<?php
require_once __DIR__ . '/../backend/ofertas.php';
$slug = $_GET['slug'] ?? '';
$stmt = $conn->prepare('SELECT * FROM eventos WHERE slug = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$evento) {
    echo '<div class="container" style="margin-top:143px;"><p>Evento no encontrado.</p></div>';
    return;
}

$eventoId = (int) $evento['id'];
$usuario = current_user();
$inscrito = $usuario ? usuario_esta_inscrito_evento($usuario['id'], $eventoId) : false;
// Vuelta de checkout.php tras un pago con tarjeta confirmado (ver
// destino_tras_pago()) — a diferencia de curso_detalle.php (que ya tenía su
// propio banner persistente con ?bienvenida=1), aquí no había NINGUNA
// confirmación visible, así que se usa un toast de notify.js en vez de un
// banner nuevo. Exige $inscrito real, no solo el query param, para que un
// enlace armado a mano no pueda fingir un "pago confirmado".
$mostrarPagoOk = $inscrito && ($_GET['pago'] ?? '') === 'ok';
$esMiembro = $usuario ? usuario_tiene_membresia_activa($usuario['id']) : false;
$esPasado = strtotime($evento['fecha_inicio']) < time();
$soloMiembros = (int) $evento['solo_miembros'] === 1;
$incluidoMembresia = (int) $evento['incluido_membresia'] === 1;
// solo_miembros ya implica "incluido" para un miembro — incluido_membresia
// extiende lo mismo a un evento que no es exclusivo (los demás lo pueden
// seguir comprando).
$accesoGratisPorMembresia = ($soloMiembros || $incluidoMembresia) && $esMiembro;

// Motor de ofertas (checklist.txt OF01-OF10) — misma fuente de verdad que
// checkout.php/curso_detalle.php. $oferta['estado']==='exclusivo_bloqueado'
// es exactamente lo mismo que $bloqueadoPorMembresia calculaba a mano.
$codigoCupon = $_GET['cupon'] ?? ($_SESSION['cupon_pendiente'] ?? null);
$ofertaItem = [
    'id' => $eventoId,
    'precio' => (float) $evento['precio'],
    'gratuito' => (bool) $evento['gratuito'],
    'incluido_membresia' => $incluidoMembresia,
    'solo_miembros' => $soloMiembros,
    'descuento_miembro_pct' => $evento['descuento_miembro_pct'] !== null ? (float) $evento['descuento_miembro_pct'] : null,
    'ya_tiene_acceso' => $inscrito,
];
$oferta = resolver_oferta($conn, 'evento', $ofertaItem, $usuario, $codigoCupon);
$bloqueadoPorMembresia = $oferta['estado'] === 'exclusivo_bloqueado';
$puedeAccederGratis = $oferta['estado'] === 'gratuito' || $oferta['estado'] === 'incluido_membresia' || $oferta['acceso_gratis_automatico'];

// Flujo de venta (landing comercial enlazada, ver panel/admin/
// contenido_form.php): quien NO tiene acceso todavía se manda a la landing
// en vez de ver esta página de venta genérica. exclusivo_bloqueado se deja
// AFUERA a propósito — ese estado ya tiene su propia protección de no
// revelar título/imagen/descripción a quien no es miembro (ver el bloque
// de abajo), y una landing comercial pública rompería justo esa protección.
// Un admin tampoco se redirige, para poder revisar/editar el evento.
// Tampoco se redirige si ?auto=1 (ver más abajo, "Mismo mecanismo que
// curso_detalle.php"): esa señal explícita de "vengo de autenticarme para
// completar la inscripción" debe llegar hasta el auto-checkout/auto-inscripción
// de abajo — sin esta excepción, un evento con landing vinculada rebotaba de
// vuelta a la landing justo en ese momento y el flujo nunca se completaba.
$esAdminParaLanding = $usuario && $usuario['rol'] === 'admin';
if (!$esAdminParaLanding && $evento['landing_page_id'] && !in_array($oferta['estado'], ['acceso', 'incluido_membresia', 'exclusivo_bloqueado'], true) && !($usuario && ($_GET['auto'] ?? '') === '1')) {
    $stmtLanding = $conn->prepare('SELECT slug FROM landing_pages WHERE id = ? AND activo = 1');
    $stmtLanding->bind_param('i', $evento['landing_page_id']);
    $stmtLanding->execute();
    $landingVinculada = $stmtLanding->get_result()->fetch_assoc();
    $stmtLanding->close();
    if ($landingVinculada) {
        header('Location: ' . BASE_URL . '/index.php?action=landing&slug=' . urlencode($landingVinculada['slug']));
        exit;
    }
}

