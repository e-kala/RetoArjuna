<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$producto = ['tipo' => 'fisico', 'nombre' => '', 'slug' => '', 'descripcion' => '',
             'precio' => 0, 'imagen' => '', 'archivo_digital' => '', 'stock' => '', 'activo' => 1];

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
    $archivoDigital = (string) $producto['archivo_digital'];
    $stock = ($tipo === 'fisico' && $_POST['stock'] !== '') ? (int) $_POST['stock'] : null;
    $activo = isset($_POST['activo']) ? 1 : 0;

    $archivoDigitalUrl = trim($_POST['archivo_digital_url'] ?? '');

    try {
        $imagen = procesar_imagen_form('imagen_file', 'productos', $imagen);
        if ($archivoDigitalUrl !== '') {
            // Un enlace externo (Drive, Dropbox, etc.) gana sobre un archivo ya subido.
            $archivoDigital = $archivoDigitalUrl;
        } else {
            $archivoSubido = procesar_subida_archivo_digital('archivo_digital_file', 'productos_digital');
            if ($archivoSubido !== null) {
                $archivoDigital = $archivoSubido;
            }
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } elseif ($error === '') {
        if ($id) {
            $stmt = $conn->prepare(
                'UPDATE productos SET tipo=?, nombre=?, slug=?, descripcion=?, precio=?, imagen=?, archivo_digital=?, stock=?, activo=? WHERE id=?'
            );
            $stmt->bind_param('ssssdssiii', $tipo, $nombre, $slug, $descripcion, $precio, $imagen, $archivoDigital, $stock, $activo, $id);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO productos (tipo, nombre, slug, descripcion, precio, imagen, archivo_digital, stock, activo) VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param('ssssdssii', $tipo, $nombre, $slug, $descripcion, $precio, $imagen, $archivoDigital, $stock, $activo);
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
    <select class="form-select" name="tipo" id="tipoProducto" onchange="document.getElementById('stockWrap').style.display = this.value === 'fisico' ? 'block' : 'none'; document.getElementById('archivoDigitalWrap').style.display = this.value === 'digital' ? 'block' : 'none';">
      <option value="fisico" <?= $producto['tipo'] === 'fisico' ? 'selected' : '' ?>>Físico (playera, taza, libro...)</option>
      <option value="digital" <?= $producto['tipo'] === 'digital' ? 'selected' : '' ?>>Digital (infoproducto)</option>
    </select>
  </div>
  <div class="col-md-8"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Slug (opcional)</label><input class="form-control" name="slug" value="<?= htmlspecialchars($producto['slug']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $producto['precio']) ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $producto['descripcion']) ?></textarea></div>
  <?php
  $imgPickerId = 'producto';
  $imgPickerCampo = 'imagen_file';
  $imgPickerSubdir = 'productos';
  $imgPickerActual = (string) $producto['imagen'];
  $imgPickerLabel = 'Imagen';
  include __DIR__ . '/_imagen_picker.php';
  ?>
  <div class="col-md-6" id="stockWrap" style="display:<?= $producto['tipo'] === 'fisico' ? 'block' : 'none' ?>;">
    <label class="form-label">Stock (vacío = ilimitado)</label>
    <input type="number" class="form-control" name="stock" value="<?= htmlspecialchars((string) $producto['stock']) ?>">
  </div>
  <div class="col-md-6" id="archivoDigitalWrap" style="display:<?= $producto['tipo'] === 'digital' ? 'block' : 'none' ?>;">
    <label class="form-label">Archivo a entregar (PDF, ZIP, EPUB, MP3, MP4)</label>
    <input type="file" class="form-control mb-2" name="archivo_digital_file" accept=".pdf,.zip,.epub,.mp3,.mp4">
    <label class="form-label small">— o pega un enlace externo (Drive, Dropbox...) —</label>
    <input type="url" class="form-control" name="archivo_digital_url" placeholder="https://...">
    <?php $esUrlExterna = (bool) preg_match('~^https?://~i', (string) $producto['archivo_digital']); ?>
    <?php if ($producto['archivo_digital']): ?>
      <div class="mt-2 text-muted small">
        Archivo actual: <a href="<?= $esUrlExterna ? htmlspecialchars($producto['archivo_digital']) : '../../' . htmlspecialchars($producto['archivo_digital']) ?>" target="_blank">descargar</a> — sube otro o pega otro enlace para reemplazarlo.
      </div>
    <?php endif; ?>
    <div class="form-text">Se le mostrará como enlace de descarga en "Mis compras" a quien lo compre.</div>
  </div>
  <div class="col-md-6 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $producto['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicado</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
