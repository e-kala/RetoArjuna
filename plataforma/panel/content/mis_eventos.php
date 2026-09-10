<?php
// "Mis eventos" — pestaña propia (2026-09-08), antes era un bloque de solo 3
// eventos dentro de "Mi aprendizaje". Al ser su propia pestaña ya no se
// limita a 3, muestra el historial completo de inscripciones.
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT e.titulo, e.slug, e.tipo, e.ubicacion, e.fecha_inicio, ei.estado,
            cert.codigo AS codigo_reconocimiento
     FROM evento_inscripciones ei
     JOIN eventos e ON e.id = ei.evento_id
     LEFT JOIN certificados cert ON cert.evento_id = e.id AND cert.usuario_id = ei.usuario_id
     WHERE ei.usuario_id = ? AND ei.estado <> 'cancelado'
     ORDER BY e.fecha_inicio DESC"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$misEventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="h3 fw-bold mb-0">Mis eventos</h1>
  <a href="../index.php?action=eventos" class="btn btn-outline-secondary btn-sm">Ver próximos eventos</a>
</div>
<div class="row row-cols-1 row-cols-md-3 g-4">
  <?php foreach ($misEventos as $ev): ?>
    <div class="col">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <h3 class="h6 fw-bold"><?= htmlspecialchars($ev['titulo']) ?></h3>
          <p class="small text-muted mb-2">
            <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
            · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
          </p>
          <span class="badge rounded-pill mb-2" style="background:<?= $ev['estado'] === 'asistio' ? '#e6f4ea' : '#e6f0fb' ?>;color:<?= $ev['estado'] === 'asistio' ? '#1e7d3c' : '#1c5fa8' ?>;">
            <?= $ev['estado'] === 'asistio' ? 'Asististe' : 'Inscrito' ?>
          </span>
          <div class="d-flex gap-2">
            <?php if ($ev['codigo_reconocimiento']): ?>
              <a href="../certificado.php?codigo=<?= urlencode($ev['codigo_reconocimiento']) ?>" class="btn btn-outline-secondary btn-sm">Reconocimiento</a>
            <?php endif; ?>
            <a href="../index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;">Ver evento</a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$misEventos): ?>
    <p class="text-muted">Aún no te has inscrito a ningún evento. <a href="../index.php?action=eventos" style="color:#B8860B;">Explora los próximos eventos</a>.</p>
  <?php endif; ?>
</div>
