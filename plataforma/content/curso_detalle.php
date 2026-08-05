<?php
$slug = $_GET['slug'] ?? '';
$stmt = $conn->prepare('SELECT * FROM cursos WHERE slug = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso) {
    echo '<div class="container" style="margin-top:143px;"><p>Curso no encontrado.</p></div>';
    return;
}

$cursoId = (int) $curso['id'];
$stmt = $conn->prepare('SELECT * FROM lecciones WHERE curso_id = ? ORDER BY orden');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$usuario = current_user();
$tieneAcceso = $usuario ? usuario_tiene_acceso_curso($usuario['id'], $cursoId) : false;

$completadas = [];
if ($usuario) {
    $stmt = $conn->prepare('SELECT leccion_id FROM progreso WHERE usuario_id = ? AND curso_id = ? AND completado = 1');
    $stmt->bind_param('ii', $usuario['id'], $cursoId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $completadas[(int) $row['leccion_id']] = true;
    }
    $stmt->close();
}
$porcentaje = count($lecciones) > 0 ? (int) round(count($completadas) / count($lecciones) * 100) : 0;
$mostrarBienvenida = $tieneAcceso && ($_GET['bienvenida'] ?? '') === '1';
$primeraLeccion = $lecciones[0] ?? null;
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=cursos" class="d-inline-block mb-3">&larr; Volver al catálogo</a>

  <?php if ($mostrarBienvenida): ?>
    <div class="card p-4 mb-4" style="background:#fff3e0;border:1px solid #f7931e;">
      <h2 class="h4 mb-2">🎉 ¡Bienvenido a <?= htmlspecialchars($curso['titulo']) ?>!</h2>
      <p class="mb-3">Tu compra fue confirmada y ya tienes acceso completo a este curso.</p>
      <?php if ($primeraLeccion): ?>
        <a href="?action=leccion&id=<?= (int) $primeraLeccion['id'] ?>" class="btn" style="background:#f7931e;color:#fff;">Comenzar curso</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <h1><?= htmlspecialchars($curso['titulo']) ?></h1>
  <p><?= nl2br(htmlspecialchars((string) $curso['descripcion'])) ?></p>
  <p>
    <span class="badge bg-secondary"><?= htmlspecialchars($curso['nivel']) ?></span>
    <?php if ((int) $curso['gratuito'] === 1): ?>
      <span class="badge" style="background:#f7931e;">Gratuito</span>
    <?php elseif ($tieneAcceso): ?>
      <span class="badge bg-success">Ya tienes acceso</span>
    <?php else: ?>
      <span class="badge" style="background:#f7931e;">$<?= number_format((float) $curso['precio'], 2) ?> MXN</span>
    <?php endif; ?>
  </p>

  <?php if ($tieneAcceso): ?>
    <div class="progress mb-3" style="height: 8px;">
      <div class="progress-bar" style="width: <?= $porcentaje ?>%; background:#f7931e;"></div>
    </div>
    <p class="text-muted small"><?= $porcentaje ?>% completado</p>
    <a href="../foro/curso.php?curso_id=<?= $cursoId ?>" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-chat-square-text"></i> Discutir este curso en el foro</a>
  <?php endif; ?>

  <div class="list-group mb-4">
    <?php foreach ($lecciones as $leccion): ?>
      <?php $desbloqueada = $tieneAcceso || (int) $leccion['vista_previa'] === 1; ?>
      <?php if ($desbloqueada): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center">
          <a href="?action=leccion&id=<?= (int) $leccion['id'] ?>" class="text-decoration-none flex-grow-1">
            <?php if (!empty($completadas[(int) $leccion['id']])): ?><i class="bi bi-check-circle-fill text-success"></i><?php endif; ?>
            <?= htmlspecialchars($leccion['titulo']) ?>
          </a>
          <?php if (!$tieneAcceso): ?><span class="badge bg-info me-2">Demo</span><?php endif; ?>
          <?php if ($tieneAcceso): ?>
            <a href="../foro/curso.php?curso_id=<?= $cursoId ?>&leccion_id=<?= (int) $leccion['id'] ?>" class="text-muted small" title="Discutir esta lección en el foro">
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

  <?php if (!$tieneAcceso && (int) $curso['gratuito'] === 0): ?>
    <div class="card p-3">
      <?php if (!$usuario): ?>
        <p class="mb-2">Regístrate para comprar este curso.</p>
        <a href="?action=registro" class="btn" style="background:#f7931e;color:#fff;">Regístrate</a>
      <?php else: ?>
        <a href="backend/pagos/checkout.php?curso_id=<?= $cursoId ?>" class="btn" style="background:#f7931e;color:#fff;">Comprar curso completo</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