// Un evento exclusivo para miembros no debe revelar título, imagen ni
// descripción a quien no es miembro — no basta con bloquear el botón de
// inscripción, alguien con el enlace directo no debería ver nada del evento.
// Un Visitante (sin sesión) tampoco debe ver mención alguna de membresía
// (checklist.txt H02) — se le pide cuenta/login primero, sin nombrar
// "Camino Arjuna"; un Usuario Arjuna logueado sin membresía sí puede verla
// como alternativa (H02 solo restringe a Visitantes).
if ($bloqueadoPorMembresia) {
    $volverEvento = urlencode((string) ($_SERVER['REQUEST_URI'] ?? ''));
    ?>
    <div class="container" style="margin-top: 143px; margin-bottom: 60px;">
      <a href="?action=eventos" class="d-inline-block mb-3">&larr; Volver a eventos</a>
      <?php if (!$usuario): ?>
        <div class="card p-4 text-center" style="max-width:480px;margin:0 auto;">
          <h1 class="h4 mt-2">Necesitas una cuenta para ver este evento</h1>
          <p class="text-muted">Este contenido requiere sesión iniciada.</p>
          <a href="?action=registro&volver=<?= $volverEvento ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn btn-primary mb-2">Crear mi cuenta</a>
          <a href="?action=ingreso&volver=<?= $volverEvento ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn btn-outline-secondary">Ya tengo cuenta</a>
        </div>
      <?php else: ?>
        <div class="card p-4 text-center" style="max-width:480px;margin:0 auto;">
          <div style="font-size:40px;"></div>
          <h1 class="h4 mt-2">Evento exclusivo para miembros</h1>
          <p class="text-muted">Este evento es exclusivo para miembros de Camino Arjuna. Únete a la membresía para conocer los detalles y registrarte.</p>
          <a href="?action=membresia" class="btn" style="background:#6f42c1;color:#fff;"> Conoce la membresía</a>
        </div>
      <?php endif; ?>
    </div>
    <?php
    return;
}

$cupoDisponible = true;
$cupoRestante = null;
if ($evento['cupo_maximo'] !== null) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM evento_inscripciones WHERE evento_id = ? AND estado <> 'cancelado'");
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $inscritos = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    $cupoRestante = max(0, (int) $evento['cupo_maximo'] - $inscritos);
    $cupoDisponible = $cupoRestante > 0;
}

// Lecciones asociadas (ver schema_lecciones_compartidas.sql) — un evento puede
// tener contenido propio, o haberlo heredado de un curso convertido a evento.
// Un borrador nunca se muestra a un alumno — un admin sí los ve, marcados aparte.
$esAdminEvento = $usuario && $usuario['rol'] === 'admin';
$stmt = $conn->prepare($esAdminEvento
    ? 'SELECT * FROM lecciones WHERE evento_id = ? ORDER BY orden'
    : 'SELECT * FROM lecciones WHERE evento_id = ? AND estado_publicacion = "publicado" ORDER BY orden');
$stmt->bind_param('i', $eventoId);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mismo checkmark de progreso que ya tiene curso_detalle.php — ver
// backend/progreso_back.php (extendido para aceptar lecciones de evento).
$completadas = [];
if ($usuario) {
    $stmt = $conn->prepare('SELECT leccion_id FROM progreso WHERE usuario_id = ? AND evento_id = ? AND completado = 1');
    $stmt->bind_param('ii', $usuario['id'], $eventoId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $completadas[(int) $row['leccion_id']] = true;
    }
    $stmt->close();
}
// El acceso real (grabación + lecciones no-demo) exige estar inscrito de
// verdad — ser miembro por sí solo ya no basta; para un evento incluido en
// la membresía, primero hay que dar clic en "Accesar gratis con mi
// membresía" (abajo), que sí crea la inscripción.
$tieneAccesoLecciones = $inscrito;

// Mismo mecanismo que curso_detalle.php: &auto=1 en el volver de crear
// cuenta/login ahorra el clic extra en "Comprar"/"Inscribirme" al regresar.
$volverActualConAuto = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '') . (str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ? '&' : '?') . 'auto=1');
$quiereAutoInscribirse = $usuario && !$inscrito && ($_GET['auto'] ?? '') === '1';
if ($quiereAutoInscribirse && !$puedeAccederGratis && ($esPasado || $cupoDisponible)) {
    header('Location: backend/pagos/checkout.php?evento_id=' . $eventoId . ($codigoCupon ? '&cupon=' . urlencode($codigoCupon) : ''));
    exit;
}

