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

$cupoDisponible = true;
if ($evento['cupo_maximo'] !== null) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM evento_inscripciones WHERE evento_id = ? AND estado <> 'cancelado'");
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $inscritos = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    $cupoDisponible = $inscritos < (int) $evento['cupo_maximo'];
}
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=eventos" class="d-inline-block mb-3">&larr; Volver a eventos</a>
  <h1><?= htmlspecialchars($evento['titulo']) ?></h1>
  <p class="text-muted">
    <?= $evento['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $evento['ubicacion']) ?>
    · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($evento['fecha_inicio']))) ?>
  </p>
  <p><?= nl2br(htmlspecialchars((string) $evento['descripcion'])) ?></p>
  <p>
    <span class="badge" style="background:#f7931e;">
      <?= (int) $evento['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $evento['precio'], 2) . ' MXN' ?>
    </span>
  </p>

  <?php if ($evento['foro_url']): ?>
    <a href="<?= htmlspecialchars($evento['foro_url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm mb-3">Discutir en el foro</a>
  <?php endif; ?>

  <div class="card p-3" style="max-width:480px;">
    <?php if ($inscrito): ?>
      <p class="mb-0 text-success">✔ Ya estás inscrito en este evento.</p>
    <?php elseif (!$cupoDisponible): ?>
      <p class="mb-0 text-muted">Ya no hay cupo disponible para este evento.</p>
    <?php elseif (!$usuario): ?>
      <p class="mb-2">Regístrate para inscribirte a este evento.</p>
      <a href="?action=registro" class="btn" style="background:#f7931e;color:#fff;">Regístrate</a>
    <?php elseif ((int) $evento['gratuito'] === 1): ?>
      <button id="btnInscribirse" class="btn" style="background:#f7931e;color:#fff;">Inscribirme</button>
      <div id="inscribirMsg" class="form-text mt-2"></div>
    <?php else: ?>
      <a href="backend/pagos/checkout.php?evento_id=<?= $eventoId ?>" class="btn" style="background:#f7931e;color:#fff;">Inscribirme (de pago)</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($usuario && !$inscrito && $cupoDisponible && (int) $evento['gratuito'] === 1): ?>
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
