<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$temaId = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT t.*, u.username_cache, u.avatar_cache, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
            cu.titulo AS curso_titulo, l.titulo AS leccion_titulo
     FROM foro_temas t
     JOIN usuarios_perfil u ON u.id = t.usuario_id
     JOIN foro_categorias c ON c.id = t.categoria_id
     LEFT JOIN cursos cu ON cu.id = t.curso_id
     LEFT JOIN lecciones l ON l.id = t.leccion_id
     WHERE t.id = ? LIMIT 1"
);
$stmt->bind_param('i', $temaId);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tema) {
    header('Location: index.php');
    exit;
}

// Cuenta la vista una sola vez por sesión de navegador para no inflar el contador.
if (empty($_SESSION['foro_vistas'][$temaId])) {
    $_SESSION['foro_vistas'][$temaId] = true;
    $conn->query('UPDATE foro_temas SET vistas = vistas + 1 WHERE id = ' . $temaId);
    $tema['vistas']++;
}

$stmt = $conn->prepare(
    "SELECT r.*, u.username_cache, u.avatar_cache
     FROM foro_respuestas r
     JOIN usuarios_perfil u ON u.id = r.usuario_id
     WHERE r.tema_id = ? ORDER BY r.created_at ASC"
);
$stmt->bind_param('i', $temaId);
$stmt->execute();
$respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$usuarioActual = current_user();
$esAdmin = $usuarioActual && $usuarioActual['rol'] === 'admin';
$page_title = $tema['titulo'];

require __DIR__ . '/inc/header.php';
?>

<p class="pf-forum-breadcrumb">
  <i class="bi bi-house-door"></i> <a href="index.php">Foro</a> / <a href="categoria.php?slug=<?= urlencode($tema['categoria_slug']) ?>"><?= htmlspecialchars($tema['categoria_nombre']) ?></a>
  <?php if ($tema['curso_titulo']): ?>
    / <a href="curso.php?curso_id=<?= (int) $tema['curso_id'] ?>"><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($tema['curso_titulo']) ?></a>
    <?php if ($tema['leccion_titulo']): ?>
      / <a href="curso.php?curso_id=<?= (int) $tema['curso_id'] ?>&leccion_id=<?= (int) $tema['leccion_id'] ?>"><?= htmlspecialchars($tema['leccion_titulo']) ?></a>
    <?php endif; ?>
  <?php endif; ?>
</p>

<div class="pf-forum-head">
  <div>
    <h1>
      <?php if ($tema['fijado']): ?><span class="pf-forum-badge pf-forum-badge-fijado"><i class="bi bi-pin-angle-fill"></i> Fijado</span><?php endif; ?>
      <?php if ($tema['cerrado']): ?><span class="pf-forum-badge pf-forum-badge-cerrado"><i class="bi bi-lock-fill"></i> Cerrado</span><?php endif; ?>
      <?= htmlspecialchars($tema['titulo']) ?>
    </h1>
  </div>
</div>