$resumenCalificacion = calificacion_resumen(null, $eventoId);
$puedeCalificar = $usuario && usuario_puede_calificar_evento($usuario['id'], $eventoId);
$miCalificacion = $usuario ? obtener_calificacion_usuario($usuario['id'], null, $eventoId) : null;

// Prestaciones y regalos — ver curso_detalle.php para el criterio completo.
$regaloConfig = $inscrito ? regalo_configuracion_obtener(null, $eventoId) : null;
$regaloEstado = $regaloConfig && $usuario ? regalo_estado_usuario($regaloConfig, $usuario['id']) : null;
$regaloMisEnlaces = $regaloConfig && $usuario ? regalos_generados_por($usuario['id'], $regaloConfig['id']) : [];
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=eventos" class="d-inline-block mb-3">&larr; Volver a eventos</a>
  <div class="row">
    <div class="col-md-6 mb-3" id="eventoMedia">
      <?php if ($esPasado && $evento['video_grabado_url'] && $tieneAccesoLecciones): ?>
        <div class="ratio ratio-16x9 rounded overflow-hidden">
          <iframe src="<?= htmlspecialchars($evento['video_grabado_url']) ?>" allowfullscreen></iframe>
        </div>
      <?php else: ?>
        <img src="<?= htmlspecialchars($evento['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="img-fluid rounded" alt="">
      <?php endif; ?>
    </div>
    <div class="col-md-6">
  <div class="d-flex align-items-center flex-wrap gap-2">
    <h1 class="mb-0"><?= htmlspecialchars($evento['titulo']) ?></h1>
    <?php if ($esAdminEvento): ?>
      <a href="panel/admin/contenido_form.php?tipo=evento&id=<?= $eventoId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
  <?php if ($resumenCalificacion['total'] > 0): ?>
    <p class="text-muted mb-2">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <i class="bi <?= $i <= round($resumenCalificacion['promedio']) ? 'bi-star-fill' : 'bi-star' ?>" style="color:#f7931e;"></i>
      <?php endfor; ?>
      <?= $resumenCalificacion['promedio'] ?> (<?= $resumenCalificacion['total'] ?> calificación<?= $resumenCalificacion['total'] === 1 ? '' : 'es' ?>)
    </p>
  <?php endif; ?>
  <?php if ($esPasado): ?>
    <span class="badge bg-secondary mb-2">Grabado</span>
  <?php endif; ?>
  <?php if ($usuario && $soloMiembros): ?>
    <span class="badge mb-2" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Exclusivo para miembros</span>
  <?php elseif ($usuario && $incluidoMembresia): ?>
    <span class="badge mb-2" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con membresía</span>
  <?php endif; ?>
  <p class="text-muted mb-1">
    <?= $evento['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $evento['ubicacion']) ?>
  </p>
  <p class="text-muted mb-1">
    🗓 <?= htmlspecialchars(date('d/m/Y H:i', strtotime($evento['fecha_inicio']))) ?>
    <?php if ($evento['fecha_fin']): ?>
      &ndash; <?= htmlspecialchars(date('d/m/Y H:i', strtotime($evento['fecha_fin']))) ?>
    <?php endif; ?>
  </p>
  <?php if ($evento['cupo_maximo'] !== null && !$esPasado): ?>
    <p class="text-muted mb-1">
      👥 <?= $cupoRestante ?> de <?= (int) $evento['cupo_maximo'] ?> lugares disponibles
    </p>
  <?php endif; ?>
  <div class="pf-contenido-html"><?= (string) $evento['descripcion'] ?></div>
  <p>
    <?php if ($accesoGratisPorMembresia || $soloMiembros): ?>
      <span class="badge" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido en tu membresía</span>
    <?php elseif ($oferta['estado'] === 'oferta'): ?>
      <span class="badge text-decoration-line-through bg-secondary">$<?= number_format($oferta['precio_regular'], 2) ?></span>
      <span class="badge" style="background:#f7931e;"><?= $oferta['precio_final'] > 0 ? '$' . number_format($oferta['precio_final'], 2) . ' MXN' : 'Gratis' ?></span>
      <span class="badge bg-secondary"><?= htmlspecialchars((string) $oferta['oferta_nombre']) ?></span>
    <?php else: ?>
      <span class="badge" style="background:#f7931e;">
        <?= $oferta['estado'] === 'gratuito' ? 'Gratuito' : '$' . number_format((float) $evento['precio'], 2) . ' MXN' ?>
      </span>
      <?php if ($usuario && $incluidoMembresia): ?>
        <span class="badge" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con membresía</span>
      <?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if ($evento['foro_url']): ?>
    <a href="<?= htmlspecialchars(navbar_href($evento['foro_url'], '../')) ?>" target="_blank" class="btn btn-outline-secondary btn-sm mb-3">Discutir en el foro</a>
  <?php endif; ?>

  <?php if ($regaloConfig): ?>
    <?php
    $regaloModalidad = (float) $regaloConfig['descuento_pct'] >= 100
        ? 'acceso'
        : ((float) $regaloConfig['descuento_pct']) . '% de descuento';
    ?>
    <div class="card p-3 mb-3" style="max-width:480px;background:#fff8ec;border-color:#f7931e;">
      <h2 class="h6 mb-1">🎁 Regalar <?= htmlspecialchars($regaloModalidad) ?> — <?= (int) $regaloEstado['disponibles'] ?> disponible<?= (int) $regaloEstado['disponibles'] === 1 ? '' : 's' ?></h2>
      <?php if ($regaloEstado['puede_generar']): ?>
        <button id="btnGenerarRegalo" class="btn btn-sm mt-1" style="background:#f7931e;color:#fff;max-width:220px;">Generar enlace de regalo</button>
        <div id="generarRegaloMsg" class="form-text mt-1"></div>
      <?php elseif ($regaloEstado['motivo_bloqueo']): ?>
        <p class="text-muted small mb-0"><?= htmlspecialchars($regaloEstado['motivo_bloqueo']) ?></p>
      <?php endif; ?>
      <?php if ($regaloMisEnlaces): ?>
        <ul class="list-group list-group-flush mt-2" id="listaRegalos">
          <?php foreach ($regaloMisEnlaces as $r): ?>
            <?php
            $regaloBadge = match ($r['estado']) {
                'disponible' => '<span class="badge bg-secondary">Disponible</span>',
                'reclamado' => '<span class="badge" style="background:#f7931e;">Reclamado por ' . htmlspecialchars((string) $r['recibe_username']) . '</span>',
                'aceptado' => '<span class="badge bg-success">Aceptado por ' . htmlspecialchars((string) $r['recibe_username']) . '</span>',
                'revocado' => '<span class="badge bg-dark">Revocado</span>',
                default => '',
            };
            ?>
            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center gap-2">
              <?php if ($r['estado'] === 'disponible'): ?>
                <code class="small text-truncate"><?= htmlspecialchars(BASE_URL . '/index.php?action=regalo&codigo=' . $r['codigo']) ?></code>
              <?php else: ?>
                <span class="small text-muted">Código <?= htmlspecialchars($r['codigo']) ?></span>
              <?php endif; ?>
              <?= $regaloBadge ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($puedeCalificar): ?>
    <div class="card p-3 mb-3" style="max-width:480px;">
      <h2 class="h6 mb-2"><?= $miCalificacion ? 'Tu calificación' : '¿Qué te pareció este evento?' ?></h2>
      <div id="calificarEstrellas" data-valor="<?= (int) ($miCalificacion['puntuacion'] ?? 0) ?>">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="bi <?= $i <= (int) ($miCalificacion['puntuacion'] ?? 0) ? 'bi-star-fill' : 'bi-star' ?> estrella-calificar" data-estrella="<?= $i ?>" style="color:#f7931e;font-size:1.4rem;cursor:pointer;"></i>
        <?php endfor; ?>
      </div>
      <textarea id="calificarComentario" class="form-control mt-2" rows="2" maxlength="500" placeholder="Comentario opcional"><?= htmlspecialchars((string) ($miCalificacion['comentario'] ?? '')) ?></textarea>
      <button id="btnGuardarCalificacion" class="btn btn-sm mt-2" style="background:#f7931e;color:#fff;">Guardar calificación</button>
      <div id="calificarMsg" class="form-text mt-1"></div>
    </div>
  <?php endif; ?>

  <div class="card p-3" style="max-width:480px;" id="eventoCTA">
    <?php if ($inscrito): ?>
      <p class="mb-0 text-success">✔ Ya estás inscrito en este evento.<?= $esPasado && $evento['video_grabado_url'] ? ' Mira la grabación arriba.' : '' ?></p>
    <?php elseif (!$esPasado && !$cupoDisponible): ?>
      <p class="mb-0 text-muted">Ya no hay cupo disponible para este evento.</p>
    <?php elseif (!$usuario): ?>
      <p class="mb-2"><?= $esPasado ? 'Crea tu Cuenta Arjuna para acceder a la grabación de este evento — al terminar, quedas inscrito automáticamente.' : 'Crea tu Cuenta Arjuna para inscribirte — al terminar, quedas inscrito automáticamente, sin pasos extra.' ?></p>
      <a href="?action=registro&volver=<?= $volverActualConAuto ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn" style="background:#f7931e;color:#fff;"><?= !$esPasado && ($oferta['estado'] === 'gratuito' || $oferta['acceso_gratis_automatico']) ? 'Inscribirme gratis' : 'Inscribirme' ?></a>
    <?php elseif ($puedeAccederGratis): ?>
      <button id="btnInscribirse" class="btn" style="background:#f7931e;color:#fff;">
        <?php if ($accesoGratisPorMembresia && $oferta['estado'] !== 'gratuito'): ?>
          Accesar gratis con mi membresía
        <?php elseif ($oferta['acceso_gratis_automatico'] && $oferta['estado'] === 'oferta'): ?>
          Inscribirme gratis (<?= htmlspecialchars((string) $oferta['oferta_nombre']) ?>)
        <?php elseif ($esPasado): ?>
          Registrarme para ver la grabación
        <?php elseif ($oferta['estado'] === 'gratuito'): ?>
          Inscribirme gratis
        <?php else: ?>
          Inscribirme
        <?php endif; ?>
      </button>
      <div id="inscribirMsg" class="form-text mt-2"></div>
    <?php else: ?>
      <a href="backend/pagos/checkout.php?evento_id=<?= $eventoId ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn" style="background:#f7931e;color:#fff;"><?= $esPasado ? 'Comprar acceso a la grabación' : 'Inscribirme (de pago)' ?></a>
    <?php endif; ?>
  </div>
    </div>
  </div>

  <div id="eventoContenido">
  <?php if ($lecciones): ?>
    <h2 class="h5 mt-4">Contenido</h2>
    <div class="list-group mb-4">
      <?php foreach ($lecciones as $leccion): ?>
        <?php $desbloqueada = $tieneAccesoLecciones || (int) $leccion['vista_previa'] === 1; ?>
        <?php if ($desbloqueada): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <a href="?action=leccion&id=<?= (int) $leccion['id'] ?>" class="text-decoration-none flex-grow-1">
              <?php if (!empty($completadas[(int) $leccion['id']])): ?><i class="bi bi-check-circle-fill text-success"></i><?php endif; ?>
              <?= htmlspecialchars($leccion['titulo']) ?>
            </a>
            <?php if ($esAdminEvento && $leccion['estado_publicacion'] === 'borrador'): ?><span class="badge bg-secondary me-2">Borrador</span><?php endif; ?>
            <?php if (!$tieneAccesoLecciones): ?><span class="badge bg-info me-2">Demo</span><?php endif; ?>
            <?php if ($tieneAccesoLecciones): ?>
              <a href="<?= $leccion['foro_url'] ? htmlspecialchars(navbar_href($leccion['foro_url'], '../')) : 'foro/evento.php?evento_id=' . $eventoId . '&leccion_id=' . (int) $leccion['id'] ?>" class="text-muted small" title="Discutir esta lección en el foro">
                <i class="bi bi-chat-square-text"></i>
              </a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <span class="list-group-item d-flex justify-content-between align-items-center text-muted">
            <span><i class="bi bi-lock-fill"></i> <?= htmlspecialchars($leccion['titulo']) ?></span>
          </span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</div>

<?php if ($usuario && !$inscrito && ($esPasado || $cupoDisponible) && $puedeAccederGratis): ?>
<script>
  // Tras inscribirse, en vez de recargar la página entera para que se vea
  // el video/lecciones desbloqueadas, se vuelve a pedir esta misma URL (ya
  // con la inscripción hecha) y se reemplazan solo los 3 bloques cuyo
  // contenido depende de tener acceso (#eventoMedia, #eventoCTA,
  // #eventoContenido) — reusa el mismo render de PHP, así que nunca se
  // desincroniza de las reglas reales de acceso/candado.
  async function pfLiberarContenidoEvento() {
    const res = await fetch(window.location.href, { cache: 'no-store' });
    const html = await res.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    ['eventoMedia', 'eventoCTA', 'eventoContenido'].forEach(function (id) {
      const nuevo = doc.getElementById(id);
      const actual = document.getElementById(id);
      if (nuevo && actual) {
        actual.replaceWith(nuevo);
      }
    });
  }

  document.getElementById('btnInscribirse').addEventListener('click', async function () {
    this.disabled = true;
    const res = await fetch('backend/evento_inscribir.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        evento_id: <?= $eventoId ?>,
        csrf_token: <?= json_encode(csrf_token()) ?>,
        codigo_cupon: <?= json_encode((string) $codigoCupon) ?>,
      }),
    });
    const data = await res.json();
    if (data.success) {
      $.notify('¡Listo! Ya estás inscrito.', { className: 'success', position: 'top right', autoHideDelay: 4000 });
      await pfLiberarContenidoEvento();
    } else {
      const msg = document.getElementById('inscribirMsg');
      if (msg) {
        msg.textContent = data.message || 'No se pudo completar la inscripción.';
        msg.className = 'form-text text-danger mt-2';
      }
      this.disabled = false;
    }
  });
  <?php if ($quiereAutoInscribirse): ?>
  document.getElementById('btnInscribirse').click();
  <?php endif; ?>
