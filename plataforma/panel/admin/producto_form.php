<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$producto = ['tipo' => 'fisico', 'nombre' => '', 'slug' => '', 'descripcion' => '',
             'precio' => 0, 'imagen' => '', 'stock' => '', 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc() ?: $producto;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? 'fisico';
    $nombre = trim($_POST['nombre'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $nombre), '-'));
    }
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float) ($_POST['precio'] ?? 0);
    $imagen = (string) $producto['imagen'];
    $stock = ($tipo === 'fisico' && $_POST['stock'] !== '') ? (int) $_POST['stock'] : null;
    $activo = isset($_POST['activo']) ? 1 : 0;

    try {
        $imagenSubida = procesar_subida_imagen('imagen_file', 'productos');
        if ($imagenSubida !== null) {
            $imagen = $imagenSubida;
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } elseif ($error === '') {
        if ($id) {
            $stmt = $conn->prepare(
                'UPDATE productos SET tipo=?, nombre=?, slug=?, descripcion=?, precio=?, imagen=?, stock=?, activo=? WHERE id=?'
            );
            $stmt->bind_param('ssssdsiii', $tipo, $nombre, $slug, $descripcion, $precio, $imagen, $stock, $activo, $id);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO productos (tipo, nombre, slug, descripcion, precio, imagen, stock, activo) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param('ssssdsii', $tipo, $nombre, $slug, $descripcion, $precio, $imagen, $stock, $activo);
        }
        if ($stmt->execute()) {
            header('Location: productos.php');
            exit;
        }
        $error = '¿El slug ya existe? Prueba con otro.';
        $stmt->close();
    }
}

$pageTitle = $id ? 'Editar producto' : 'Nuevo producto';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-4">
    <label class="form-label">Tipo</label>
    <select class="form-select" name="tipo" id="tipoProducto" onchange="document.getElementById('stockWrap').style.display = this.value === 'fisico' ? 'block' : 'none';">
      <option value="fisico" <?= $producto['tipo'] === 'fisico' ? 'selected' : '' ?>>Físico (playera, taza, libro...)</option>
      <option value="digital" <?= $producto['tipo'] === 'digital' ? 'selected' : '' ?>>Digital (infoproducto)</option>
    </select>
  </div>
  <div class="col-md-8"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Slug (opcional)</label><input class="form-control" name="slug" value="<?= htmlspecialchars($producto['slug']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $producto['precio']) ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $producto['descripcion']) ?></textarea></div>
  <div class="col-md-6">
    <label class="form-label">Imagen</label>
    <input type="file" class="form-control" name="imagen_file" accept="image/png,image/jpeg,image/webp,image/gif">
    <?php if ($producto['imagen']): ?>
      <div class="mt-2 d-flex align-items-center gap-2">
        <img src="../../<?= htmlspecialchars($producto['imagen']) ?>" alt="" style="height:60px;border-radius:6px;">
        <span class="text-muted small">Imagen actual — sube otra para reemplazarla.</span>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-6" id="stockWrap" style="display:<?= $producto['tipo'] === 'fisico' ? 'block' : 'none' ?>;">
    <label class="form-label">Stock (vacío = ilimitado)</label>
    <input type="number" class="form-control" name="stock" value="<?= htmlspecialchars((string) $producto['stock']) ?>">
  </div>
  <div class="col-md-6 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $producto['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicado</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
