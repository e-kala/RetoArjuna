<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $titulo = trim($_POST['titulo'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $enlace = trim($_POST['enlace'] ?? '');

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } else {
        // Sin enlace explícito, manda al inicio — toda notificación necesita
        // una URL a donde llevar al hacer click (mismo campo que usan las
        // automáticas de contenido nuevo).
        if ($enlace === '') {
            $enlace = 'index.php';
        }
        $miId = (int) $_SESSION['usuario_perfil_id'];
        $total = notificacion_difundir_personalizada($titulo, $mensaje !== '' ? $mensaje : null, $enlace, $miId);
        $destino = 'notificaciones_config.php?enviada=1&total=' . $total;

        if ($esAjax) {
            echo json_encode(['success' => true, 'redirect' => $destino]);
            exit;
        }
        header('Location: ' . $destino);
        exit;
    }

    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$pageTitle = 'Nueva notificación';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Nueva notificación</h1>
<p class="text-muted small">
  Se enviará de inmediato a la campana de notificaciones de <strong>todos los usuarios</strong> de la plataforma.
  Úsalo para avisos que no encajan en "contenido nuevo" (curso/evento/producto/noticia) — un cambio importante,
  una promoción por tiempo limitado, mantenimiento programado, etc.
</p>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" data-ajax-form style="max-width:640px;">
  <?= csrf_field() ?>
  <div class="col-12">
    <label class="form-label">Título</label>
    <input class="form-control" name="titulo" maxlength="200" required autofocus>
  </div>
  <div class="col-12">
    <label class="form-label">Mensaje (opcional)</label>
    <textarea class="form-control" name="mensaje" rows="3" maxlength="500"></textarea>
  </div>
  <div class="col-12">
    <label class="form-label">A dónde lleva al hacer click (opcional)</label>
    <input class="form-control" name="enlace" placeholder="index.php?action=noticias">
    <div class="form-text">Ruta dentro de la plataforma, relativa a <?= htmlspecialchars(BASE_URL) ?>/ — por ejemplo <code>index.php?action=membresia</code>. Vacío = lleva al inicio.</div>
  </div>
  <div class="col-12">
    <button class="btn btn-success">Enviar a todos los usuarios</button>
    <a href="notificaciones_config.php" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
