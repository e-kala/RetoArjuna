<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$page_title = 'Foro';
$categorias = foro_categorias_arbol_plano(foro_categorias_arbol());

$ordenesValidos = [
    'recientes' => 't.ultima_respuesta_at DESC, t.created_at DESC',
    'respondidas' => 't.respuestas_count DESC, t.ultima_respuesta_at DESC',
    'vistas' => 't.vistas DESC, t.ultima_respuesta_at DESC',
];
$orden = $_GET['orden'] ?? 'recientes';
if (!isset($ordenesValidos[$orden])) {
    $orden = 'recientes';
}

$categoriaFiltro = (int) ($_GET['categoria'] ?? 0);
// Confirma que la categoría elegida en el filtro realmente exista, para no
// armar un WHERE con un id inventado.
if ($categoriaFiltro && !in_array($categoriaFiltro, array_map('intval', array_column($categorias, 'id')), true)) {
    $categoriaFiltro = 0;
}

$porPagina = 20;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$whereFiltro = $categoriaFiltro ? 'WHERE t.categoria_id = ?' : '';

$stmtTotal = $conn->prepare("SELECT COUNT(*) AS n FROM foro_temas t $whereFiltro");
if ($categoriaFiltro) {
    $stmtTotal->bind_param('i', $categoriaFiltro);
}
$stmtTotal->execute();
$totalTemas = (int) $stmtTotal->get_result()->fetch_assoc()['n'];
$stmtTotal->close();

$totalPaginas = max(1, (int) ceil($totalTemas / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$sql = "SELECT t.id, t.titulo, t.slug, t.fijado, t.cerrado, t.respuestas_count, t.vistas,
               t.ultima_respuesta_at, t.created_at, u.username_cache, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
               cu.titulo AS curso_titulo
        FROM foro_temas t
        JOIN usuarios_perfil u ON u.id = t.usuario_id
        JOIN foro_categorias c ON c.id = t.categoria_id
        LEFT JOIN cursos cu ON cu.id = t.curso_id
        $whereFiltro
        ORDER BY t.fijado DESC, {$ordenesValidos[$orden]}
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($categoriaFiltro) {
    $stmt->bind_param('iii', $categoriaFiltro, $porPagina, $offset);
} else {
    $stmt->bind_param('ii', $porPagina, $offset);
}
$stmt->execute();
$temasRecientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Para armar los links de "Ordenar"/paginación conservando el filtro activo.
$queryBase = array_filter(['orden' => $orden !== 'recientes' ? $orden : null, 'categoria' => $categoriaFiltro ?: null]);

require __DIR__ . '/inc/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-chat-square-text-fill"></i> Foro de la comunidad</h1>
    <p class="text-muted mb-0">Conversa, pregunta y comparte con la comunidad del Reto Arjuna.</p>
  </div>
  <?php if (current_user()): ?>
    <a class="btn fw-bold" style="background:var(--pf-accent);color:#fff;" href="nuevo_tema.php"><i class="bi bi-plus-lg"></i> Nuevo tema</a>
  <?php endif; ?>
</div>

<form action="buscar.php" method="get" class="input-group input-group-lg shadow-sm mb-4">
  <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
  <input type="search" name="q" class="form-control border-start-0 ps-0" placeholder="Buscar temas, respuestas, palabras clave…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
  <button type="submit" class="btn fw-bold" style="background:var(--pf-accent);color:#fff;">Buscar</button>
</form>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span class="text-muted small fw-bold text-uppercase"><i class="bi bi-funnel-fill"></i> Ordenar</span>
      <div class="btn-group btn-group-sm" role="group">
        <a class="btn btn-outline-secondary <?= $orden === 'recientes' ? 'active' : '' ?>" href="?<?= http_build_query(array_filter(['categoria' => $categoriaFiltro ?: null]) + ['orden' => 'recientes']) ?>">Recientes</a>
        <a class="btn btn-outline-secondary <?= $orden === 'respondidas' ? 'active' : '' ?>" href="?<?= http_build_query(array_filter(['categoria' => $categoriaFiltro ?: null]) + ['orden' => 'respondidas']) ?>">Más respondidas</a>
        <a class="btn btn-outline-secondary <?= $orden === 'vistas' ? 'active' : '' ?>" href="?<?= http_build_query(array_filter(['categoria' => $categoriaFiltro ?: null]) + ['orden' => 'vistas']) ?>">Más vistas</a>
      </div>

      <form method="get" class="ms-auto" id="formFiltroCategoria" style="min-width:220px;">
        <?php if ($orden !== 'recientes'): ?><input type="hidden" name="orden" value="<?= htmlspecialchars($orden) ?>"><?php endif; ?>
        <select name="categoria" id="selectFiltroCategoria" class="form-select form-select-sm">
          <option value="">Todas las categorías</option>
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>" <?= $categoriaFiltro === (int) $cat['id'] ? 'selected' : '' ?>>
              <?= str_repeat('— ', (int) $cat['nivel']) ?><?= htmlspecialchars($cat['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="list-group shadow-sm">
      <?php foreach ($temasRecientes as $t): ?>
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
              <i class="bi bi-folder2"></i> <?= htmlspecialchars($t['categoria_nombre']) ?>
              <?php if ($t['curso_titulo']): ?> · <i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($t['curso_titulo']) ?><?php endif; ?>
              · <i class="bi bi-person-fill"></i> <?= htmlspecialchars($t['username_cache']) ?>
              · <?= foro_tiempo_relativo($t['ultima_respuesta_at'] ?? $t['created_at']) ?>
            </div>
          </div>
          <div class="d-flex gap-3 text-muted small text-center flex-shrink-0">
            <div><i class="bi bi-chat-left-text"></i> <strong class="text-dark d-block"><?= (int) $t['respuestas_count'] ?></strong></div>
            <div><i class="bi bi-eye"></i> <strong class="text-dark d-block"><?= (int) $t['vistas'] ?></strong></div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$temasRecientes): ?>
        <div class="list-group-item text-center text-muted py-5">
          <i class="bi bi-chat-square-dots fs-2 d-block mb-2"></i>
          <?= $categoriaFiltro ? 'No hay temas en esta categoría.' : 'Todavía no hay temas. ¡Sé el primero en publicar!' ?>
        </div>
      <?php endif; ?>
    </div>
    <?php $paginaActual = $pagina; require __DIR__ . '/inc/paginacion.php'; ?>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-folder2"></i> Categorías</h2>
        <div class="list-group list-group-flush">
          <?php foreach ($categorias as $cat): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2" style="padding-left:<?= 8 + ((int) $cat['nivel'] * 16) ?>px !important;" href="categoria.php?slug=<?= urlencode($cat['slug']) ?>">
              <span><i class="bi <?= $cat['nivel'] > 0 ? 'bi-chevron-right' : 'bi-chat-square-dots' ?>"></i> <?= htmlspecialchars($cat['nombre']) ?></span>
              <span class="badge text-bg-light text-muted rounded-pill"><?= (int) $cat['temas_count'] ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (!$categorias): ?>
            <p class="text-muted text-center py-3 mb-0">Todavía no hay categorías.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('selectFiltroCategoria').addEventListener('change', function () {
  document.getElementById('formFiltroCategoria').submit();
});
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
