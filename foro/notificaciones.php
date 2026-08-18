<?php
require_once __DIR__ . '/backend/foro_helpers.php';
require_login('index.php');

$usuarioActual = current_user();
$page_title = 'Notificaciones';

$stmt = $conn->prepare(
    "SELECT n.*, t.titulo AS tema_titulo, a.username_cache AS actor_username
     FROM foro_notificaciones n
     JOIN foro_temas t ON t.id = n.tema_id
     JOIN usuarios_perfil a ON a.id = n.actor_usuario_id
     WHERE n.usuario_id = ?
     ORDER BY n.created_at DESC LIMIT 50"
);
$stmt->bind_param('i', $usuarioActual['id']);
$stmt->execute();
$notificaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/inc/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <h1 class="h3 fw-bold mb-0"><i class="bi bi-bell-fill"></i> Notificaciones</h1>
  <?php if ($notificaciones): ?>
    <button class="btn btn-outline-secondary btn-sm" id="marcarTodasBtn"><i class="bi bi-check2-all"></i> Marcar todas como leídas</button>
  <?php endif; ?>
</div>

<div class="list-group shadow-sm">
  <?php foreach ($notificaciones as $n): ?>
    <a class="list-group-item list-group-item-action" style="<?= $n['leida'] ? '' : 'border-left:3px solid var(--pf-accent);background:#fffaf2;' ?>"
       href="tema.php?id=<?= (int) $n['tema_id'] ?><?= $n['respuesta_id'] ? '#respuesta-' . (int) $n['respuesta_id'] : '' ?>">
      <i class="bi <?= $n['tipo'] === 'mencion' ? 'bi-at' : 'bi-reply-fill' ?>"></i>
      <strong><?= htmlspecialchars($n['actor_username']) ?></strong>
      <?= $n['tipo'] === 'mencion' ? 'te mencionó en' : 'respondió a' ?>
      «<?= htmlspecialchars($n['tema_titulo']) ?>» · <span class="text-muted"><?= foro_tiempo_relativo($n['created_at']) ?></span>
    </a>
  <?php endforeach; ?>
  <?php if (!$notificaciones): ?>
    <div class="list-group-item text-center text-muted py-5"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i> No tienes notificaciones todavía.</div>
  <?php endif; ?>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const marcarTodasBtn = document.getElementById('marcarTodasBtn');
if (marcarTodasBtn) {
  marcarTodasBtn.addEventListener('click', function () {
    fetch('backend/marcar_leida.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ todas: '1', csrf_token: CSRF_TOKEN })
    }).then(() => window.location.reload());
  });
}
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
