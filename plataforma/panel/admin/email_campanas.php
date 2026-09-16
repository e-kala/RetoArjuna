<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$campanas = $conn->query(
    "SELECT c.*, u.username_cache AS creado_por_username
     FROM email_campanas c
     LEFT JOIN usuarios_perfil u ON u.id = c.creado_por
     ORDER BY c.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$segmentoLabel = [
    'todos' => 'Todos los usuarios',
    'inactivos' => 'Inactivos (30+ días sin sesión)',
    'miembros' => 'Miembros activos',
    'sin_compra' => 'Sin ninguna compra confirmada',
];

$pageTitle = 'Campañas de correo';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Campañas de correo</h1>
  <a href="email_campana_form.php" class="btn btn-success btn-sm">+ Nueva campaña</a>
</div>
<?php if (isset($_GET['enviada'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Campaña enviada: <?= (int) ($_GET['total'] ?? 0) ?> de <?= (int) ($_GET['de'] ?? 0) ?> correos entregados.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<p class="text-muted small">
  Correo real (no notificación de la campana) a un segmento de usuarios. Cada campaña se redacta y se envía de
  inmediato — no hay borradores. El envío queda registrado aquí con cuántos destinatarios tenía.
</p>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Asunto</th><th>Segmento</th><th>Enviados</th><th>Enviada por</th><th>Fecha</th></tr></thead>
  <tbody>
    <?php foreach ($campanas as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['asunto']) ?></td>
        <td><?= htmlspecialchars($segmentoLabel[$c['segmento']] ?? $c['segmento']) ?></td>
        <td><?= (int) $c['total_enviados'] ?> / <?= (int) $c['total_destinatarios'] ?></td>
        <td><?= htmlspecialchars((string) ($c['creado_por_username'] ?? '—')) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$campanas): ?><tr><td colspan="5" class="text-muted">No se ha enviado ninguna campaña todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
