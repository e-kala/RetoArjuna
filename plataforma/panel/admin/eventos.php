<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_evento') {
        $stmt = $conn->prepare('DELETE FROM eventos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Evento eliminado.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE eventos SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM eventos WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Evento publicado.' : 'Evento oculto.',
                'boton_texto' => $activo ? 'Ocultar' : 'Publicar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? 'Publicado' : 'Oculto',
            ]);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'convertir_a_curso') {
        $stmt = $conn->prepare('SELECT * FROM eventos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ev = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ev) {
            $slugCurso = $ev['slug'];
            $stmt = $conn->prepare(
                "INSERT INTO cursos (titulo, slug, descripcion, nivel, precio, imagen_portada, gratuito, activo)
                 VALUES (?, ?, ?, 'principiante', ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssdsii', $ev['titulo'], $slugCurso, $ev['descripcion'], $ev['precio'], $ev['imagen_portada'], $ev['gratuito'], $ev['activo']);
            if ($stmt->execute()) {
                $nuevoCursoId = $stmt->insert_id;
                $stmt->close();
                // El evento original se oculta, NO se borra — conserva su
                // historial (inscripciones/certificados/pagos). Las lecciones
                // sí se REASIGNAN al curso nuevo para que el contenido siga
                // siendo accesible (ver schema_lecciones_compartidas.sql).
                $stmtLec = $conn->prepare('UPDATE lecciones SET evento_id = NULL, curso_id = ? WHERE evento_id = ?');
                $stmtLec->bind_param('ii', $nuevoCursoId, $id);
                $stmtLec->execute();
                $stmtLec->close();

                $stmtOff = $conn->prepare('UPDATE eventos SET activo = 0 WHERE id = ?');
                $stmtOff->bind_param('i', $id);
                $stmtOff->execute();
                $stmtOff->close();
                header('Location: contenido_form.php?tipo=curso&id=' . $nuevoCursoId);
                exit;
            }
            $stmt->close();
        }
    }
    header('Location: eventos.php');
    exit;
}

require __DIR__ . '/_eventos_query.php';

$pageTitle = 'Eventos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Eventos</h1>
  <a href="contenido_form.php?tipo=evento" class="btn btn-success btn-sm">+ Nuevo evento</a>
</div>
<form method="get" id="eventosBuscarForm" class="mb-3 d-flex gap-2 flex-wrap" style="max-width:400px;">
  <input type="search" name="q" id="eventosBuscarQ" class="form-control" placeholder="Buscar por título" value="<?= htmlspecialchars($busqueda) ?>" autocomplete="off">
  <span id="eventosBuscarCargando" class="spinner-border spinner-border-sm text-secondary align-self-center" style="display:none;" aria-hidden="true"></span>
</form>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>Tipo</th><th>Fecha del evento</th><th>Creado</th><th>Precio</th><th>Inscritos</th><th>Lecciones</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody id="eventosTbody">
    <?php include __DIR__ . '/_eventos_filas.php'; ?>
  </tbody>
</table>
</div>
<script>
(function () {
  var form = document.getElementById('eventosBuscarForm');
  var input = document.getElementById('eventosBuscarQ');
  var tbody = document.getElementById('eventosTbody');
  var cargando = document.getElementById('eventosBuscarCargando');
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
    fetch('eventos_buscar.php?' + params.toString())
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (idPeticion !== ultimaPeticion) return;
        tbody.innerHTML = html;
      })
      .catch(function () {
        if (idPeticion === ultimaPeticion) tbody.innerHTML = '<tr><td colspan="9" class="text-danger text-center">No se pudo buscar, intenta de nuevo.</td></tr>';
      })
      .finally(function () {
        if (idPeticion === ultimaPeticion) cargando.style.display = 'none';
      });
  }
})();
</script>
<?php include __DIR__ . '/_footer.php'; ?>