<div class="pf-forum-post" id="post-tema">
  <div class="pf-forum-post-head">
    <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $tema['username_cache'], 0, 1))) ?></span>
    <div><strong><?= htmlspecialchars($tema['username_cache']) ?></strong> · <?= foro_tiempo_relativo($tema['created_at']) ?></div>
  </div>
  <div class="pf-forum-post-body"><?= $tema['contenido'] ?></div>
  <div class="pf-forum-post-actions">
    <?php
      $likesTema = foro_contar_likes($tema['id'], null);
      $meGustaTema = $usuarioActual && foro_usuario_dio_like($usuarioActual['id'], $tema['id'], null);
    ?>
    <button class="pf-forum-like-btn <?= $meGustaTema ? 'activo' : '' ?>" data-tema-id="<?= (int) $tema['id'] ?>" <?= $usuarioActual ? '' : 'disabled' ?>>
      <i class="bi bi-hand-thumbs-up<?= $meGustaTema ? '-fill' : '' ?>"></i> <span class="likes-count"><?= $likesTema ?></span>
    </button>
    <?php if ($esAdmin): ?>
      <div class="pf-forum-mod-actions">
        <button data-accion="<?= $tema['fijado'] ? 'desfijar' : 'fijar' ?>"><i class="bi bi-pin-angle"></i> <?= $tema['fijado'] ? 'Quitar fijado' : 'Fijar' ?></button>
        <button data-accion="<?= $tema['cerrado'] ? 'reabrir' : 'cerrar' ?>"><i class="bi bi-lock"></i> <?= $tema['cerrado'] ? 'Reabrir' : 'Cerrar' ?></button>
        <button data-accion="eliminar" style="color:#b91c1c;"><i class="bi bi-trash"></i> Eliminar</button>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php foreach ($respuestas as $r): ?>
  <div class="pf-forum-post" id="respuesta-<?= (int) $r['id'] ?>">
    <div class="pf-forum-post-head">
      <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $r['username_cache'], 0, 1))) ?></span>
      <div><strong><?= htmlspecialchars($r['username_cache']) ?></strong> · <?= foro_tiempo_relativo($r['created_at']) ?></div>
    </div>
    <div class="pf-forum-post-body"><?= $r['contenido'] ?></div>
    <div class="pf-forum-post-actions">
      <?php
        $likesResp = foro_contar_likes(null, $r['id']);
        $meGustaResp = $usuarioActual && foro_usuario_dio_like($usuarioActual['id'], null, $r['id']);
      ?>
      <button class="pf-forum-like-btn <?= $meGustaResp ? 'activo' : '' ?>" data-respuesta-id="<?= (int) $r['id'] ?>" <?= $usuarioActual ? '' : 'disabled' ?>>
        <i class="bi bi-hand-thumbs-up<?= $meGustaResp ? '-fill' : '' ?>"></i> <span class="likes-count"><?= $likesResp ?></span>
      </button>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($tema['cerrado'] && !$esAdmin): ?>
  <p class="pf-forum-empty"><i class="bi bi-lock-fill"></i> Este tema está cerrado y ya no acepta respuestas.</p>
<?php elseif ($usuarioActual): ?>
  <div class="pf-forum-form" style="margin-top:24px;">
    <div id="responderError" class="alert alert-danger d-none" style="display:none;"></div>
    <form id="responderForm">
      <div class="campo">
        <label for="respuesta_contenido"><i class="bi bi-reply-fill"></i> Responder</label>
        <textarea id="respuesta_contenido" rows="5" placeholder="Escribe tu respuesta. Usa @usuario para mencionar a alguien." required></textarea>
      </div>
      <button type="submit" class="pf-btn pf-btn-primary">Publicar respuesta</button>
    </form>
  </div>
<?php else: ?>
  <p class="pf-forum-empty"><a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=ingreso">Inicia sesión</a> para responder.</p>
<?php endif; ?>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const TEMA_ID = <?= (int) $tema['id'] ?>;

document.querySelectorAll('.pf-forum-like-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (btn.disabled) return;
    const params = { csrf_token: CSRF_TOKEN };
    if (btn.dataset.temaId) params.tema_id = btn.dataset.temaId;
    if (btn.dataset.respuestaId) params.respuesta_id = btn.dataset.respuestaId;

    fetch('backend/like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(params)
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          btn.querySelector('.likes-count').textContent = data.likes;
          btn.classList.toggle('activo', data.activo);
        }
      });
  });
});

<?php if ($usuarioActual && (!$tema['cerrado'] || $esAdmin)): ?>
document.getElementById('responderForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const $error = document.getElementById('responderError');
  $error.style.display = 'none';

  fetch('backend/responder.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      tema_id: TEMA_ID,
      contenido: document.getElementById('respuesta_contenido').value,
      csrf_token: CSRF_TOKEN
    })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        $error.textContent = data.message || 'No se pudo publicar la respuesta.';
        $error.style.display = 'block';
      }
    })
    .catch(() => {
      $error.textContent = 'Error de conexión. Intenta de nuevo.';
      $error.style.display = 'block';
    });
});
<?php endif; ?>

<?php if ($esAdmin): ?>
document.querySelectorAll('.pf-forum-mod-actions button').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const accion = btn.dataset.accion;
    if (accion === 'eliminar' && !confirm('¿Eliminar este tema y todas sus respuestas? No se puede deshacer.')) return;

    fetch('backend/moderar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ tema_id: TEMA_ID, accion: accion, csrf_token: CSRF_TOKEN })
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          window.location.href = data.redirect || 'index.php';
        } else {
          alert(data.message || 'No se pudo completar la acción.');
        }
      });
  });
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
