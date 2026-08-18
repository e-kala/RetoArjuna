<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$slug = trim($_GET['slug'] ?? '');
$categoria = $slug !== '' ? foro_categoria_por_slug($slug) : null;
if (!$categoria) {
    header('Location: index.php');
    exit;
}

$page_title = $categoria['nombre'];
$ruta = foro_categoria_ruta((int) $categoria['id']);
$subcategorias = $conn->prepare('SELECT c.*, (SELECT COUNT(*) FROM foro_temas t WHERE t.categoria_id = c.id) AS temas_count FROM foro_categorias c WHERE c.parent_id = ? ORDER BY c.orden ASC, c.nombre ASC');
$subcategorias->bind_param('i', $categoria['id']);
$subcategorias->execute();
$subcategorias = $subcategorias->get_result()->fetch_all(MYSQLI_ASSOC);

$ordenesValidos = [
    'recientes' => 't.ultima_respuesta_at DESC, t.created_at DESC',
    'respondidas' => 't.respuestas_count DESC, t.ultima_respuesta_at DESC',
    'vistas' => 't.vistas DESC, t.ultima_respuesta_at DESC',
];
$orden = $_GET['orden'] ?? 'recientes';
if (!isset($ordenesValidos[$orden])) {
    $orden = 'recientes';
}

$porPagina = 20;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$stmtTotal = $conn->prepare('SELECT COUNT(*) AS n FROM foro_temas t WHERE t.categoria_id = ?');
$stmtTotal->bind_param('i', $categoria['id']);
$stmtTotal->execute();
$totalTemas = (int) $stmtTotal->get_result()->fetch_assoc()['n'];
$stmtTotal->close();

$totalPaginas = max(1, (int) ceil($totalTemas / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$stmt = $conn->prepare(
    "SELECT t.id, t.titulo, t.fijado, t.cerrado, t.respuestas_count, t.vistas,
            t.ultima_respuesta_at, t.created_at, u.username_cache
     FROM foro_temas t
     JOIN usuarios_perfil u ON u.id = t.usuario_id
     WHERE t.categoria_id = ?
     ORDER BY t.fijado DESC, {$ordenesValidos[$orden]}
     LIMIT ? OFFSET ?"
);
$stmt->bind_param('iii', $categoria['id'], $porPagina, $offset);
$stmt->execute();
$temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Para armar los links de "Ordenar"/paginación conservando el slug y el orden activo.
$queryBase = array_filter(['slug' => $slug, 'orden' => $orden !== 'recientes' ? $orden : null]);

require __DIR__ . '/inc/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php">Foro</a></li>
    <?php foreach ($ruta as $nodo): ?>
      <?php if ((int) $nodo['id'] === (int) $categoria['id']): ?>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($nodo['nombre']) ?></li>
      <?php else: ?>
        <li class="breadcrumb-item"><a href="categoria.php?slug=<?= urlencode($nodo['slug']) ?>"><?= htmlspecialchars($nodo['nombre']) ?></a></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
</nav>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-folder2-open"></i> <?= htmlspecialchars($categoria['nombre']) ?></h1>
    <?php if ($categoria['descripcion']): ?><p class="text-muted mb-0"><?= htmlspecialchars($categoria['descripcion']) ?></p><?php endif; ?>
  </div>
  <?php if (current_user()): ?>
    <a class="btn fw-bold" style="background:var(--pf-accent);color:#fff;" href="nuevo_tema.php?categoria_id=<?= (int) $categoria['id'] ?>"><i class="bi bi-plus-lg"></i> Nuevo tema</a>
  <?php endif; ?>
</div>

<?php if ($subcategorias): ?>
  <div class="list-group mb-4">
    <?php foreach ($subcategorias as $sub): ?>
      <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="categoria.php?slug=<?= urlencode($sub['slug']) ?>">
        <span><i class="bi bi-chevron-right"></i> <?= htmlspecialchars($sub['nombre']) ?></span>
        <span class="badge text-bg-light text-muted rounded-pill"><?= (int) $sub['temas_count'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <span class="text-muted small fw-bold text-uppercase"><i class="bi bi-funnel-fill"></i> Ordenar</span>
  <div class="btn-group btn-group-sm" role="group">
    <a class="btn btn-outline-secondary <?= $orden === 'recientes' ? 'active' : '' ?>" href="?slug=<?= urlencode($slug) ?>&orden=recientes">Recientes</a>
    <a class="btn btn-outline-secondary <?= $orden === 'respondidas' ? 'active' : '' ?>" href="?slug=<?= urlencode($slug) ?>&orden=respondidas">Más respondidas</a>
    <a class="btn btn-outline-secondary <?= $orden === 'vistas' ? 'active' : '' ?>" href="?slug=<?= urlencode($slug) ?>&orden=vistas">Más vistas</a>
  </div>
</div>

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
      Todavía no hay temas en esta categoría. ¡Sé el primero en publicar!
    </div>
  <?php endif; ?>
</div>

<?php $paginaActual = $pagina; require __DIR__ . '/inc/paginacion.php'; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
