<?php
$eventos = $conn->query(
    "SELECT id, titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, precio, imagen_portada, gratuito
     FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW() ORDER BY fecha_inicio ASC"
)->fetch_all(MYSQLI_ASSOC);
?>
<section class="pf-section">
  <div class="pf-container">
    <div class="pf-section-title">
      <h2>Próximos eventos</h2>
      <p>Encuentros en línea y presenciales de la comunidad Reto Arjuna.</p>
    </div>
    <div class="pf-grid-3">
      <?php foreach ($eventos as $evento): ?>
        <div class="pf-course-card">
          <?php if ($evento['imagen_portada']): ?>
            <img src="<?= htmlspecialchars($evento['imagen_portada']) ?>" alt="">
          <?php endif; ?>
          <div class="pf-course-card-body">
            <h3><?= htmlspecialchars($evento['titulo']) ?></h3>
            <p class="mb-1" style="color:var(--pf-muted);font-size:13px;">
              <?= $evento['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $evento['ubicacion']) ?>
              · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($evento['fecha_inicio']))) ?>
            </p>
            <p><?= htmlspecialchars(mb_strimwidth((string) $evento['descripcion'], 0, 100, '…')) ?></p>
            <div class="pf-course-card-footer">
              <span class="pf-price-tag">
                <?= (int) $evento['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $evento['precio'], 2) . ' MXN' ?>
              </span>
              <a href="?action=evento&amp;slug=<?= urlencode($evento['slug']) ?>" class="pf-btn pf-btn-outline">Ver evento</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$eventos): ?>
        <p style="color:var(--pf-muted);">No hay eventos próximos por ahora.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
