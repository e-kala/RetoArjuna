<?php
$actividades = $conn->query(
    'SELECT * FROM actividades WHERE activo = 1 ORDER BY orden ASC, titulo ASC'
)->fetch_all(MYSQLI_ASSOC);
?>
<section class="pf-section">
  <div class="pf-container">
    <div class="pf-section-title">
      <h2>Actividades</h2>
      <p>Esto es lo que estamos haciendo ahora en la comunidad del Reto Arjuna.</p>
    </div>
    <div class="pf-grid-3">
      <?php foreach ($actividades as $a): ?>
        <div class="pf-card">
          <div class="pf-card-icon"><?= htmlspecialchars($a['icono']) ?></div>
          <h3><?= htmlspecialchars($a['titulo']) ?></h3>
          <p><?= nl2br(htmlspecialchars((string) $a['descripcion'])) ?></p>
          <?php if ($a['enlace_url']): ?>
            <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars($a['enlace_url']) ?>" target="_blank" rel="noopener">Más información</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$actividades): ?>
        <p style="color:var(--pf-muted);">Todavía no hay actividades publicadas.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
