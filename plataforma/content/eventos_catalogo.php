<?php
require_once __DIR__ . '/../backend/ofertas.php';
$usuarioEventosCatalogo = current_user();
$esMiembroEventosCatalogo = $usuarioEventosCatalogo && usuario_tiene_membresia_activa($usuarioEventosCatalogo['id']);
$usuarioIdEventosCatalogo = (int) ($usuarioEventosCatalogo['id'] ?? 0);
$esAdminEventosCatalogo = $usuarioEventosCatalogo && $usuarioEventosCatalogo['rol'] === 'admin';

// Un evento exclusivo para miembros (solo_miembros=1) no aparece en ningún
// listado para quien no es miembro — ni la tarjeta ni el enlace al detalle.
$filtroSoloMiembros = $esMiembroEventosCatalogo ? '' : ' AND solo_miembros = 0';

// total_lecciones/completadas — mismo patrón de subqueries correlacionadas
// que ya usa panel/content/mis_cursos.php, para la barra de progreso que se
// muestra en la tarjeta cuando el usuario ya está inscrito (usuario_id=0
// para un Visitante simplemente no matchea ninguna fila de `progreso`).
// estado_inscripcion — para distinguir "asistió" (CTA "Volver a ver
// grabaciones y materiales") de solo inscrito/comprado (CTA por progreso).
// Puede haber varias filas en evento_inscripciones+pagos para el mismo
// usuario/evento (ver usuario_esta_inscrito_evento) — MAX() con el orden
// alfabético 'asistio' > 'confirmado' > 'inscrito' no aplica aquí, así que
// se prioriza con CASE dentro de una subquery ordenada.
$subqueryEstadoInscripcion = "(SELECT ei.estado FROM evento_inscripciones ei WHERE ei.usuario_id = ? AND ei.evento_id = eventos.id AND ei.estado <> 'cancelado' ORDER BY (ei.estado = 'asistio') DESC LIMIT 1) AS estado_inscripcion";

$stmtEventos = $conn->prepare(
    "SELECT id, titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, cupo_maximo, precio, imagen_portada, gratuito, solo_miembros, incluido_membresia, descuento_miembro_pct, video_grabado_url,
            (SELECT COUNT(*) FROM lecciones WHERE evento_id = eventos.id AND estado_publicacion = 'publicado') AS total_lecciones,
            (SELECT COUNT(*) FROM progreso WHERE evento_id = eventos.id AND usuario_id = ? AND completado = 1) AS lecciones_completadas,
            $subqueryEstadoInscripcion
     FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW()$filtroSoloMiembros ORDER BY fecha_inicio ASC"
);
$stmtEventos->bind_param('ii', $usuarioIdEventosCatalogo, $usuarioIdEventosCatalogo);
$stmtEventos->execute();
$eventos = $stmtEventos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEventos->close();

$stmtEventosPasados = $conn->prepare(
    "SELECT id, titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, precio, imagen_portada, gratuito, solo_miembros, incluido_membresia, descuento_miembro_pct, video_grabado_url,
            (SELECT COUNT(*) FROM lecciones WHERE evento_id = eventos.id AND estado_publicacion = 'publicado') AS total_lecciones,
            (SELECT COUNT(*) FROM progreso WHERE evento_id = eventos.id AND usuario_id = ? AND completado = 1) AS lecciones_completadas,
            $subqueryEstadoInscripcion
     FROM eventos WHERE activo = 1 AND fecha_inicio < NOW()$filtroSoloMiembros ORDER BY fecha_inicio DESC"
);
$stmtEventosPasados->bind_param('ii', $usuarioIdEventosCatalogo, $usuarioIdEventosCatalogo);
$stmtEventosPasados->execute();
$eventosPasados = $stmtEventosPasados->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEventosPasados->close();

