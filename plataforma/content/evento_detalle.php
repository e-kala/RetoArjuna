<?php
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
$esMiembro = $usuario ? usuario_tiene_membresia_activa($usuario['id']) : false;
$esPasado = strtotime($evento['fecha_inicio']) < time();
$soloMiembros = (int) $evento['solo_miembros'] === 1;
$incluidoMembresia = (int) $evento['incluido_membresia'] === 1;
// solo_miembros ya implica "incluido" para un miembro — incluido_membresia
// extiende lo mismo a un evento que no es exclusivo (los demás lo pueden
// seguir comprando).
$accesoGratisPorMembresia = ($soloMiembros || $incluidoMembresia) && $esMiembro;
$bloqueadoPorMembresia = $soloMiembros && !$esMiembro;
$puedeAccederGratis = (int) $evento['gratuito'] === 1 || $accesoGratisPorMembresia;

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
$stmt = $conn->prepare('SELECT * FROM lecciones WHERE evento_id = ? ORDER BY orden');
$stmt->bind_param('i', $eventoId);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// El acceso real (grabación + lecciones no-demo) exige estar inscrito de
// verdad — ser miembro por sí solo ya no basta; para un evento incluido en
// la membresía, primero hay que dar clic en "Accesar gratis con mi
// membresía" (abajo), que sí crea la inscripción.
$tieneAccesoLecciones = $inscrito;
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=eventos" class="d-inline-block mb-3">&larr; Volver a eventos</a>
  <div class="row">
    <div class="col-md-6 mb-3">
      <?php if ($esPasado && $evento['video_grabado_url'] && $tieneAccesoLecciones): ?>
        <div class="ratio ratio-16x9 rounded overflow-hidden">
          <iframe src="<?= htmlspecialchars($evento['video_grabado_url']) ?>" allowfullscreen></iframe>
        </div>
      <?php else: ?>
        <img src="<?= htmlspecialchars($evento['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="img-fluid rounded" alt="">
      <?php endif; ?>
    </div>
    <div class="col-md-6">
  <h1><?= htmlspecialchars($evento['titulo']) ?></h1>
  <?php if ($esPasado): ?>
    <span class="badge bg-secondary mb-2">Grabado</span>
  <?php endif; ?>
  <?php if ($soloMiembros): ?>
    <span class="badge mb-2" style="background:#6f42c1;">👑 Exclusivo para miembros</span>
  <?php elseif ($incluidoMembresia): ?>
    <span class="badge mb-2" style="background:#6f42c1;">👑 Incluido con membresía</span>
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
  <p><?= nl2br(htmlspecialchars((string) $evento['descripcion'])) ?></p>
  <p>
    <?php if ($accesoGratisPorMembresia || $soloMiembros): ?>
      <span class="badge" style="background:#6f42c1;">Incluido en tu membresía</span>
    <?php else: ?>
      <span class="badge" style="background:#f7931e;">
        <?= (int) $evento['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $evento['precio'], 2) . ' MXN' ?>
      </span>
      <?php if ($incluidoMembresia): ?>
        <span class="badge" style="background:#6f42c1;">👑 Incluido con membresía</span>
      <?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if ($evento['foro_url']): ?>
    <a href="<?= htmlspecialchars(navbar_href($evento['foro_url'], '../')) ?>" target="_blank" class="btn btn-outline-secondary btn-sm mb-3">Discutir en el foro</a>
  <?php endif; ?>

  <div class="card p-3" style="max-width:480px;">
    <?php if ($bloqueadoPorMembresia): ?>
      <p class="mb-2">Este evento es exclusivo para miembros de Camino Arjuna.</p>
      <a href="?action=membresia" class="btn" style="background:#6f42c1;color:#fff;">👑 Conoce la membresía</a>
    <?php elseif ($inscrito): ?>
      <p class="mb-0 text-success">✔ Ya estás inscrito en este evento.<?= $esPasado && $evento['video_grabado_url'] ? ' Mira la grabación arriba.' : '' ?></p>
    <?php elseif (!$esPasado && !$cupoDisponible): ?>
      <p class="mb-0 text-muted">Ya no hay cupo disponible para este evento.</p>
    <?php elseif (!$usuario): ?>
      <p class="mb-2"><?= $esPasado ? 'Inicia sesión o regístrate para acceder a la grabación de este evento.' : 'Regístrate para inscribirte a este evento.' ?></p>
      <a href="?action=registro" class="btn" style="background:#f7931e;color:#fff;">Regístrate</a>
    <?php elseif ($puedeAccederGratis): ?>
      <button id="btnInscribirse" class="btn" style="background:#f7931e;color:#fff;">
        <?php if ($accesoGratisPorMembresia && (int) $evento['gratuito'] !== 1): ?>
          Accesar gratis con mi membresía
        <?php elseif ($esPasado): ?>
          Registrarme para ver la grabación
        <?php else: ?>
          Inscribirme
        <?php endif; ?>
      </button>
      <div id="inscribirMsg" class="form-text mt-2"></div>
    <?php else: ?>
      <a href="backend/pagos/checkout.php?evento_id=<?= $eventoId ?>" class="btn" style="background:#f7931e;color:#fff;"><?= $esPasado ? 'Comprar acceso a la grabación' : 'Inscribirme (de pago)' ?></a>
    <?php endif; ?>
  </div>
    </div>
  </div>

  <?php if ($lecciones): ?>
    <h2 class="h5 mt-4">Contenido</h2>
    <div class="list-group mb-4">
      <?php foreach ($lecciones as $leccion): ?>
        <?php $desbloqueada = $tieneAccesoLecciones || (int) $leccion['vista_previa'] === 1; ?>
        <?php if ($desbloqueada): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <a href="?action=leccion&id=<?= (int) $leccion['id'] ?>" class="text-decoration-none flex-grow-1">
              <?= htmlspecialchars($leccion['titulo']) ?>
            </a>
            <?php if (!$tieneAccesoLecciones): ?><span class="badge bg-info">Demo</span><?php endif; ?>
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

<?php if ($usuario && !$inscrito && !$bloqueadoPorMembresia && ($esPasado || $cupoDisponible) && $puedeAccederGratis): ?>
<script>
  document.getElementById('btnInscribirse').addEventListener('click', async function () {
    const res = await fetch('backend/evento_inscribir.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ evento_id: <?= $eventoId ?>, csrf_token: <?= json_encode(csrf_token()) ?> }),
    });
    const data = await res.json();
    const msg = document.getElementById('inscribirMsg');
    if (data.success) {
      msg.textContent = '¡Listo! Ya estás inscrito.';
      msg.className = 'form-text text-success mt-2';
      this.disabled = true;
    } else {
      msg.textContent = data.message || 'No se pudo completar la inscripción.';
      msg.className = 'form-text text-danger mt-2';
    }
  });
</script>
<?php endif; ?>
