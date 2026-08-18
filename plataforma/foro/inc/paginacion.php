<?php
// Paginación compartida — espera $paginaActual, $totalPaginas y $queryBase
// (array de parámetros GET a conservar, SIN 'pagina') ya definidos por quien
// la incluya. Usa el componente <nav><ul class="pagination"> real de Bootstrap.
if (!isset($paginaActual, $totalPaginas, $queryBase) || $totalPaginas <= 1) {
    return;
}

$urlPagina = function (int $pagina) use ($queryBase): string {
    return '?' . http_build_query(array_merge($queryBase, ['pagina' => $pagina]));
};

$inicio = max(1, $paginaActual - 2);
$fin = min($totalPaginas, $paginaActual + 2);
?>
<nav aria-label="Paginación" class="mt-4">
  <ul class="pagination justify-content-center flex-wrap">
    <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $paginaActual > 1 ? htmlspecialchars($urlPagina($paginaActual - 1)) : '#' ?>">&laquo; Anterior</a>
    </li>

    <?php if ($inicio > 1): ?>
      <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($urlPagina(1)) ?>">1</a></li>
      <?php if ($inicio > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $inicio; $i <= $fin; $i++): ?>
      <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>">
        <a class="page-link" href="<?= htmlspecialchars($urlPagina($i)) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>

    <?php if ($fin < $totalPaginas): ?>
      <?php if ($fin < $totalPaginas - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($urlPagina($totalPaginas)) ?>"><?= $totalPaginas ?></a></li>
    <?php endif; ?>

    <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $paginaActual < $totalPaginas ? htmlspecialchars($urlPagina($paginaActual + 1)) : '#' ?>">Siguiente &raquo;</a>
    </li>
  </ul>
</nav>
