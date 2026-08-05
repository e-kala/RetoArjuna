<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$page_title = 'Foro';
$categorias = foro_categorias();

$ordenesValidos = [
    'recientes' => 't.ultima_respuesta_at DESC, t.created_at DESC',
    'respondidas' => 't.respuestas_count DESC, t.ultima_respuesta_at DESC',
    'vistas' => 't.vistas DESC, t.ultima_respuesta_at DESC',
];
$orden = $_GET['orden'] ?? 'recientes';
if (!isset($ordenesValidos[$orden])) {
    $orden = 'recientes';
}

$temasRecientes = $conn->query(
    "SELECT t.id, t.titulo, t.slug, t.fijado, t.cerrado, t.respuestas_count, t.vistas,
            t.ultima_respuesta_at, t.created_at, u.username_cache, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
            cu.titulo AS curso_titulo
     FROM foro_temas t
     JOIN usuarios_perfil u ON u.id = t.usuario_id
     JOIN foro_categorias c ON c.id = t.categoria_id
     LEFT JOIN cursos cu ON cu.id = t.curso_id
     ORDER BY t.fijado DESC, {$ordenesValidos[$orden]}
     LIMIT 15"
)->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/inc/header.php';
?>

<div class="pf-forum-head">
  <div>
    <h1><i class="bi bi-chat-square-text-fill"></i> Foro de la comunidad</h1>
    <p>Conversa, pregunta y comparte con la comunidad del Reto Arjuna.</p>
  </div>
  <?php if (current_user()): ?>
    <a class="pf-btn pf-btn-primary" href="nuevo_tema.php"><i class="bi bi-plus-lg"></i> Nuevo tema</a>
  <?php endif; ?>
</div>

<form action="buscar.php" method="get" class="pf-forum-search-hero">
  <i class="bi bi-search"></i>
  <input type="search" name="q" placeholder="Buscar temas, respuestas, palabras clave…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
  <button type="submit" class="pf-btn pf-btn-primary">Buscar</button>
</form>

<div class="pf-forum-layout">
  <div class="pf-forum-main">
    <div class="pf-forum-filtros">
      <span class="pf-forum-filtros-label"><i class="bi bi-funnel-fill"></i> Ordenar:</span>
      <a class="pf-forum-filtro <?= $orden === 'recientes' ? 'activo' : '' ?>" href="?orden=recientes">Recientes</a>
      <a class="pf-forum-filtro <?= $orden === 'respondidas' ? 'activo' : '' ?>" href="?orden=respondidas">Más respondidas</a>
      <a class="pf-forum-filtro <?= $orden === 'vistas' ? 'activo' : '' ?>" href="?orden=vistas">Más vistas</a>
    </div>
    <div class="pf-forum-list">
      <?php foreach ($temasRecientes as $t): ?>
        <a class="pf-forum-tema-row" href="tema.php?id=<?= (int) $t['id'] ?>">
          <div>
            <div class="pf-forum-tema-titulo">
              <?php if ($t['fijado']): ?><span class="pf-forum-badge pf-forum-badge-fijado"><i class="bi bi-pin-angle-fill"></i> Fijado</span><?php endif; ?>
              <?php if ($t['cerrado']): ?><span class="pf-forum-badge pf-forum-badge-cerrado"><i class="bi bi-lock-fill"></i> Cerrado</span><?php endif; ?>
              <?= htmlspecialchars($t['titulo']) ?>
            </div>
            <div class="pf-forum-tema-meta">
              <i class="bi bi-folder2"></i> <?= htmlspecialchars($t['categoria_nombre']) ?>
              <?php if ($t['curso_titulo']): ?> · <i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($t['curso_titulo']) ?><?php endif; ?>
              · <i class="bi bi-person-fill"></i> <?= htmlspecialchars($t['username_cache']) ?>
              · <?= foro_tiempo_relativo($t['ultima_respuesta_at'] ?? $t['created_at']) ?>
            </div>
          </div>
          <div class="pf-forum-tema-stats">
            <div><i class="bi bi-chat-left-text"></i> <strong><?= (int) $t['respuestas_count'] ?></strong></div>
            <div><i class="bi bi-eye"></i> <strong><?= (int) $t['vistas'] ?></strong></div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$temasRecientes): ?>
        <p class="pf-forum-empty">Todavía no hay temas. ¡Sé el primero en publicar!</p>
      <?php endif; ?>
    </div>
  </div>

  <aside class="pf-forum-sidebar">
    <h2><i class="bi bi-folder2"></i> Categorías</h2>
    <div class="pf-forum-cat-lista">
      <?php foreach ($categorias as $cat): ?>
        <a class="pf-forum-cat-item" href="categoria.php?slug=<?= urlencode($cat['slug']) ?>">
          <span><i class="bi bi-chat-square-dots"></i> <?= htmlspecialchars($cat['nombre']) ?></span>
          <span class="pf-forum-cat-count"><?= (int) $cat['temas_count'] ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$categorias): ?>
        <p class="pf-forum-empty">Todavía no hay categorías.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
