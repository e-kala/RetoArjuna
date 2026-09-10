<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$noticia = ['titulo' => '', 'slug' => '', 'resumen' => '', 'contenido' => '', 'imagen' => '',
            'activo' => 1, 'publicada_at' => date('Y-m-d\TH:i')];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM noticias WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $noticia = $stmt->get_result()->fetch_assoc() ?: $noticia;
    $stmt->close();
    $noticia['publicada_at'] = date('Y-m-d\TH:i', strtotime($noticia['publicada_at']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $titulo = trim($_POST['titulo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $titulo), '-'));
    }
    $resumen = trim($_POST['resumen'] ?? '');
    $contenido = $_POST['contenido'] ?? '';
    $publicadaAt = $_POST['publicada_at'] !== '' ? str_replace('T', ' ', $_POST['publicada_at']) . ':00' : date('Y-m-d H:i:s');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $imagen = (string) $noticia['imagen'];

    try {
        $imagen = procesar_imagen_form('imagen_file', 'noticias', $imagen);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }

    if ($titulo === '' || $contenido === '') {
        $error = 'El título y el contenido son obligatorios.';
    } elseif ($error === '') {
        $esNuevo = !$id;
        if ($id) {
            $stmt = $conn->prepare('UPDATE noticias SET titulo=?, slug=?, resumen=?, contenido=?, imagen=?, publicada_at=?, activo=? WHERE id=?');
            $stmt->bind_param('ssssssii', $titulo, $slug, $resumen, $contenido, $imagen, $publicadaAt, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO noticias (titulo, slug, resumen, contenido, imagen, publicada_at, activo) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('ssssssi', $titulo, $slug, $resumen, $contenido, $imagen, $publicadaAt, $activo);
        }
        if ($stmt->execute()) {
            // No hay cron — si publicada_at queda en el futuro, la noticia
            // todavía no es visible en el catálogo (mismo filtro que ya usa
            // noticias_catalogo.php), así que tampoco tiene sentido avisar
            // de algo que nadie puede ver todavía.
            if ($esNuevo && $activo && $publicadaAt <= date('Y-m-d H:i:s')) {
                notificacion_difundir('nueva_noticia', 'Nueva noticia: ' . $titulo, $resumen !== '' ? mb_strimwidth($resumen, 0, 140, '…') : null, 'index.php?action=noticia&slug=' . urlencode($slug));
            }
            if ($esAjax) {
                echo json_encode(['success' => true, 'redirect' => 'noticias.php']);
                exit;
            }
            header('Location: noticias.php');
            exit;
        }
        $error = '¿El slug ya existe? Prueba con otro.';
        $stmt->close();
    }
    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$pageTitle = $id ? 'Editar noticia' : 'Nueva noticia';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" enctype="multipart/form-data" data-ajax-form>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-8"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($noticia['titulo']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Slug (opcional)</label><input class="form-control" name="slug" value="<?= htmlspecialchars($noticia['slug']) ?>"></div>
  <div class="col-12"><label class="form-label">Resumen (para la tarjeta del listado)</label><input class="form-control" name="resumen" value="<?= htmlspecialchars((string) $noticia['resumen']) ?>" maxlength="300"></div>
  <div class="col-12"><label class="form-label">Contenido (HTML básico)</label><textarea class="form-control" name="contenido" rows="8" required><?= htmlspecialchars($noticia['contenido']) ?></textarea></div>
  <?php
  $imgPickerId = 'noticia';
  $imgPickerCampo = 'imagen_file';
  $imgPickerSubdir = 'noticias';
  $imgPickerActual = (string) $noticia['imagen'];
  $imgPickerLabel = 'Imagen';
  include __DIR__ . '/_imagen_picker.php';
  ?>
  <div class="col-md-4"><label class="form-label">Fecha de publicación</label><input type="datetime-local" class="form-control" name="publicada_at" value="<?= htmlspecialchars($noticia['publicada_at']) ?>"></div>
  <div class="col-md-2 form-check form-switch mt-4">
    <input type="checkbox" class="form-check-input" role="switch" name="activo" id="activo" <?= (int) $noticia['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicada</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
