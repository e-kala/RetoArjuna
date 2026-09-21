<?php
// Qué avisos de contenido nuevo se difunden a todos los usuarios — ver
// backend/notificaciones.php (notificacion_difundir()) y sus disparadores en
// contenido_form.php/producto_form.php/noticia_form.php. Respuestas y
// menciones del foro no aparecen aquí a propósito: son 1-a-1, disparadas por
// una acción directa de otro usuario, no un aviso masivo que convenga apagar.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'toggle_activo') {
    $esAjax = es_peticion_ajax();
    $tipo = $_POST['tipo'] ?? '';
    $stmt = $conn->prepare('UPDATE notificacion_config SET activo = 1 - activo WHERE tipo = ?');
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) {
        $stmt = $conn->prepare('SELECT activo FROM notificacion_config WHERE tipo = ?');
        $stmt->bind_param('s', $tipo);
        $stmt->execute();
        $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
        $stmt->close();
        echo json_encode([
            'success' => true,
            'mensaje' => $activo ? 'Notificación activada.' : 'Notificación desactivada.',
            'boton_texto' => $activo ? 'Desactivar' : 'Activar',
            'boton_accion' => 'toggle_activo',
            'estado_html' => $activo ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Desactivada</span>',
        ]);
        exit;
    }
    header('Location: notificaciones_config.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_difusion') {
    $esAjax = es_peticion_ajax();
    $difusionId = (int) ($_POST['id'] ?? 0);
    notificacion_difusion_eliminar($difusionId);
    if ($esAjax) {
        echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Notificación eliminada de la bandeja de todos.']);
        exit;
    }
    header('Location: notificaciones_config.php');
    exit;
}

// Sección de WhatsApp — deliberadamente restringida a es_super_admin(), no a
// cualquier admin (ver auth.php). Un admin normal ni siquiera puede mandar
// este POST: el guard de abajo corta antes de tocar la tabla.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_plantilla_whatsapp') {
    require_super_admin();
    $esAjax = es_peticion_ajax();
    $tipo = (string) ($_POST['tipo'] ?? '');
    $nombrePlantilla = trim((string) ($_POST['nombre_plantilla'] ?? ''));
    $idioma = trim((string) ($_POST['idioma'] ?? 'es_MX')) ?: 'es_MX';
    $activo = !empty($_POST['activo']) ? 1 : 0;
    if (!in_array($tipo, ['pago_confirmado', 'voucher_membresia'], true)) {
        echo json_encode(['success' => false, 'mensaje' => 'Tipo no reconocido.']);
        exit;
    }
    $stmt = $conn->prepare(
        'INSERT INTO whatsapp_plantillas (tipo, nombre_plantilla, idioma, activo) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre_plantilla = VALUES(nombre_plantilla), idioma = VALUES(idioma), activo = VALUES(activo)'
    );
    $stmt->bind_param('sssi', $tipo, $nombrePlantilla, $idioma, $activo);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) {
        echo json_encode(['success' => true, 'mensaje' => 'Plantilla guardada.']);
        exit;
    }
    header('Location: notificaciones_config.php');
    exit;
}

$tiposLabel = [
    'nuevo_curso' => ['icono' => 'bi-book', 'texto' => 'Curso nuevo publicado'],
    'nuevo_evento' => ['icono' => 'bi-calendar-event', 'texto' => 'Evento nuevo publicado'],
    'nuevo_producto' => ['icono' => 'bi-shop', 'texto' => 'Producto nuevo publicado'],
    'nueva_noticia' => ['icono' => 'bi-newspaper', 'texto' => 'Noticia nueva publicada'],
];
$config = $conn->query('SELECT * FROM notificacion_config')->fetch_all(MYSQLI_ASSOC);
$configPorTipo = array_column($config, null, 'tipo');
$difusiones = notificaciones_difusiones_recientes(50);
$tiposLabelDifusion = $tiposLabel + ['aviso_admin' => ['icono' => 'bi-megaphone-fill', 'texto' => 'Aviso personalizado']];

// Sección WhatsApp: solo se consulta/pinta si es_super_admin() — nadie más
// ve ni el bloque ni sabe que esta tabla existe.
$whatsappTiposLabel = [
    'pago_confirmado' => ['icono' => 'bi-check-circle-fill', 'texto' => 'Comprobante de pago confirmado'],
    'voucher_membresia' => ['icono' => 'bi-cash-coin', 'texto' => 'Voucher OXXO de membresía listo'],
];
$whatsappPlantillas = [];
if (es_super_admin()) {
    $filas = $conn->query('SELECT * FROM whatsapp_plantillas')->fetch_all(MYSQLI_ASSOC);
    $whatsappPlantillas = array_column($filas, null, 'tipo');
}