// "Todos" = un solo orden cronológico, de más antiguo (izquierda) a más
// próximo/nuevo (derecha) — pedido explícito del usuario. Se re-ordena aparte
// de $eventosPasados/$eventos (que conservan su propio orden, útil para sus
// pestañas individuales: pasados más recientes primero, próximos más
// cercanos primero) — un merge simple no bastaría porque esos dos arreglos
// vienen en direcciones opuestas.
$eventosTodos = array_merge($eventosPasados, $eventos);
usort($eventosTodos, fn($a, $b) => strtotime($a['fecha_inicio']) <=> strtotime($b['fecha_inicio']));

// "Todos" es la pestaña que se ve al entrar por default — ?tab=pasados en
// la URL (ver content/proximo_evento.php: botón "Ver grabaciones pasadas")
// permite entrar directo a "Pasados / grabados" en vez de "Todos".
$tabActiva = ($_GET['tab'] ?? 'todos') === 'pasados' ? 'pasados' : 'todos';

function pf_evento_card(mysqli $conn, array $evento, bool $esPasado, ?array $usuario): void
{
    $esMiembro = $usuario && usuario_tiene_membresia_activa((int) $usuario['id']);
    $haySesion = $usuario !== null;
    $usuarioId = (int) ($usuario['id'] ?? 0);
    $soloMiembros = (int) $evento['solo_miembros'] === 1;
    $incluidoMembresia = (int) $evento['incluido_membresia'] === 1;
    $accesoGratisPorMembresia = ($soloMiembros || $incluidoMembresia) && $esMiembro;
    $esPresencialCupoLimitadoConDescuentoMiembro = !$esPasado
        && $evento['tipo'] !== 'online'
        && $evento['cupo_maximo'] !== null
        && (float) $evento['precio'] > 0
        && (float) ($evento['descuento_miembro_pct'] ?? 0) > 0;
    // IN03 (checklist.txt) — quien ya tiene acceso ve un estado breve de
    // disponibilidad en vez de precio/promoción, aquí mismo en la tarjeta.
    $tieneAcceso = $usuarioId > 0 && usuario_esta_inscrito_evento($usuarioId, (int) $evento['id']);
    $ofertaEvento = resolver_oferta($conn, 'evento', [
        'id' => (int) $evento['id'], 'precio' => (float) $evento['precio'],
        'gratuito' => (bool) $evento['gratuito'], 'incluido_membresia' => $incluidoMembresia,
        'solo_miembros' => $soloMiembros, 'descuento_miembro_pct' => $evento['descuento_miembro_pct'],
        'ya_tiene_acceso' => $tieneAcceso,
    ], $usuario);
    $asistio = ($evento['estado_inscripcion'] ?? null) === 'asistio';
    // Acceso por membresía vs. independiente: evento_inscripciones no
    // guarda de dónde vino el acceso, así que se evalúa sobre el estado
    // actual del producto — "¿este evento es de los que la membresía
    // incluye, para un miembro ahora?" (mismo criterio que
    // cursos_catalogo.php/curso_detalle.php).
    $accesoPorMembresiaEvento = $tieneAcceso && $incluidoMembresia && $esMiembro;
    // ?ver=1 salta la landing comercial (si el evento tiene una vinculada) —
    // quien ya tiene acceso va directo al contenido.
    $hrefEvento = '?action=evento&amp;slug=' . urlencode($evento['slug']) . ($tieneAcceso ? '&amp;ver=1' : '');
    ?>
    <div class="col pf-item-buscable" data-busqueda="<?=htmlspecialchars($evento['titulo'].' '.strip_tags((string)$evento['descripcion']).' '.$evento['ubicacion'],ENT_QUOTES)?>" style="max-width:360px;">
      <div class="card h-100 border-0 shadow-sm pf-card-clicable" style="cursor:pointer;" data-href="?action=evento&slug=<?= urlencode($evento['slug']) ?><?= $tieneAcceso ? '&ver=1' : '' ?>">
        <div class="position-relative">
          <img src="<?= htmlspecialchars($evento['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
          <?php if ($esPasado && $evento['video_grabado_url']): ?>
            <span class="position-absolute top-50 start-50 translate-middle text-white" style="font-size:36px;">▶</span>
          <?php endif; ?>
        </div>
        <div class="card-body d-flex flex-column">
          <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
            <span class="badge <?= $esPasado ? 'pf-badge-grabado' : 'pf-badge-proximo' ?>"><?= $esPasado ? '🎥 Grabado' : '📅 Próximo evento' ?></span>
            <?php if ((int) $evento['gratuito'] === 1): ?><span class="badge pf-badge-gratis">🎉 Gratis</span><?php endif; ?>
            <?php if ($evento['tipo'] !== 'online'): ?><span class="badge pf-badge-presencial">📍 Presencial</span><?php endif; ?>
            <?php if ($accesoPorMembresiaEvento): ?>
              <span class="badge rounded-pill pf-badge-incluido d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con tu membresía<?= $esPasado && $evento['video_grabado_url'] ? ' · Grabación disponible' : '' ?></span>
            <?php elseif ($tieneAcceso): ?>
              <span class="badge rounded-pill pf-badge-acceso">✔ Ya tienes acceso<?= $esPasado && $evento['video_grabado_url'] ? ' · Grabación disponible' : '' ?></span>
            <?php elseif ($ofertaEvento['estado'] === 'oferta' && in_array($ofertaEvento['oferta_modalidad'], ['promocion', 'preventa'], true)): ?>
              <span class="badge rounded-pill <?= $ofertaEvento['oferta_modalidad'] === 'preventa' ? 'pf-badge-preventa' : 'pf-badge-promocion' ?>"><?= $ofertaEvento['oferta_modalidad'] === 'preventa' ? 'Preventa' : '🏷️ Promoción' ?> · <?= htmlspecialchars((string) $ofertaEvento['oferta_nombre']) ?></span>
            <?php elseif ($ofertaEvento['estado'] === 'oferta' && in_array($ofertaEvento['oferta_tipo'], ['descuento_miembro', 'combinado'], true)): ?>
              <span class="badge rounded-pill pf-badge-precio-miembro d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Precio especial con membresía</span>
            <?php elseif ($incluidoMembresia): ?>
              <span class="badge rounded-pill pf-badge-incluido d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con Membresía</span>
            <?php endif; ?>
            <?php if ($haySesion && !$esMiembro && (float) ($evento['descuento_miembro_pct'] ?? 0) > 0): ?>
              <span class="badge rounded-pill pf-badge-descuento-miembro d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Descuento con membresía <?= number_format((float) $evento['descuento_miembro_pct'], 0) ?>%</span>
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
          <?php
          // Progreso: mismo patrón de mis_cursos.php — solo tiene sentido
          // mostrarlo si el evento tiene lecciones y el usuario ya está
          // inscrito (sin acceso no puede haber avanzado nada).
          $totalLecciones = (int) ($evento['total_lecciones'] ?? 0);
          $porcentajeEvento = $totalLecciones > 0 ? (int) round((int) ($evento['lecciones_completadas'] ?? 0) / $totalLecciones * 100) : null;
          ?>
          <?php if ($tieneAcceso && $porcentajeEvento !== null): ?>
            <div class="progress mb-1" style="height:6px;">
              <div class="progress-bar" style="width:<?= $porcentajeEvento ?>%;background:#F6C500;"></div>
            </div>
            <p class="small text-muted mb-2"><?= $porcentajeEvento ?>% completado</p>
          <?php endif; ?>
          <?php if ($esPasado): ?>
            <?php
            // Prioridad: asistió en vivo gana siempre sobre el progreso de la
            // grabación (confirmado con el usuario) — "Volver a ver..." es el
            // mensaje correcto tanto si ya avanzó algo en la grabación como si
            // no, porque ya vivió el evento en el momento. El progreso solo
            // decide el CTA para quien tiene acceso pero NO asistió en vivo
            // (está consumiendo la grabación desde cero, o retomándola).
            if ($tieneAcceso && $asistio) {
                $labelCtaEvento = 'Volver a ver grabaciones y materiales';
            } elseif ($tieneAcceso && $evento['video_grabado_url']) {
                $labelCtaEvento = $porcentajeEvento !== null && $porcentajeEvento > 0 ? 'Seguir viendo' : 'Comenzar a ver';
            } elseif ($tieneAcceso) {
                $labelCtaEvento = 'Ver detalle';
            } else {
                // La pestaña de pasados agrupa la experiencia grabada completa:
                // algunos eventos guardan los recursos como lecciones/materiales
                // y no en el campo legado video_grabado_url. Por eso el CTA no
                // debe depender exclusivamente de ese campo.
                $labelCtaEvento = 'Saber más de las grabaciones y los materiales';
            }
            ?>
            <a href="<?= $hrefEvento ?>" class="small fw-semibold text-decoration-none d-block w-100 text-end mt-2" style="color:#171717;"><?= htmlspecialchars($labelCtaEvento) ?> →</a>
          <?php else: ?>
            <a href="<?= $hrefEvento ?>" class="small fw-semibold text-decoration-none d-block w-100 text-end mt-2" style="color:#171717;"><?= $tieneAcceso ? 'Ver evento' : ($accesoGratisPorMembresia ? 'Acceder con mi membresía' : ($esPresencialCupoLimitadoConDescuentoMiembro ? 'Apartar lugar' : 'Saber más del evento')) ?> →</a>
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
    <?php $buscadorId='buscarEventos'; $buscadorPlaceholder='Buscar eventos por título, descripción o lugar…'; include __DIR__.'/_catalogo_buscador.php'; ?>

    <ul class="nav nav-pills justify-content-center gap-2 mb-4">
      <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'pasados' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-pasados" type="button">Pasados / grabados</button>
      </li>
      <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'todos' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-todos" type="button">Todos</button>
      </li>
      <li class="nav-item">
        <button class="nav-link <?= $tabActiva === 'proximos' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-proximos" type="button">Próximos</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade <?= $tabActiva === 'pasados' ? 'show active' : '' ?>" id="tab-pasados">
        <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
          <?php foreach ($eventosPasados as $evento): ?>
            <?php pf_evento_card($conn, $evento, true, $usuarioEventosCatalogo); ?>
          <?php endforeach; ?>
          <?php if (!$eventosPasados): ?>
            <p class="text-muted text-center">Todavía no hay eventos pasados registrados.</p>
          <?php endif; ?>
        </div>
      </div>
      <div class="tab-pane fade <?= $tabActiva === 'todos' ? 'show active' : '' ?>" id="tab-todos">
        <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
          <?php foreach ($eventosTodos as $evento): ?>
            <?php pf_evento_card($conn, $evento, strtotime($evento['fecha_inicio']) < time(), $usuarioEventosCatalogo); ?>
            <?php endforeach; ?>
            <?php if (!$eventosTodos): ?>
              <p class="text-muted text-center">Todavía no hay eventos registrados.</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="tab-pane fade <?= $tabActiva === 'proximos' ? 'show active' : '' ?>" id="tab-proximos">
            <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
              <?php foreach ($eventos as $evento): ?>
                <?php pf_evento_card($conn, $evento, false, $usuarioEventosCatalogo); ?>
              <?php endforeach; ?>
              <?php if (!$eventos): ?>
                <div class="text-center py-4">
                  <p class="text-muted mb-3">No hay eventos próximos por ahora.</p>
                  <a href="?action=proximo_evento" class="btn" style="background:#f7931e;color:#fff;">Ver opciones mientras tanto</a>
                </div>
              <?php endif; ?>
            </div>
          </div>


    </div>
  </div>
</section>
