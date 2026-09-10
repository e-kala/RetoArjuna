<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$q = trim($_GET['q'] ?? '');
$page_title = 'Buscar: ' . $q;
$usuarioActual = current_user();
$visSql = foro_visibilidad_sql();
[$uid1, $uid2, $esAdmin, $esAdmin2] = foro_visibilidad_binds($usuarioActual);
$ocultoFiltro = foro_oculto_filtro_admin($usuarioActual);
$ocultoSql = foro_oculto_filtro_sql($ocultoFiltro);

$temas = [];
$respuestas = [];

if ($q !== '') {
    $etiquetasResumenSql = foro_etiquetas_resumen_sql('t');
    $stmt = $conn->prepare(
        "SELECT t.id, t.usuario_id, t.titulo, t.contenido, t.oculto, t.created_at, u.username_cache, $etiquetasResumenSql AS etiquetas_nombres
         FROM foro_temas t
         JOIN usuarios_perfil u ON u.id = t.usuario_id
         WHERE MATCH(t.titulo, t.contenido) AGAINST (? IN NATURAL LANGUAGE MODE) AND $visSql$ocultoSql
         ORDER BY t.created_at DESC LIMIT 20"
    );
    $stmt->bind_param('siiii', $q, $uid1, $uid2, $esAdmin, $esAdmin2);
    $stmt->execute();
    $temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT r.id, r.usuario_id, r.tema_id, r.contenido, r.created_at, u.username_cache, t.titulo AS tema_titulo, t.oculto
         FROM foro_respuestas r
         JOIN usuarios_perfil u ON u.id = r.usuario_id
         JOIN foro_temas t ON t.id = r.tema_id
         WHERE MATCH(r.contenido) AGAINST (? IN NATURAL LANGUAGE MODE) AND $visSql$ocultoSql
         ORDER BY r.created_at DESC LIMIT 20"
    );
    $stmt->bind_param('siiii', $q, $uid1, $uid2, $esAdmin, $esAdmin2);
    $stmt->execute();
    $respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

require __DIR__ . '/inc/header.php';
?>

<div class="mb-4 d-flex flex-wrap align-items-end justify-content-between gap-3">
  <div>
    <h1 class="h3 fw-bold mb-1">Resultados para «<?= htmlspecialchars($q) ?>»</h1>
    <p class="text-muted mb-0"><?= count($temas) + count($respuestas) ?> resultado(s)</p>
  </div>
  <?php if ($usuarioActual && $usuarioActual['rol'] === 'admin'): ?>
    <form method="get">
      <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
      <select name="oculto" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
        <option value="">Ocultos y publicados</option>
        <option value="publicados" <?= $ocultoFiltro === 'publicados' ? 'selected' : '' ?>>Solo publicados</option>
        <option value="ocultos" <?= $ocultoFiltro === 'ocultos' ? 'selected' : '' ?>>Solo ocultos</option>
      </select>
    </form>
  <?php endif; ?>
</div>

<?php if ($q === ''): ?>
  <div class="text-center text-muted py-5"><i class="bi bi-search fs-2 d-block mb-2"></i>Escribe algo en el buscador para empezar.</div>
<?php else: ?>
  <?php if ($temas): ?>
    <h2 class="h6 fw-bold mb-3">Temas</h2>
    <div class="list-group shadow-sm mb-4">
      <?php foreach ($temas as $t): ?>
        <a class="list-group-item list-group-item-action" href="tema.php?id=<?= (int) $t['id'] ?>">
          <div class="fw-bold">
            <?php if (!empty($t['oculto'])): ?><span class="badge rounded-pill text-bg-dark me-1"><i class="bi bi-eye-slash-fill"></i> Oculto</span><?php endif; ?>
            <?= htmlspecialchars($t['titulo']) ?>
          </div>
          <div class="text-muted small mt-1">
            <?php if ($t['etiquetas_nombres']): ?>en <?= htmlspecialchars($t['etiquetas_nombres']) ?> · <?php endif; ?>por <?= perfil_link((int) $t['usuario_id'], (string) $t['username_cache']) ?>
            · <?= foro_tiempo_relativo($t['created_at']) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($respuestas): ?>
    <h2 class="h6 fw-bold mb-3">Respuestas</h2>
    <div class="list-group shadow-sm">
      <?php foreach ($respuestas as $r): ?>
        <a class="list-group-item list-group-item-action" href="tema.php?id=<?= (int) $r['tema_id'] ?>#respuesta-<?= (int) $r['id'] ?>">
          <div class="fw-bold">
            <?php if (!empty($r['oculto'])): ?><span class="badge rounded-pill text-bg-dark me-1"><i class="bi bi-eye-slash-fill"></i> Oculto</span><?php endif; ?>
            en «<?= htmlspecialchars($r['tema_titulo']) ?>»
          </div>
          <div class="text-muted small mt-1">
            por <?= perfil_link((int) $r['usuario_id'], (string) $r['username_cache']) ?> · <?= foro_tiempo_relativo($r['created_at']) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$temas && !$respuestas): ?>
    <div class="text-center text-muted py-5"><i class="bi bi-search fs-2 d-block mb-2"></i>No se encontró nada con esa búsqueda.</div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
