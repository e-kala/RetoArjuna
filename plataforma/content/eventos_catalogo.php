<?php
$eventos = $conn->query(
    "SELECT id, titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, precio, imagen_portada, gratuito, solo_miembros, incluido_membresia, video_grabado_url
     FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW() ORDER BY fecha_inicio ASC"
)->fetch_all(MYSQLI_ASSOC);

$eventosPasados = $conn->query(
    "SELECT id, titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, precio, imagen_portada, gratuito, solo_miembros, incluido_membresia, video_grabado_url
     FROM eventos WHERE activo = 1 AND fecha_inicio < NOW() ORDER BY fecha_inicio DESC"
)->fetch_all(MYSQLI_ASSOC);

$usuarioEventosCatalogo = current_user();
$esMiembroEventosCatalogo = $usuarioEventosCatalogo && usuario_tiene_membresia_activa($usuarioEventosCatalogo['id']);

// "Todos" = próximos (los más cercanos primero) seguidos de pasados (los más
// recientes primero) — mismo orden que ya tiene cada pestaña por separado.
$eventosTodos = array_merge($eventos, $eventosPasados);

// Si no hay nada próximo, no tiene sentido que esa pestaña sea la que se ve
// al entrar — se prioriza "Todos" en ese caso.
$tabActiva = $eventos ? 'proximos' : 'todos';

function pf_evento_card(array $evento, bool $esPasado, bool $esMiembro): void
{
    $soloMiembros = (int) $evento['solo_miembros'] === 1;
    $incluidoMembresia = (int) $evento['incluido_membresia'] === 1;
    $accesoGratisPorMembresia = ($soloMiembros || $incluidoMembresia) && $esMiembro;
    ?>
    <div class="col" style="max-width:360px;">
      <div class="card h-100 border-0 shadow-sm">
        <div class="position-relative">
          <img src="<?= htmlspecialchars($evento['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
          <?php if ($esPasado && $evento['video_grabado_url']): ?>
            <span class="position-absolute top-50 start-50 translate-middle text-white" style="font-size:36px;">▶</span>
          <?php endif; ?>
        </div>
        <div class="card-body d-flex flex-column">
          <?php if ($soloMiembros): ?>
            <span class="badge mb-2 align-self-start" style="background:#6f42c1;">👑 Miembros</span>
          <?php elseif ($incluidoMembresia): ?>
            <span class="badge mb-2 align-self-start" style="background:#6f42c1;">👑 Incluido con membresía</span>
          <?php endif; ?>
          <h3 class="h5 fw-bold"><?= htmlspecialchars($evento['titulo']) ?></h3>
          <p class="small text-muted mb-1">
            <?php if ($esPasado): ?>
              🗓 <?= htmlspecialchars(date('d/m/Y', strtotime($evento['fecha_inicio']))) ?>
            <?php else: ?>
              <?= $evento['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $evento['ubicacion']) ?>
              · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($evento['fecha_inicio']))) ?>
            <?php endif; ?>
          </p>
          <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth((string) $evento['descripcion'], 0, 100, '…')) ?></p>
          <?php if ($esPasado): ?>
            <a href="?action=evento&amp;slug=<?= urlencode($evento['slug']) ?>" class="btn btn-outline-secondary btn-sm mt-2"><?= $evento['video_grabado_url'] ? 'Ver grabación' : 'Ver detalle' ?></a>
          <?php else: ?>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <span class="badge rounded-pill" style="background:#e6f4ea;color:#1e7d3c;">
                <?= $accesoGratisPorMembresia || $soloMiembros ? 'Incluido' : ((int) $evento['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $evento['precio'], 2) . ' MXN') ?>
              </span>
              <a href="?action=evento&amp;slug=<?= urlencode($evento['slug']) ?>" class="btn btn-outline-secondary btn-sm">Ver evento</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
}
?>
<section class="py-5">
  <div class="container py-4">
    <div class="text-center mb-4">
      <h2 class="fw-bold">Eventos</h2>
      <p class="text-muted">Encuentros en línea y presenciales de la comunidad Reto Arjuna.</p>
    </div>

    <ul class="nav nav-pills justify-content-center gap-2 mb-4">
      <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'proximos' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-proximos" type="button">Próximos</button>
      </li>
      <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'todos' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-todos" type="button">Todos</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-pasados" type="button">Pasados / grabados</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade <?= $tabActiva === 'proximos' ? 'show active' : '' ?>" id="tab-proximos">
        <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
          <?php foreach ($eventos as $evento): ?>
            <?php pf_evento_card($evento, false, $esMiembroEventosCatalogo); ?>
          <?php endforeach; ?>
          <?php if (!$eventos): ?>
            <p class="text-muted text-center">No hay eventos próximos por ahora.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="tab-pane fade <?= $tabActiva === 'todos' ? 'show active' : '' ?>" id="tab-todos">
        <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
          <?php foreach ($eventosTodos as $evento): ?>
            <?php pf_evento_card($evento, strtotime($evento['fecha_inicio']) < time(), $esMiembroEventosCatalogo); ?>
          <?php endforeach; ?>
          <?php if (!$eventosTodos): ?>
            <p class="text-muted text-center">Todavía no hay eventos registrados.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-pasados">
        <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
          <?php foreach ($eventosPasados as $evento): ?>
            <?php pf_evento_card($evento, true, $esMiembroEventosCatalogo); ?>
          <?php endforeach; ?>
          <?php if (!$eventosPasados): ?>
            <p class="text-muted text-center">Todavía no hay eventos pasados registrados.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
