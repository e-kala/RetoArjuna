<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_producto') {
        $stmt = $conn->prepare('DELETE FROM productos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Producto eliminado.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE productos SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM productos WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Producto publicado.' : 'Producto oculto.',
                'boton_texto' => $activo ? 'Ocultar' : 'Publicar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? 'Publicado' : 'Oculto',
            ]);
            exit;
        }
    }
    header('Location: productos.php');
    exit;
}

require __DIR__ . '/_productos_query.php';

$pageTitle = 'Productos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Productos</h1>
  <a href="producto_form.php" class="btn btn-success btn-sm">+ Nuevo producto</a>
</div>
<form method="get" id="productosBuscarForm" class="mb-3 d-flex gap-2 flex-wrap" style="max-width:400px;">
  <input type="search" name="q" id="productosBuscarQ" class="form-control" placeholder="Buscar por nombre" value="<?= htmlspecialchars($busqueda) ?>" autocomplete="off">
  <span id="productosBuscarCargando" class="spinner-border spinner-border-sm text-secondary align-self-center" style="display:none;" aria-hidden="true"></span>
</form>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Nombre</th><th>Tipo</th><th>Precio</th><th>Stock</th><th>Creado</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody id="productosTbody">
    <?php include __DIR__ . '/_productos_filas.php'; ?>
  </tbody>
</table>
</div>
<script>
(function () {
  var form = document.getElementById('productosBuscarForm');
  var input = document.getElementById('productosBuscarQ');
  var tbody = document.getElementById('productosTbody');
  var cargando = document.getElementById('productosBuscarCargando');
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
    fetch('productos_buscar.php?' + params.toString())
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (idPeticion !== ultimaPeticion) return;
        tbody.innerHTML = html;
      })
      .catch(function () {
        if (idPeticion === ultimaPeticion) tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">No se pudo buscar, intenta de nuevo.</td></tr>';
      })
      .finally(function () {
        if (idPeticion === ultimaPeticion) cargando.style.display = 'none';
      });
  }
})();
</script>
<?php include __DIR__ . '/_footer.php'; ?>
