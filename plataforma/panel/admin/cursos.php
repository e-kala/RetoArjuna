<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_curso') {
        $stmt = $conn->prepare('DELETE FROM cursos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Curso eliminado.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE cursos SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM cursos WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Curso publicado.' : 'Curso oculto.',
                'boton_texto' => $activo ? 'Ocultar' : 'Publicar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? 'Publicado' : 'Oculto',
            ]);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'convertir_a_evento') {
        $stmt = $conn->prepare('SELECT * FROM cursos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($c) {
            // Se copian los campos en común; el resto queda con valores por
            // defecto razonables — fecha_inicio se pone en el pasado (la
            // fecha de creación del curso) para que aparezca de una vez en
            // "eventos pasados/grabados", y el admin la ajusta si hace falta.
            $slugEvento = $c['slug'];
            $stmt = $conn->prepare(
                "INSERT INTO eventos (titulo, slug, descripcion, tipo, fecha_inicio, precio, imagen_portada, gratuito, activo)
                 VALUES (?, ?, ?, 'online', ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssdsii', $c['titulo'], $slugEvento, $c['descripcion'], $c['created_at'], $c['precio'], $c['imagen_portada'], $c['gratuito'], $c['activo']);
            if ($stmt->execute()) {
                $nuevoEventoId = $stmt->insert_id;
                $stmt->close();
                // El curso original se oculta, NO se borra — conserva su
                // historial (progreso/pagos/certificados). Las lecciones sí se
                // REASIGNAN al evento nuevo para que el contenido siga siendo
                // accesible (ver schema_lecciones_compartidas.sql).
                $stmtLec = $conn->prepare('UPDATE lecciones SET curso_id = NULL, evento_id = ? WHERE curso_id = ?');
                $stmtLec->bind_param('ii', $nuevoEventoId, $id);
                $stmtLec->execute();
                $stmtLec->close();

                $stmtOff = $conn->prepare('UPDATE cursos SET activo = 0 WHERE id = ?');
                $stmtOff->bind_param('i', $id);
                $stmtOff->execute();
                $stmtOff->close();
                header('Location: contenido_form.php?tipo=evento&id=' . $nuevoEventoId);
                exit;
            }
            $stmt->close();
        }
    }
    header('Location: cursos.php');
    exit;
}

require __DIR__ . '/_cursos_query.php';

$pageTitle = 'Cursos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Cursos</h1>
  <a href="contenido_form.php?tipo=curso" class="btn btn-success btn-sm">+ Nuevo curso</a>
</div>
<form method="get" id="cursosBuscarForm" class="mb-3 d-flex gap-2 flex-wrap" style="max-width:400px;">
  <input type="search" name="q" id="cursosBuscarQ" class="form-control" placeholder="Buscar por título" value="<?= htmlspecialchars($busqueda) ?>" autocomplete="off">
  <span id="cursosBuscarCargando" class="spinner-border spinner-border-sm text-secondary align-self-center" style="display:none;" aria-hidden="true"></span>
</form>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>Precio</th><th>Lecciones</th><th>Creado</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody id="cursosTbody">
    <?php include __DIR__ . '/_cursos_filas.php'; ?>
  </tbody>
</table>
</div>
<script>
(function () {
  var form = document.getElementById('cursosBuscarForm');
  var input = document.getElementById('cursosBuscarQ');
  var tbody = document.getElementById('cursosTbody');
  var cargando = document.getElementById('cursosBuscarCargando');
  var timer = null;
  var ultimaPeticion = 0;

  form.addEventListener('submit', function (e) { e.preventDefault(); });

  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(buscar, 250);
  });

  function buscar() {
    var idPeticion = ++ultimaPeticion;
    cargando.style.display = 'inline-block';
    var params = new URLSearchParams(new FormData(form));
    fetch('cursos_buscar.php?' + params.toString())
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (idPeticion !== ultimaPeticion) return; // respuesta obsoleta, llegó otra después
        tbody.innerHTML = html;
      })
      .catch(function () {
        if (idPeticion === ultimaPeticion) tbody.innerHTML = '<tr><td colspan="6" class="text-danger text-center">No se pudo buscar, intenta de nuevo.</td></tr>';
      })
      .finally(function () {
        if (idPeticion === ultimaPeticion) cargando.style.display = 'none';
      });
  }
})();
</script>
<?php include __DIR__ . '/_footer.php'; ?>
