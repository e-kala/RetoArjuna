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

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php">Foro</a></li>
    <li class="breadcrumb-item"><a href="curso.php?curso_id=<?= (int) $curso['id'] ?>"><?= htmlspecialchars($curso['titulo']) ?></a></li>
    <?php if ($leccion): ?><li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($leccion['titulo']) ?></li><?php endif; ?>
  </ol>
</nav>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($leccion ? $leccion['titulo'] : $curso['titulo']) ?></h1>
    <p class="text-muted mb-0">
      <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=curso&slug=<?= urlencode($curso['slug']) ?>">Ver el curso</a>
      <?php if ($leccion): ?> · <a href="curso.php?curso_id=<?= (int) $curso['id'] ?>">Ver todos los temas del curso</a><?php endif; ?>
    </p>
  </div>
  <?php if (current_user()): ?>
    <a class="btn fw-bold" style="background:var(--pf-accent);color:#fff;" href="<?= htmlspecialchars($nuevoTemaUrl) ?>"><i class="bi bi-plus-lg"></i> Nuevo tema</a>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="list-group shadow-sm">
      <?php foreach ($temas as $t): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3 py-3"
           style="<?= $t['fijado'] ? 'border-left:3px solid var(--pf-accent);background:#fffaf2;' : '' ?>"
           href="tema.php?id=<?= (int) $t['id'] ?>">
          <div>
            <div class="fw-bold">
              <?php if ($t['fijado']): ?><span class="badge rounded-pill me-1" style="background:#fff3e0;color:var(--pf-accent-ink);"><i class="bi bi-pin-angle-fill"></i> Fijado</span><?php endif; ?>
              <?php if ($t['cerrado']): ?><span class="badge rounded-pill text-bg-secondary me-1"><i class="bi bi-lock-fill"></i> Cerrado</span><?php endif; ?>
              <?= htmlspecialchars($t['titulo']) ?>
            </div>
            <div class="text-muted small mt-1">
              <i class="bi bi-person-fill"></i> <?= htmlspecialchars($t['username_cache']) ?> · <?= foro_tiempo_relativo($t['ultima_respuesta_at'] ?? $t['created_at']) ?>
            </div>
          </div>
          <div class="d-flex gap-3 text-muted small text-center flex-shrink-0">
            <div><i class="bi bi-chat-left-text"></i> <strong class="text-dark d-block"><?= (int) $t['respuestas_count'] ?></strong></div>
            <div><i class="bi bi-eye"></i> <strong class="text-dark d-block"><?= (int) $t['vistas'] ?></strong></div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$temas): ?>
        <div class="list-group-item text-center text-muted py-5">
          <i class="bi bi-chat-square-dots fs-2 d-block mb-2"></i>
          Todavía no hay temas <?= $leccion ? 'sobre esta lección' : 'sobre este curso' ?>. ¡Sé el primero en publicar!
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-list-ol"></i> Lecciones</h2>
        <div class="list-group list-group-flush">
          <a class="list-group-item list-group-item-action <?= !$leccionId ? 'active' : '' ?>" href="curso.php?curso_id=<?= (int) $curso['id'] ?>">
            Todos los temas
          </a>
          <?php foreach ($lecciones as $l): ?>
            <a class="list-group-item list-group-item-action <?= $leccionId === (int) $l['id'] ? 'active' : '' ?>" href="curso.php?curso_id=<?= (int) $curso['id'] ?>&leccion_id=<?= (int) $l['id'] ?>">
              <?= htmlspecialchars($l['titulo']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
