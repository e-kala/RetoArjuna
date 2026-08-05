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

<div class="pf-forum-head">
  <h1><i class="bi bi-bell-fill"></i> Notificaciones</h1>
  <?php if ($notificaciones): ?>
    <button class="pf-btn pf-btn-outline" id="marcarTodasBtn"><i class="bi bi-check2-all"></i> Marcar todas como leídas</button>
  <?php endif; ?>
</div>

<div class="pf-forum-notif-list">
  <?php foreach ($notificaciones as $n): ?>
    <div class="pf-forum-notif-row <?= $n['leida'] ? '' : 'no-leida' ?>">
      <a href="tema.php?id=<?= (int) $n['tema_id'] ?><?= $n['respuesta_id'] ? '#respuesta-' . (int) $n['respuesta_id'] : '' ?>">
        <i class="bi <?= $n['tipo'] === 'mencion' ? 'bi-at' : 'bi-reply-fill' ?>"></i>
        <strong><?= htmlspecialchars($n['actor_username']) ?></strong>
        <?= $n['tipo'] === 'mencion' ? 'te mencionó en' : 'respondió a' ?>
        «<?= htmlspecialchars($n['tema_titulo']) ?>» · <?= foro_tiempo_relativo($n['created_at']) ?>
      </a>
    </div>
  <?php endforeach; ?>
  <?php if (!$notificaciones): ?>
    <p class="pf-forum-empty"><i class="bi bi-bell-slash"></i> No tienes notificaciones todavía.</p>
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
