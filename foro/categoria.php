<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$slug = trim($_GET['slug'] ?? '');
$categoria = $slug !== '' ? foro_categoria_por_slug($slug) : null;
if (!$categoria) {
    header('Location: index.php');
    exit;
}

$page_title = $categoria['nombre'];

$ordenesValidos = [
    'recientes' => 't.ultima_respuesta_at DESC, t.created_at DESC',
    'respondidas' => 't.respuestas_count DESC, t.ultima_respuesta_at DESC',
    'vistas' => 't.vistas DESC, t.ultima_respuesta_at DESC',
];
$orden = $_GET['orden'] ?? 'recientes';
if (!isset($ordenesValidos[$orden])) {
    $orden = 'recientes';
}

$stmt = $conn->prepare(
    "SELECT t.id, t.titulo, t.fijado, t.cerrado, t.respuestas_count, t.vistas,
            t.ultima_respuesta_at, t.created_at, u.username_cache
     FROM foro_temas t
     JOIN usuarios_perfil u ON u.id = t.usuario_id
     WHERE t.categoria_id = ?
     ORDER BY t.fijado DESC, {$ordenesValidos[$orden]}"
);
$stmt->bind_param('i', $categoria['id']);
$stmt->execute();
$temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/inc/header.php';
?>

<p class="pf-forum-breadcrumb"><a href="index.php">Foro</a> / <?= htmlspecialchars($categoria['nombre']) ?></p>

<div class="pf-forum-head">
  <div>
    <h1><i class="bi bi-folder2-open"></i> <?= htmlspecialchars($categoria['nombre']) ?></h1>
    <?php if ($categoria['descripcion']): ?><p><?= htmlspecialchars($categoria['descripcion']) ?></p><?php endif; ?>
  </div>
  <?php if (current_user()): ?>
    <a class="pf-btn pf-btn-primary" href="nuevo_tema.php?categoria_id=<?= (int) $categoria['id'] ?>"><i class="bi bi-plus-lg"></i> Nuevo tema</a>
  <?php endif; ?>
</div>

<div class="pf-forum-filtros">
  <span class="pf-forum-filtros-label"><i class="bi bi-funnel-fill"></i> Ordenar:</span>
  <a class="pf-forum-filtro <?= $orden === 'recientes' ? 'activo' : '' ?>" href="?slug=<?= urlencode($slug) ?>&orden=recientes">Recientes</a>
  <a class="pf-forum-filtro <?= $orden === 'respondidas' ? 'activo' : '' ?>" href="?slug=<?= urlencode($slug) ?>&orden=respondidas">Más respondidas</a>
  <a class="pf-forum-filtro <?= $orden === 'vistas' ? 'activo' : '' ?>" href="?slug=<?= urlencode($slug) ?>&orden=vistas">Más vistas</a>
</div>

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
    <p class="pf-forum-empty">Todavía no hay temas en esta categoría. ¡Sé el primero en publicar!</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
