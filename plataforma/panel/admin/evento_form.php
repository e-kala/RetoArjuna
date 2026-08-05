<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$evento = ['titulo' => '', 'slug' => '', 'descripcion' => '', 'tipo' => 'online', 'ubicacion' => '',
           'fecha_inicio' => '', 'fecha_fin' => '', 'cupo_maximo' => '', 'precio' => 0,
           'imagen_portada' => '', 'foro_url' => '', 'gratuito' => 0, 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc() ?: $evento;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $titulo), '-'));
    }
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'online';
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
    $fechaFin = $_POST['fecha_fin'] !== '' ? $_POST['fecha_fin'] : null;
    $cupoMaximo = $_POST['cupo_maximo'] !== '' ? (int) $_POST['cupo_maximo'] : null;
    $precio = (float) ($_POST['precio'] ?? 0);
    $imagen = trim($_POST['imagen_portada'] ?? '');
    $foroUrl = trim($_POST['foro_url'] ?? '');
    $gratuito = isset($_POST['gratuito']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($titulo === '' || $fechaInicio === '') {
        $error = 'Título y fecha de inicio son obligatorios.';
    } else {
        if ($id) {
            $stmt = $conn->prepare(
                'UPDATE eventos SET titulo=?, slug=?, descripcion=?, tipo=?, ubicacion=?, fecha_inicio=?, fecha_fin=?, cupo_maximo=?, precio=?, imagen_portada=?, foro_url=?, gratuito=?, activo=? WHERE id=?'
            );
            $stmt->bind_param('sssssssidssiii', $titulo, $slug, $descripcion, $tipo, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $gratuito, $activo, $id);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO eventos (titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, fecha_fin, cupo_maximo, precio, imagen_portada, foro_url, gratuito, activo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param('sssssssidssii', $titulo, $slug, $descripcion, $tipo, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $gratuito, $activo);
        }
        if ($stmt->execute()) {
            header('Location: eventos.php');
            exit;
        }
        $error = '¿El slug ya existe? Prueba con otro.';
        $stmt->close();
    }
}

$pageTitle = $id ? 'Editar evento' : 'Nuevo evento';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-6"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($evento['titulo']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Slug (opcional)</label><input class="form-control" name="slug" value="<?= htmlspecialchars($evento['slug']) ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $evento['descripcion']) ?></textarea></div>
  <div class="col-md-4">
    <label class="form-label">Tipo</label>
    <select class="form-select" name="tipo">
      <option value="online" <?= $evento['tipo'] === 'online' ? 'selected' : '' ?>>En línea</option>
      <option value="presencial" <?= $evento['tipo'] === 'presencial' ? 'selected' : '' ?>>Presencial</option>
    </select>
  </div>
  <div class="col-md-8"><label class="form-label">Ubicación (dirección o liga de Zoom)</label><input class="form-control" name="ubicacion" value="<?= htmlspecialchars((string) $evento['ubicacion']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Fecha y hora de inicio</label><input type="datetime-local" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $evento['fecha_inicio'], 0, 16))) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Fecha y hora de fin (opcional)</label><input type="datetime-local" class="form-control" name="fecha_fin" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $evento['fecha_fin'], 0, 16))) ?>"></div>
  <div class="col-md-4"><label class="form-label">Cupo máximo (opcional)</label><input type="number" class="form-control" name="cupo_maximo" value="<?= htmlspecialchars((string) $evento['cupo_maximo']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $evento['precio']) ?>"></div>
  <div class="col-md-4 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="gratuito" id="gratuito" <?= (int) $evento['gratuito'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="gratuito">Evento gratuito</label>
  </div>
  <div class="col-md-6"><label class="form-label">Imagen de portada (ruta)</label><input class="form-control" name="imagen_portada" value="<?= htmlspecialchars((string) $evento['imagen_portada']) ?>" placeholder="content/img/evento.jpg"></div>
  <div class="col-md-6"><label class="form-label">Enlace al foro</label><input class="form-control" name="foro_url" value="<?= htmlspecialchars((string) $evento['foro_url']) ?>" placeholder="foro/tema.php?id=..."></div>
  <div class="col-md-6 form-check">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $evento['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicado</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
