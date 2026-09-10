<?php
$usuarioEventosCatalogo = current_user();
$esMiembroEventosCatalogo = $usuarioEventosCatalogo && usuario_tiene_membresia_activa($usuarioEventosCatalogo['id']);

// Un evento exclusivo para miembros (solo_miembros=1) no aparece en ningún
// listado para quien no es miembro — ni la tarjeta ni el enlace al detalle.
$filtroSoloMiembros = $esMiembroEventosCatalogo ? '' : ' AND solo_miembros = 0';

$eventos = $conn->query(
    "SELECT id, titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, precio, imagen_portada, gratuito, solo_miembros, incluido_membresia, video_grabado_url
     FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW()$filtroSoloMiembros ORDER BY fecha_inicio ASC"
)->fetch_all(MYSQLI_ASSOC);

$eventosPasados = $conn->query(
    "SELECT id, titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, precio, imagen_portada, gratuito, solo_miembros, incluido_membresia, video_grabado_url
     FROM eventos WHERE activo = 1 AND fecha_inicio < NOW()$filtroSoloMiembros ORDER BY fecha_inicio DESC"
)->fetch_all(MYSQLI_ASSOC);

// "Todos" = un solo orden cronológico, de más antiguo (izquierda) a más
// próximo/nuevo (derecha) — pedido explícito del usuario. Se re-ordena aparte
// de $eventosPasados/$eventos (que conservan su propio orden, útil para sus
// pestañas individuales: pasados más recientes primero, próximos más
// cercanos primero) — un merge simple no bastaría porque esos dos arreglos
// vienen en direcciones opuestas.
$eventosTodos = array_merge($eventosPasados, $eventos);
usort($eventosTodos, fn($a, $b) => strtotime($a['fecha_inicio']) <=> strtotime($b['fecha_inicio']));

// "Todos" es la pestaña que se ve al entrar por default.
$tabActiva = 'todos';

function pf_evento_card(array $evento, bool $esPasado, bool $esMiembro, bool $haySesion, int $usuarioId = 0): void
{
    $soloMiembros = (int) $evento['solo_miembros'] === 1;
    $incluidoMembresia = (int) $evento['incluido_membresia'] === 1;
    $accesoGratisPorMembresia = ($soloMiembros || $incluidoMembresia) && $esMiembro;
    // IN03 (checklist.txt) — quien ya tiene acceso ve un estado breve de
    // disponibilidad en vez de precio/promoción, aquí mismo en la tarjeta.
    $tieneAcceso = $usuarioId > 0 && usuario_esta_inscrito_evento($usuarioId, (int) $evento['id']);
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
          <div class="d-flex flex-wrap gap-1 mb-2">
            <?php if ($haySesion && $soloMiembros): ?>
              <span class="badge align-self-start" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Miembros</span>
            <?php elseif ($haySesion && $incluidoMembresia): ?>
              <span class="badge align-self-start" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con membresía</span>
            <?php endif; ?>
            <?php if ($esPasado): ?>
              <span class="badge align-self-start" style="background:#495057;">🎥 Grabado</span>
            <?php else: ?>
              <span class="badge align-self-start" style="background:#0d6efd;">📅 Próximo evento</span>
            <?php endif; ?>
            <?php if ((int) $evento['gratuito'] === 1): ?>
              <span class="badge align-self-start" style="background:#F6C500;color:#171717;">🎉 Gratis</span>
            <?php endif; ?>
            <?php if ($evento['tipo'] !== 'online'): ?>
              <span class="badge align-self-start" style="background:#e9ecef;color:#495057;">📍 Presencial</span>
            <?php endif; ?>
          </div>
          <h3 class="h5 fw-bold"><?= htmlspecialchars($evento['titulo']) ?></h3>
          <p class="small text-muted mb-1">
            <?php if ($esPasado): ?>
              🗓 <?= htmlspecialchars(date('d/m/Y', strtotime($evento['fecha_inicio']))) ?>
            <?php else: ?>
              <?= $evento['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $evento['ubicacion']) ?>
              · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($evento['fecha_inicio']))) ?>
            <?php endif; ?>
          </p>
          <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth(trim(strip_tags((string) $evento['descripcion'])), 0, 100, '…')) ?></p>
          <?php if ($esPasado): ?>
            <?php if ($tieneAcceso && $evento['video_grabado_url']): ?>
              <span class="badge rounded-pill align-self-start mb-2" style="background:#e6f4ea;color:#1e7d3c;">Grabación disponible</span>
            <?php endif; ?>
            <a href="?action=evento&amp;slug=<?= urlencode($evento['slug']) ?>" class="btn btn-outline-secondary btn-sm mt-2"><?= $evento['video_grabado_url'] ? 'Ver grabación' : 'Ver detalle' ?></a>
          <?php else: ?>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <span class="badge rounded-pill" style="background:#e6f4ea;color:#1e7d3c;">
                <?= $tieneAcceso ? '✔ Adquirido' : ($accesoGratisPorMembresia || $soloMiembros ? 'Incluido' : ((int) $evento['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $evento['precio'], 2) . ' MXN')) ?>
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
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-pasados" type="button">Pasados / grabados</button>
      </li>
      <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'todos' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-todos" type="button">Todos</button>
      </li>
      <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'proximos' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-proximos" type="button">Próximos</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade" id="tab-pasados">
        <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
          <?php foreach ($eventosPasados as $evento): ?>
            <?php pf_evento_card($evento, true, $esMiembroEventosCatalogo, $usuarioEventosCatalogo !== null, (int) ($usuarioEventosCatalogo['id'] ?? 0)); ?>
          <?php endforeach; ?>
          <?php if (!$eventosPasados): ?>
            <p class="text-muted text-center">Todavía no hay eventos pasados registrados.</p>
          <?php endif; ?>
        </div>
      </div>
      <div class="tab-pane fade <?= $tabActiva === 'todos' ? 'show active' : '' ?>" id="tab-todos">
        <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
          <?php foreach ($eventosTodos as $evento): ?>
            <?php pf_evento_card($evento, strtotime($evento['fecha_inicio']) < time(), $esMiembroEventosCatalogo, $usuarioEventosCatalogo !== null, (int) ($usuarioEventosCatalogo['id'] ?? 0)); ?>
            <?php endforeach; ?>
            <?php if (!$eventosTodos): ?>
              <p class="text-muted text-center">Todavía no hay eventos registrados.</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="tab-pane fade <?= $tabActiva === 'proximos' ? 'show active' : '' ?>" id="tab-proximos">
            <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
              <?php foreach ($eventos as $evento): ?>
                <?php pf_evento_card($evento, false, $esMiembroEventosCatalogo, $usuarioEventosCatalogo !== null, (int) ($usuarioEventosCatalogo['id'] ?? 0)); ?>
              <?php endforeach; ?>
              <?php if (!$eventos): ?>
                <p class="text-muted text-center">No hay eventos próximos por ahora.</p>
              <?php endif; ?>
            </div>
          </div>

      
    </div>
  </div>
</section>
