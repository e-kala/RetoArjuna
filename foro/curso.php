<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$cursoId = (int) ($_GET['curso_id'] ?? 0);
$leccionId = (int) ($_GET['leccion_id'] ?? 0);

$stmt = $conn->prepare('SELECT id, titulo, slug FROM cursos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso) {
    header('Location: index.php');
    exit;
}

$leccion = null;
if ($leccionId) {
    $stmt = $conn->prepare('SELECT id, titulo FROM lecciones WHERE id = ? AND curso_id = ? LIMIT 1');
    $stmt->bind_param('ii', $leccionId, $cursoId);
    $stmt->execute();
    $leccion = $stmt->get_result()->fetch_assoc();
    if (!$leccion) {
        $leccionId = 0;
    }
}

$page_title = $leccion ? $leccion['titulo'] : $curso['titulo'];

$stmt = $conn->prepare('SELECT id, titulo FROM lecciones WHERE curso_id = ? ORDER BY orden');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($leccionId) {
    $stmt = $conn->prepare(
        "SELECT t.id, t.titulo, t.fijado, t.cerrado, t.respuestas_count, t.vistas,
                t.ultima_respuesta_at, t.created_at, u.username_cache
         FROM foro_temas t
         JOIN usuarios_perfil u ON u.id = t.usuario_id
         WHERE t.curso_id = ? AND t.leccion_id = ?
         ORDER BY t.fijado DESC, t.ultima_respuesta_at DESC, t.created_at DESC"
    );
    $stmt->bind_param('ii', $cursoId, $leccionId);
} else {
    $stmt = $conn->prepare(
        "SELECT t.id, t.titulo, t.fijado, t.cerrado, t.respuestas_count, t.vistas,
                t.ultima_respuesta_at, t.created_at, u.username_cache
         FROM foro_temas t
         JOIN usuarios_perfil u ON u.id = t.usuario_id
         WHERE t.curso_id = ?
         ORDER BY t.fijado DESC, t.ultima_respuesta_at DESC, t.created_at DESC"
    );
    $stmt->bind_param('i', $cursoId);
}
$stmt->execute();
$temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$nuevoTemaUrl = 'nuevo_tema.php?curso_id=' . $cursoId . ($leccionId ? '&leccion_id=' . $leccionId : '');

require __DIR__ . '/inc/header.php';
?>

<p class="pf-forum-breadcrumb">
  <i class="bi bi-house-door"></i> <a href="index.php">Foro</a> /
  <a href="curso.php?curso_id=<?= (int) $curso['id'] ?>"><?= htmlspecialchars($curso['titulo']) ?></a>
  <?php if ($leccion): ?> / <?= htmlspecialchars($leccion['titulo']) ?><?php endif; ?>
</p>

<div class="pf-forum-head">
  <div>
    <h1><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($leccion ? $leccion['titulo'] : $curso['titulo']) ?></h1>
    <p>
      <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=curso&slug=<?= urlencode($curso['slug']) ?>">Ver el curso</a>
      <?php if ($leccion): ?> · <a href="curso.php?curso_id=<?= (int) $curso['id'] ?>">Ver todos los temas del curso</a><?php endif; ?>
    </p>
  </div>
  <?php if (current_user()): ?>
    <a class="pf-btn pf-btn-primary" href="<?= htmlspecialchars($nuevoTemaUrl) ?>"><i class="bi bi-plus-lg"></i> Nuevo tema</a>
  <?php endif; ?>
</div>

<div class="pf-forum-layout">
  <div class="pf-forum-main">
    <div class="pf-forum-list">
      <?php foreach ($temas as $t): ?>
        <a class="pf-forum-tema-row" href="tema.php?id=<?= (int) $t['id'] ?>">
          <div>
            <div class="pf-forum-tema-titulo">
              <?php if ($t['fijado']): ?><span class="pf-forum-badge pf-forum-badge-fijado"><i class="bi bi-pin-angle-fill"></i> Fijado</span><?php endif; ?>
              <?php if ($t['cerrado']): ?><span class="pf-forum-badge pf-forum-badge-cerrado"><i class="bi bi-lock-fill"></i> Cerrado</span><?php endif; ?>
              <?= htmlspecialchars($t['titulo']) ?>
            </div>
            <div class="pf-forum-tema-meta">
              <i class="bi bi-person-fill"></i> <?= htmlspecialchars($t['username_cache']) ?> · <?= foro_tiempo_relativo($t['ultima_respuesta_at'] ?? $t['created_at']) ?>
            </div>
          </div>
          <div class="pf-forum-tema-stats">
            <div><i class="bi bi-chat-left-text"></i> <strong><?= (int) $t['respuestas_count'] ?></strong></div>
            <div><i class="bi bi-eye"></i> <strong><?= (int) $t['vistas'] ?></strong></div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$temas): ?>
        <p class="pf-forum-empty">Todavía no hay temas <?= $leccion ? 'sobre esta lección' : 'sobre este curso' ?>. ¡Sé el primero en publicar!</p>
      <?php endif; ?>
    </div>
  </div>

  <aside class="pf-forum-sidebar">
    <h2><i class="bi bi-list-ol"></i> Lecciones</h2>
    <div class="pf-forum-cat-lista">
      <a class="pf-forum-cat-item <?= !$leccionId ? 'activo' : '' ?>" href="curso.php?curso_id=<?= (int) $curso['id'] ?>">
        <span>Todos los temas</span>
      </a>
      <?php foreach ($lecciones as $l): ?>
        <a class="pf-forum-cat-item <?= $leccionId === (int) $l['id'] ? 'activo' : '' ?>" href="curso.php?curso_id=<?= (int) $curso['id'] ?>&leccion_id=<?= (int) $l['id'] ?>">
          <span><?= htmlspecialchars($l['titulo']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
