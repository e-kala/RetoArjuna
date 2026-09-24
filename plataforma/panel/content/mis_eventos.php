<?php
// "Mis eventos" — pestaña propia (2026-09-08), antes era un bloque de solo 3
// eventos dentro de "Mi aprendizaje". Al ser su propia pestaña ya no se
// limita a 3, muestra el historial completo de inscripciones.
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT e.id, e.titulo, e.slug, e.tipo, e.ubicacion, e.fecha_inicio, e.video_grabado_url, ei.estado,
            cert.codigo AS codigo_reconocimiento,
            (SELECT COUNT(*) FROM lecciones WHERE evento_id = e.id AND estado_publicacion = 'publicado') AS total_lecciones,
            (SELECT COUNT(*) FROM progreso WHERE evento_id = e.id AND usuario_id = ei.usuario_id AND completado = 1) AS lecciones_completadas
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
    <?php
    $esPasadoMisEventos = strtotime($ev['fecha_inicio']) < time();
    $asistioMisEventos = $ev['estado'] === 'asistio';
    $totalLeccionesMisEventos = (int) ($ev['total_lecciones'] ?? 0);
    $porcentajeMisEventos = $totalLeccionesMisEventos > 0 ? (int) round((int) ($ev['lecciones_completadas'] ?? 0) / $totalLeccionesMisEventos * 100) : 0;
    // Mismo criterio que eventos_catalogo.php: futuro/en curso siempre "Ver
    // evento"; pasado con asistencia en vivo siempre "Volver a ver..." (gana
    // sobre el progreso); pasado sin asistencia pero con grabación decide por
    // progreso; sin grabación cae a "Ver detalle".
    if (!$esPasadoMisEventos) {
        $labelCtaMisEventos = 'Ver evento';
    } elseif ($asistioMisEventos) {
        $labelCtaMisEventos = 'Volver a ver grabaciones y materiales';
    } elseif ($ev['video_grabado_url']) {
        $labelCtaMisEventos = $porcentajeMisEventos > 0 ? 'Seguir viendo' : 'Comenzar a ver';
    } else {
        $labelCtaMisEventos = 'Ver detalle';
    }
    ?>
    <div class="col">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <h3 class="h6 fw-bold"><?= htmlspecialchars($ev['titulo']) ?></h3>
          <p class="small text-muted mb-2">
            <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
            · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
          </p>
          <span class="badge rounded-pill mb-2" style="background:<?= $asistioMisEventos ? '#e6f4ea' : '#e6f0fb' ?>;color:<?= $asistioMisEventos ? '#1e7d3c' : '#1c5fa8' ?>;">
            <?= $asistioMisEventos ? 'Asististe' : 'Inscrito' ?>
          </span>
          <?php if ($esPasadoMisEventos && !$asistioMisEventos && $totalLeccionesMisEventos > 0): ?>
            <div class="progress mb-1" style="height:6px;">
              <div class="progress-bar" style="width:<?= $porcentajeMisEventos ?>%;background:#F6C500;"></div>
            </div>
            <p class="small text-muted mb-2"><?= $porcentajeMisEventos ?>% completado</p>
          <?php endif; ?>
          <div class="d-flex gap-2">
            <?php if ($ev['codigo_reconocimiento']): ?>
              <a href="../certificado.php?codigo=<?= urlencode($ev['codigo_reconocimiento']) ?>" class="btn btn-outline-secondary btn-sm">Reconocimiento</a>
            <?php endif; ?>
            <a href="../index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>&ver=1" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;"><?= htmlspecialchars($labelCtaMisEventos) ?></a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$misEventos): ?>
    <p class="text-muted">Aún no te has inscrito a ningún evento. <a href="../index.php?action=eventos" style="color:#B8860B;">Explora los próximos eventos</a>.</p>
  <?php endif; ?>
</div>
