<?php
require_once __DIR__ . '/backend/foro_helpers.php';

// Sin tarea programada (cron) real en el proyecto — se dispara una vez por
// sesión de navegador con el tráfico normal del foro, para que la papelera
// de respuestas (ver backend/moderar.php) sí se vacíe sola a los 15 días
// aunque ningún admin entre a revisarla.
if (empty($_SESSION['foro_papelera_purgada'])) {
    $_SESSION['foro_papelera_purgada'] = true;
    foro_purgar_papelera_vencida();
}

$page_title = 'Foro';
$usuarioActual = current_user();
$etiquetas = foro_categorias_arbol_plano(foro_categorias_arbol($usuarioActual));
$cursosConTemas = $conn->query(
    "SELECT DISTINCT cu.id, cu.titulo FROM cursos cu JOIN foro_temas t ON t.curso_id = cu.id ORDER BY cu.titulo ASC"
)->fetch_all(MYSQLI_ASSOC);
$eventosConTemas = $conn->query(
    "SELECT DISTINCT ev.id, ev.titulo FROM eventos ev JOIN foro_temas t ON t.evento_id = ev.id ORDER BY ev.titulo ASC"
)->fetch_all(MYSQLI_ASSOC);
$categoriasLibres = foro_categorias_libres_todas();

$orden = foro_orden_valido($_GET['orden'] ?? null);

$etiquetaFiltro = (int) ($_GET['etiqueta'] ?? 0);
// Confirma que la etiqueta elegida en el filtro realmente exista, para no
// armar un WHERE con un id inventado.
if ($etiquetaFiltro && !in_array($etiquetaFiltro, array_map('intval', array_column($etiquetas, 'id')), true)) {
    $etiquetaFiltro = 0;
}

$porPagina = 20;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$condicionesFiltro = [];
$paramsFiltro = [];
$tiposFiltro = '';
if ($etiquetaFiltro) {
    $condicionesFiltro[] = 'EXISTS (SELECT 1 FROM foro_tema_etiquetas te WHERE te.tema_id = t.id AND te.categoria_id = ?)';
    $paramsFiltro[] = $etiquetaFiltro;
    $tiposFiltro .= 'i';
}
$condicionesFiltro[] = foro_visibilidad_sql();
$paramsFiltro = array_merge($paramsFiltro, foro_visibilidad_binds($usuarioActual));
$tiposFiltro .= 'iiii';
$ocultoFiltro = foro_oculto_filtro_admin($usuarioActual);
$whereFiltro = 'WHERE ' . implode(' AND ', $condicionesFiltro) . foro_oculto_filtro_sql($ocultoFiltro);

$stmtTotal = $conn->prepare("SELECT COUNT(*) AS n FROM foro_temas t $whereFiltro");
$stmtTotal->bind_param($tiposFiltro, ...$paramsFiltro);
$stmtTotal->execute();
$totalTemas = (int) $stmtTotal->get_result()->fetch_assoc()['n'];
$stmtTotal->close();