</script>
<?php endif; ?>

<?php if ($puedeCalificar): ?>
<script>
  (function () {
    const contenedor = document.getElementById('calificarEstrellas');
    const estrellas = contenedor.querySelectorAll('.estrella-calificar');
    function pintar(valor) {
      estrellas.forEach(function (e) {
        e.className = 'bi ' + (Number(e.dataset.estrella) <= valor ? 'bi-star-fill' : 'bi-star') + ' estrella-calificar';
      });
      contenedor.dataset.valor = valor;
    }
    estrellas.forEach(function (e) {
      e.addEventListener('click', function () { pintar(Number(e.dataset.estrella)); });
    });
    document.getElementById('btnGuardarCalificacion').addEventListener('click', async function () {
      const puntuacion = Number(contenedor.dataset.valor);
      const msg = document.getElementById('calificarMsg');
      if (!puntuacion) {
        msg.textContent = 'Elige una calificación de 1 a 5 estrellas.';
        msg.className = 'form-text text-danger mt-1';
        return;
      }
      this.disabled = true;
      const res = await fetch('backend/calificacion_guardar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          evento_id: <?= $eventoId ?>,
          puntuacion: puntuacion,
          comentario: document.getElementById('calificarComentario').value,
          csrf_token: <?= json_encode(csrf_token()) ?>,
        }),
      }).catch(function () { return null; });
      this.disabled = false;
      if (!res) {
        msg.textContent = 'No se pudo guardar, intenta de nuevo.';
        msg.className = 'form-text text-danger mt-1';
        return;
      }
      const data = await res.json();
      msg.textContent = data.success ? 'Gracias por tu calificación.' : (data.message || 'No se pudo guardar.');
      msg.className = 'form-text mt-1 ' + (data.success ? 'text-success' : 'text-danger');
    });
  })();
</script>
<?php endif; ?>

<?php if ($regaloConfig && $regaloEstado && $regaloEstado['puede_generar']): ?>
<script>
  document.getElementById('btnGenerarRegalo').addEventListener('click', async function () {
    this.disabled = true;
    const msg = document.getElementById('generarRegaloMsg');
    const res = await fetch('backend/regalo_generar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ evento_id: <?= $eventoId ?>, csrf_token: <?= json_encode(csrf_token()) ?> }),
    }).catch(function () { return null; });
    if (!res) {
      msg.textContent = 'No se pudo generar el enlace, intenta de nuevo.';
      msg.className = 'form-text text-danger mt-1';
      this.disabled = false;
      return;
    }
    const data = await res.json();
    if (data.success) {
      window.location.reload();
    } else {
      msg.textContent = data.message || 'No se pudo generar el enlace.';
      msg.className = 'form-text text-danger mt-1';
      this.disabled = false;
    }
  });
</script>
<?php endif; ?>

<?php if ($mostrarPagoOk): ?>
<script>
  $(function () {
    $.notify('¡Listo! Tu pago fue confirmado y ya tienes acceso a este evento.', { className: 'success', position: 'top right', autoHideDelay: 4000 });
  });
</script>
<?php endif; ?>
