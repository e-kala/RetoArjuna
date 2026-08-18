<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_role('admin');
requerir_csrf_form();

$miId = (int) $_SESSION['usuario_perfil_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar_membresia') {
        $stmt = $conn->prepare('DELETE FROM membresias WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE membresias SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'cancelar_pendiente') {
        $stmt = $conn->prepare("DELETE FROM membresia_suscripciones WHERE id = ? AND estado = 'pendiente'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: membresias.php');
    exit;
}

$membresias = $conn->query('SELECT * FROM membresias ORDER BY orden ASC, nombre ASC')->fetch_all(MYSQLI_ASSOC);

$pendientes = $conn->query(
    "SELECT s.*, u.username_cache, u.email_cache, m.nombre AS membresia_nombre
     FROM membresia_suscripciones s
     JOIN usuarios_perfil u ON u.id = s.usuario_id
     JOIN membresias m ON m.id = s.membresia_id
     WHERE s.estado = 'pendiente'
     ORDER BY s.created_at ASC"
)->fetch_all(MYSQLI_ASSOC);

$suscripciones = $conn->query(
    "SELECT s.*, u.username_cache, u.email_cache, m.nombre AS membresia_nombre
     FROM membresia_suscripciones s
     JOIN usuarios_perfil u ON u.id = s.usuario_id
     JOIN membresias m ON m.id = s.membresia_id
     WHERE s.estado <> 'pendiente'
     ORDER BY (s.estado = 'activa') DESC, s.created_at DESC
     LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);

$usuarios = $conn->query('SELECT id, username_cache, email_cache FROM usuarios_perfil ORDER BY username_cache ASC')->fetch_all(MYSQLI_ASSOC);
$listaMembresiasModal = $membresias;
$listaUsuariosModal = $usuarios;

$estadoBadge = ['activa' => 'bg-success', 'cancelada' => 'bg-secondary', 'vencida' => 'bg-danger', 'pendiente' => 'bg-warning'];
$metodoLabel = ['stripe' => 'Stripe', 'transferencia' => 'Transferencia', 'manual' => 'Manual'];
$pageTitle = 'Membresías';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Membresías</h1>
  <a href="membresia_form.php" class="btn btn-success btn-sm">+ Nueva membresía</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white mb-5">
  <thead><tr><th>Nombre</th><th>Precio</th><th>Intervalo</th><th>Stripe Price ID</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($membresias as $m): ?>
      <tr>
        <td><?= htmlspecialchars($m['nombre']) ?></td>
        <td>$<?= number_format((float) $m['precio'], 2) ?> MXN</td>
        <td><?= $m['intervalo'] === 'anual' ? 'Anual' : 'Mensual' ?></td>
        <td><?= $m['stripe_price_id'] ? '<code>' . htmlspecialchars($m['stripe_price_id']) . '</code>' : '<span class="text-danger">sin configurar</span>' ?></td>
        <td><?= (int) $m['activo'] === 1 ? 'Activa' : 'Oculta' ?></td>
        <td class="d-flex gap-2">
          <a href="membresia_form.php?id=<?= (int) $m['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><button class="btn btn-sm btn-outline-secondary"><?= (int) $m['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button></form>
          <form method="post" onsubmit="return confirm('¿Eliminar membresía?');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="accion" value="eliminar_membresia"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$membresias): ?><tr><td colspan="6" class="text-muted">No hay membresías creadas.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<div class="mb-5">
  <h2 class="h5 mb-2">👑 Membresías de usuarios</h2>
  <p class="text-muted small">Para pagos en efectivo, cortesías, o cualquier alta que no pase por Stripe.</p>
  <?php if ($membresias): ?>
    <button type="button" class="btn btn-success btn-sm" onclick="abrirMembresiaModal({ titulo: 'Otorgar membresía', volver: 'membresias.php' })">+ Otorgar membresía</button>
  <?php endif; ?>
</div>

<?php if ($pendientes): ?>
<h2 class="h5 mb-3">Pendientes de confirmar (transferencia)</h2>
<div class="table-responsive">
<table class="table table-bordered bg-white mb-5">
  <thead><tr><th>Usuario</th><th>Membresía</th><th>Comprobante</th><th>Solicitado</th><th>Confirmar</th></tr></thead>
  <tbody>
    <?php foreach ($pendientes as $p): ?>
      <tr>
        <td><?= htmlspecialchars((string) $p['username_cache']) ?><br><span class="text-muted small"><?= htmlspecialchars((string) $p['email_cache']) ?></span></td>
        <td><?= htmlspecialchars($p['membresia_nombre']) ?></td>
        <td><?= $p['comprobante_url'] ? '<a href="../../' . htmlspecialchars($p['comprobante_url']) . '" target="_blank">Ver</a>' : '<span class="text-muted">— (revisa WhatsApp)</span>' ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($p['created_at']))) ?></td>
        <td class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-sm btn-success" onclick="abrirMembresiaModal({
            titulo: 'Confirmar transferencia',
            suscripcionId: <?= (int) $p['id'] ?>,
            usuarioId: <?= (int) $p['usuario_id'] ?>,
            usuarioLabel: <?= htmlspecialchars(json_encode($p['username_cache'] . ' — ' . $p['email_cache']), ENT_QUOTES) ?>,
            membresiaId: <?= (int) $p['membresia_id'] ?>,
            metodo: 'transferencia',
            volver: 'membresias.php'
          })">Confirmar</button>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Descartar esta solicitud?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="accion" value="cancelar_pendiente">
            <button class="btn btn-sm btn-outline-danger">Descartar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<h2 class="h5 mb-3">Suscripciones</h2>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Membresía</th><th>Método</th><th>Estado</th><th>Vence/renueva</th><th>Desde</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($suscripciones as $s): ?>
      <tr>
        <td><?= htmlspecialchars((string) $s['username_cache']) ?><br><span class="text-muted small"><?= htmlspecialchars((string) $s['email_cache']) ?></span></td>
        <td><?= htmlspecialchars($s['membresia_nombre']) ?></td>
        <td><?= $metodoLabel[$s['metodo']] ?? htmlspecialchars($s['metodo']) ?></td>
        <td><span class="badge <?= $estadoBadge[$s['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($s['estado']) ?></span></td>
        <td>
          <?= $s['periodo_actual_fin'] ? htmlspecialchars(date('d/m/Y', strtotime($s['periodo_actual_fin']))) : 'No caduca' ?>
          <?php if ((int) $s['renovacion_automatica'] === 1): ?><span class="badge bg-info text-dark ms-1" title="Se renueva automáticamente">🔁</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($s['fecha_inicio'] ?? $s['created_at']))) ?></td>
        <td>
          <?php if ($s['metodo'] !== 'stripe'): ?>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirMembresiaModal({
              titulo: 'Editar suscripción',
              suscripcionId: <?= (int) $s['id'] ?>,
              usuarioId: <?= (int) $s['usuario_id'] ?>,
              usuarioLabel: <?= htmlspecialchars(json_encode($s['username_cache'] . ' — ' . $s['email_cache']), ENT_QUOTES) ?>,
              membresiaId: <?= (int) $s['membresia_id'] ?>,
              metodo: <?= htmlspecialchars(json_encode($s['metodo']), ENT_QUOTES) ?>,
              fechaInicio: <?= htmlspecialchars(json_encode($s['fecha_inicio'] ?? date('Y-m-d', strtotime($s['created_at']))), ENT_QUOTES) ?>,
              caduca: <?= $s['periodo_actual_fin'] ? 'true' : 'false' ?>,
              renovacionAutomatica: <?= (int) $s['renovacion_automatica'] === 1 ? 'true' : 'false' ?>,
              notificar: false,
              volver: 'membresias.php'
            })">Editar</button>
          <?php else: ?>
            <span class="text-muted small">Gestionada por Stripe</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$suscripciones): ?><tr><td colspan="7" class="text-muted">Todavía no hay suscripciones.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_membresia_modal.php'; ?>
<?php include __DIR__ . '/_footer.php'; ?>
