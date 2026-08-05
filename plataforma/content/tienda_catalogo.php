<?php
$productos = $conn->query(
    'SELECT id, tipo, nombre, slug, descripcion, precio, imagen, stock
     FROM productos WHERE activo = 1 ORDER BY created_at DESC'
)->fetch_all(MYSQLI_ASSOC);
?>
<section class="pf-section">
  <div class="pf-container">
    <div class="pf-section-title">
      <h2>Tienda</h2>
      <p>Merchandise e infoproductos del Reto Arjuna.</p>
    </div>
    <div class="pf-grid-3">
      <?php foreach ($productos as $producto): ?>
        <?php $agotado = $producto['tipo'] === 'fisico' && $producto['stock'] !== null && (int) $producto['stock'] <= 0; ?>
        <div class="pf-course-card">
          <?php if ($producto['imagen']): ?>
            <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="">
          <?php endif; ?>
          <div class="pf-course-card-body">
            <h3><?= htmlspecialchars($producto['nombre']) ?> <?= $producto['tipo'] === 'digital' ? '<span class="pf-badge-soon">Digital</span>' : '' ?></h3>
            <p><?= htmlspecialchars(mb_strimwidth((string) $producto['descripcion'], 0, 100, '…')) ?></p>
            <div class="pf-course-card-footer">
              <span class="pf-price-tag">$<?= number_format((float) $producto['precio'], 2) ?> MXN</span>
              <?php if ($agotado): ?>
                <span class="pf-badge-soon">Agotado</span>
              <?php else: ?>
                <a href="?action=producto&amp;slug=<?= urlencode($producto['slug']) ?>" class="pf-btn pf-btn-outline">Ver producto</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$productos): ?>
        <p style="color:var(--pf-muted);">Aún no hay productos publicados.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