$pageTitle = 'Notificaciones';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Notificaciones</h1>
<?php if (isset($_GET['enviada'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Notificación enviada a <?= (int) ($_GET['total'] ?? 0) ?> usuarios.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<p class="text-muted small">
  Cuando se publica contenido nuevo, todos los usuarios reciben un aviso en su campana de notificaciones.
  Desactiva aquí los tipos que no quieres que se avisen masivamente — las respuestas y menciones del foro
  no se controlan desde aquí porque van dirigidas a una sola persona, no a todos.
</p>
<div class="table-responsive">
<table class="table table-bordered bg-white" style="max-width:560px;">
  <thead><tr><th>Evento</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($tiposLabel as $tipo => $info): ?>
      <?php $activo = (int) ($configPorTipo[$tipo]['activo'] ?? 1) === 1; ?>
      <tr>
        <td><i class="bi <?= $info['icono'] ?>"></i> <?= htmlspecialchars($info['texto']) ?></td>
        <td data-ajax-estado><?= $activo ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Desactivada</span>' ?></td>
        <td>
          <form method="post" data-ajax="toggle">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
            <input type="hidden" name="accion" value="toggle_activo">
            <div class="form-check form-switch mb-0">
              <input type="checkbox" class="form-check-input" role="switch" <?= $activo ? 'checked' : '' ?> aria-label="<?= $activo ? 'Desactivar' : 'Activar' ?>">
            </div>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="d-flex justify-content-between align-items-center mt-5 mb-3">
  <h2 class="h5 mb-0">Notificaciones enviadas</h2>
  <a href="notificacion_difusion_form.php" class="btn btn-success btn-sm">+ Nueva notificación</a>
</div>
<p class="text-muted small">
  Cada fila es un aviso masivo — ya sea automático (contenido nuevo publicado) o un mensaje que compusiste a
  mano. Eliminarlo lo quita de la bandeja de notificaciones de todos los usuarios que lo recibieron.
</p>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Tipo</th><th>Título</th><th>Destinatarios</th><th>Enviada por</th><th>Fecha</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($difusiones as $d): ?>
      <?php $infoTipo = $tiposLabelDifusion[$d['tipo']] ?? ['icono' => 'bi-bell', 'texto' => $d['tipo']]; ?>
      <tr>
        <td><i class="bi <?= $infoTipo['icono'] ?>"></i> <?= htmlspecialchars($infoTipo['texto']) ?></td>
        <td>
          <?= htmlspecialchars($d['titulo']) ?>
          <?php if ($d['mensaje']): ?><br><span class="text-muted small"><?= htmlspecialchars($d['mensaje']) ?></span><?php endif; ?>
        </td>
        <td><?= (int) $d['total_destinatarios'] ?></td>
        <td><?= htmlspecialchars((string) ($d['creado_por_username'] ?? '— (automático)')) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($d['created_at']))) ?></td>
        <td>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar esta notificación de la bandeja de los <?= (int) $d['total_destinatarios'] ?> usuarios que la recibieron?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_difusion">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$difusiones): ?><tr><td colspan="6" class="text-muted">No se ha enviado ninguna notificación masiva todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<?php if (es_super_admin()): ?>
<div class="mt-5">
  <h2 class="h5 mb-2">📱 WhatsApp (experimental)</h2>
  <p class="text-muted small">
    Canal extra sobre correo + campana — solo se manda si <code>WHATSAPP_TOKEN</code>/<code>WHATSAPP_PHONE_ID</code>
    están configurados (<code>config.local.php</code>) y el tipo tiene una plantilla <strong>ya aprobada por Meta</strong> capturada
    aquí abajo. Meta exige que cualquier mensaje que iniciemos nosotros (el usuario no nos escribió primero) use una
    plantilla pre-aprobada — no se puede mandar texto libre. Da de alta y aprueba la plantilla en
    <em>Meta Business Manager → WhatsApp Manager → Plantillas de mensajes</em> antes de activarla aquí; el cuerpo debe
    tener dos variables <code>{{1}}</code> (título) y <code>{{2}}</code> (mensaje), en ese orden — son los mismos textos
    que ya se mandan por correo y en la campana de la plataforma. Solo llega a usuarios que tengan un teléfono
    capturado en su perfil.
    <?php if (!whatsapp_esta_listo()): ?>
      <br><span class="text-danger">⚠️ WHATSAPP_TOKEN/WHATSAPP_PHONE_ID no están configurados todavía en este entorno — nada se enviará aunque actives una plantilla aquí.</span>
    <?php endif; ?>
  </p>
  <div class="table-responsive">
  <table class="table table-bordered bg-white" style="max-width:760px;">
    <thead><tr><th>Evento</th><th>Nombre de la plantilla en Meta</th><th>Idioma</th><th>Activa</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($whatsappTiposLabel as $tipo => $info): ?>
        <?php $fila = $whatsappPlantillas[$tipo] ?? ['nombre_plantilla' => '', 'idioma' => 'es_MX', 'activo' => 0]; ?>
        <tr>
          <td><i class="bi <?= $info['icono'] ?>"></i> <?= htmlspecialchars($info['texto']) ?></td>
          <td colspan="4">
            <form method="post" data-ajax-form class="d-flex gap-2 align-items-center flex-wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="accion" value="guardar_plantilla_whatsapp">
              <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
              <input type="text" name="nombre_plantilla" class="form-control form-control-sm" style="max-width:220px;"
                     placeholder="nombre_exacto_de_la_plantilla" value="<?= htmlspecialchars($fila['nombre_plantilla']) ?>">
              <input type="text" name="idioma" class="form-control form-control-sm" style="max-width:100px;"
                     value="<?= htmlspecialchars($fila['idioma']) ?>">
              <div class="form-check form-switch mb-0">
                <input type="checkbox" name="activo" class="form-check-input" role="switch" <?= ((int) $fila['activo'] === 1) ? 'checked' : '' ?>>
                <label class="form-check-label small">Activa</label>
              </div>
              <button class="btn btn-sm btn-outline-primary">Guardar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
