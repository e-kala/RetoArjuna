<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$actividad = ['titulo' => '', 'descripcion' => '', 'icono' => '📌', 'enlace_url' => '', 'orden' => 0, 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM actividades WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $actividad = $stmt->get_result()->fetch_assoc() ?: $actividad;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $icono = trim($_POST['icono'] ?? '') ?: '📌';
    $enlaceUrl = trim($_POST['enlace_url'] ?? '');
    $orden = (int) ($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } else {
        if ($id) {
            $stmt = $conn->prepare('UPDATE actividades SET titulo=?, descripcion=?, icono=?, enlace_url=?, orden=?, activo=? WHERE id=?');
            $stmt->bind_param('ssssiii', $titulo, $descripcion, $icono, $enlaceUrl, $orden, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO actividades (titulo, descripcion, icono, enlace_url, orden, activo) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('ssssii', $titulo, $descripcion, $icono, $enlaceUrl, $orden, $activo);
        }
        $stmt->execute();
        $stmt->close();
        header('Location: actividades.php');
        exit;
    }
}

$pageTitle = $id ? 'Editar actividad' : 'Nueva actividad';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-2"><label class="form-label">Icono (emoji)</label><input class="form-control" name="icono" value="<?= htmlspecialchars($actividad['icono']) ?>" maxlength="10"></div>
  <div class="col-md-8"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($actividad['titulo']) ?>" required></div>
  <div class="col-md-2"><label class="form-label">Orden</label><input type="number" class="form-control" name="orden" value="<?= (int) $actividad['orden'] ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $actividad['descripcion']) ?></textarea></div>
  <div class="col-md-8"><label class="form-label">Enlace (opcional)</label><input class="form-control" name="enlace_url" value="<?= htmlspecialchars((string) $actividad['enlace_url']) ?>" placeholder="https://..."></div>
  <div class="col-md-4 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $actividad['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Visible</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