$totalPaginas = max(1, (int) ceil($totalTemas / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$etiquetasResumenSql = foro_etiquetas_resumen_sql('t');
$sql = "SELECT t.id, t.usuario_id, t.titulo, t.slug, t.fijado, t.cerrado, t.oculto, t.respuestas_count, t.vistas,
               t.ultima_respuesta_at, t.created_at, u.username_cache,
               $etiquetasResumenSql AS etiquetas_nombres,
               cu.titulo AS curso_titulo, ev.titulo AS evento_titulo
        FROM foro_temas t
        JOIN usuarios_perfil u ON u.id = t.usuario_id
        LEFT JOIN cursos cu ON cu.id = t.curso_id
        LEFT JOIN eventos ev ON ev.id = t.evento_id
        $whereFiltro
        ORDER BY t.fijado DESC, " . foro_orden_sql($orden) . "
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($tiposFiltro . 'ii', ...array_merge($paramsFiltro, [$porPagina, $offset]));
$stmt->execute();
$temasRecientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Para armar los links de "Ordenar"/paginación conservando el filtro activo.
$queryBase = array_filter(['orden' => $orden !== 'recientes' ? $orden : null, 'etiqueta' => $etiquetaFiltro ?: null, 'oculto' => $ocultoFiltro ?: null]);

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
      <?php $ordenCamposOcultos = ['etiqueta' => $etiquetaFiltro ?: null]; require __DIR__ . '/inc/orden_selector.php'; ?>

      <form method="get" class="ms-auto d-flex gap-2" id="formFiltroCategoria" style="min-width:220px;">
        <?php if ($orden !== 'recientes'): ?><input type="hidden" name="orden" value="<?= htmlspecialchars($orden) ?>"><?php endif; ?>
        <?php if ($usuarioActual && $usuarioActual['rol'] === 'admin'): ?>
          <select name="oculto" id="selectFiltroOculto" class="form-select form-select-sm" style="width:auto;">
            <option value="">Ocultos y publicados</option>
            <option value="publicados" <?= $ocultoFiltro === 'publicados' ? 'selected' : '' ?>>Solo publicados</option>
            <option value="ocultos" <?= $ocultoFiltro === 'ocultos' ? 'selected' : '' ?>>Solo ocultos</option>
          </select>
        <?php endif; ?>
        <select name="etiqueta" id="selectFiltroCategoria" class="form-select form-select-sm">
          <option value="">Todas las etiquetas</option>
          <?php foreach ($etiquetas as $et): ?>
            <option value="<?= (int) $et['id'] ?>" <?= $etiquetaFiltro === (int) $et['id'] ? 'selected' : '' ?>><?= htmlspecialchars($et['nombre']) ?></option>
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
              <?php if (!empty($t['oculto'])): ?><span class="badge rounded-pill text-bg-dark me-1"><i class="bi bi-eye-slash-fill"></i> Oculto</span><?php endif; ?>
              <?= htmlspecialchars($t['titulo']) ?>
            </div>
            <div class="text-muted small mt-1">
              <?php if ($t['curso_titulo']): ?><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($t['curso_titulo']) ?> · <?php endif; ?>
              <?php if ($t['evento_titulo']): ?><i class="bi bi-calendar-event-fill"></i> <?= htmlspecialchars($t['evento_titulo']) ?> · <?php endif; ?>
              <?php if ($t['etiquetas_nombres']): ?><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($t['etiquetas_nombres']) ?> · <?php endif; ?>
              <i class="bi bi-person-fill"></i> <?= perfil_link((int) $t['usuario_id'], (string) $t['username_cache']) ?>
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
          <?= $etiquetaFiltro ? 'No hay temas con esta etiqueta.' : 'Todavía no hay temas. ¡Sé el primero en publicar!' ?>
        </div>
      <?php endif; ?>
    </div>
    <?php $paginaActual = $pagina; require __DIR__ . '/inc/paginacion.php'; ?>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-collection"></i> Cursos y eventos</h2>
        <div class="list-group list-group-flush">
          <?php foreach ($cursosConTemas as $c): ?>
            <a class="list-group-item list-group-item-action px-2" href="curso.php?curso_id=<?= (int) $c['id'] ?>"><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($c['titulo']) ?></a>
          <?php endforeach; ?>
          <?php foreach ($eventosConTemas as $e): ?>
            <a class="list-group-item list-group-item-action px-2" href="evento.php?evento_id=<?= (int) $e['id'] ?>"><i class="bi bi-calendar-event-fill"></i> <?= htmlspecialchars($e['titulo']) ?></a>
          <?php endforeach; ?>
          <?php if (!$cursosConTemas && !$eventosConTemas): ?>
            <p class="text-muted text-center py-3 mb-0">Todavía no hay temas ligados a un curso o evento.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-bookmark-fill"></i> Categorías</h2>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($categoriasLibres as $cl): ?>
            <a class="badge rounded-pill text-decoration-none" style="background:#eaf7ea;color:#1c7a3a;" href="categoria.php?slug=<?= urlencode($cl['slug']) ?>"><?= htmlspecialchars($cl['nombre']) ?> <span class="opacity-75">(<?= (int) $cl['temas_count'] ?>)</span></a>
          <?php endforeach; ?>
          <?php if (!$categoriasLibres): ?>
            <p class="text-muted text-center py-3 mb-0 w-100">Todavía no hay categorías — cualquiera puede crear una al publicar un tema.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-tag-fill"></i> Etiquetas</h2>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($etiquetas as $et): ?>
            <a class="badge rounded-pill text-decoration-none" style="background:#e6f0fb;color:#1c5fa8;" href="etiqueta.php?slug=<?= urlencode($et['slug']) ?>"><?= htmlspecialchars($et['nombre']) ?> <span class="opacity-75">(<?= (int) $et['temas_count'] ?>)</span></a>
          <?php endforeach; ?>
          <?php if (!$etiquetas): ?>
            <p class="text-muted text-center py-3 mb-0 w-100">Todavía no hay etiquetas.</p>
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
const selectFiltroOculto = document.getElementById('selectFiltroOculto');
if (selectFiltroOculto) {
  selectFiltroOculto.addEventListener('change', function () {
    document.getElementById('formFiltroCategoria').submit();
  });
}
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
