<?php
$productos = $conn->query(
    'SELECT id, tipo, nombre, slug, descripcion, precio, imagen, stock
     FROM productos WHERE activo = 1 ORDER BY created_at DESC'
)->fetch_all(MYSQLI_ASSOC);
$usuarioTienda = current_user();
?>
<section class="py-5">
  <div class="container py-4">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Tienda</h2>
      <p class="text-muted">Merchandise e infoproductos del Reto Arjuna.</p>
    </div>
    <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
      <?php foreach ($productos as $producto): ?>
        <?php
        $agotado = $producto['tipo'] === 'fisico' && $producto['stock'] !== null && (int) $producto['stock'] <= 0;
        $adquirido = $usuarioTienda && usuario_compro_producto($usuarioTienda['id'], (int) $producto['id']);
        ?>
        <div class="col" style="max-width:360px;">
          <div class="card h-100 border-0 shadow-sm">
            <img src="<?= htmlspecialchars($producto['imagen'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
            <div class="card-body d-flex flex-column">
              <h3 class="h5 fw-bold">
                <?= htmlspecialchars($producto['nombre']) ?>
                <?php if ($producto['tipo'] === 'digital'): ?><span class="badge bg-secondary align-middle">Digital</span><?php endif; ?>
              </h3>
              <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth((string) $producto['descripcion'], 0, 100, '…')) ?></p>
              <div class="d-flex align-items-center justify-content-between mt-2">
                <?php if ($adquirido): ?>
                  <span class="badge rounded-pill" style="background:#e6f4ea;color:#1e7d3c;">Adquirido</span>
                <?php else: ?>
                  <span class="badge rounded-pill" style="background:#fdeaea;color:#c0392b;">$<?= number_format((float) $producto['precio'], 2) ?> MXN</span>
                <?php endif; ?>
                <?php if ($agotado && !$adquirido): ?>
                  <span class="badge bg-secondary">Agotado</span>
                <?php else: ?>
                  <a href="?action=producto&amp;slug=<?= urlencode($producto['slug']) ?>" class="btn btn-outline-secondary btn-sm">Ver producto</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$productos): ?>
        <p class="text-muted text-center">Aún no hay productos publicados.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
