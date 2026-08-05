<?php
$slug = $_GET['slug'] ?? '';
$stmt = $conn->prepare('SELECT * FROM productos WHERE slug = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    echo '<div class="container" style="margin-top:143px;"><p>Producto no encontrado.</p></div>';
    return;
}

$productoId = (int) $producto['id'];
$usuario = current_user();
$agotado = $producto['tipo'] === 'fisico' && $producto['stock'] !== null && (int) $producto['stock'] <= 0;
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=tienda" class="d-inline-block mb-3">&larr; Volver a la tienda</a>
  <div class="row">
    <?php if ($producto['imagen']): ?>
      <div class="col-md-5 mb-3">
        <img src="<?= htmlspecialchars($producto['imagen']) ?>" class="img-fluid rounded" alt="">
      </div>
    <?php endif; ?>
    <div class="col-md-7">
      <h1><?= htmlspecialchars($producto['nombre']) ?></h1>
      <span class="badge mb-3" style="background:#f7931e;"><?= $producto['tipo'] === 'fisico' ? 'Físico' : 'Digital' ?></span>
      <p><?= nl2br(htmlspecialchars((string) $producto['descripcion'])) ?></p>
      <p class="h4">$<?= number_format((float) $producto['precio'], 2) ?> MXN</p>

      <?php if ($agotado): ?>
        <p class="text-muted">Agotado por el momento.</p>
      <?php elseif (!$usuario): ?>
        <p class="mb-2">Regístrate para comprar este producto.</p>
        <a href="?action=registro" class="btn" style="background:#f7931e;color:#fff;">Regístrate</a>
      <?php else: ?>
        <a href="backend/pagos/checkout.php?producto_id=<?= $productoId ?>" class="btn" style="background:#f7931e;color:#fff;">Comprar</a>
      <?php endif; ?>
    </div>
  </div>
</div>
