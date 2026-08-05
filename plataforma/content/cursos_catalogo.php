<?php
$cursos = $conn->query(
    'SELECT id, titulo, slug, descripcion, nivel, precio, imagen_portada, gratuito
     FROM cursos WHERE activo = 1 ORDER BY gratuito DESC, created_at DESC'
)->fetch_all(MYSQLI_ASSOC);
$usuarioCatalogo = current_user();
?>
<section class="pf-section">
  <div class="pf-container">
    <div class="pf-section-title">
      <h2>Cursos</h2>
      <p>Aprende a tu ritmo, con certificado al completar cada curso.</p>
    </div>
    <div class="pf-grid-3">
      <?php foreach ($cursos as $curso): ?>
        <div class="pf-course-card">
          <?php if ($curso['imagen_portada']): ?>
            <img src="<?= htmlspecialchars($curso['imagen_portada']) ?>" alt="">
          <?php endif; ?>
          <div class="pf-course-card-body">
            <h3><?= htmlspecialchars($curso['titulo']) ?></h3>
            <p><?= htmlspecialchars(mb_strimwidth((string) $curso['descripcion'], 0, 120, '…')) ?></p>
            <div class="pf-course-card-footer">
              <?php if ((int) $curso['gratuito'] === 1): ?>
                <span class="pf-price-tag">Gratuito</span>
              <?php elseif ($usuarioCatalogo && usuario_tiene_acceso_curso($usuarioCatalogo['id'], (int) $curso['id'])): ?>
                <span class="pf-price-tag">Ya inscrito</span>
              <?php else: ?>
                <span class="pf-price-tag">$<?= number_format((float) $curso['precio'], 2) ?> MXN</span>
              <?php endif; ?>
              <a href="?action=curso&amp;slug=<?= urlencode($curso['slug']) ?>" class="pf-btn pf-btn-outline">Ver curso</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$cursos): ?>
        <p style="color:var(--pf-muted);">Aún no hay cursos publicados.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
