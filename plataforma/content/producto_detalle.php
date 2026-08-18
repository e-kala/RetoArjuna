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
$adquirido = $usuario ? usuario_compro_producto($usuario['id'], $productoId) : false;
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=tienda" class="d-inline-block mb-3">&larr; Volver a la tienda</a>
  <div class="row">
    <div class="col-md-5 mb-3">
      <img src="<?= htmlspecialchars($producto['imagen'] ?: BASE_URL . '/../banner.png') ?>" class="img-fluid rounded" alt="">
    </div>
    <div class="col-md-7">
      <h1><?= htmlspecialchars($producto['nombre']) ?></h1>
      <span class="badge mb-3" style="background:#f7931e;"><?= $producto['tipo'] === 'fisico' ? 'Físico' : 'Digital' ?></span>
      <p><?= nl2br(htmlspecialchars((string) $producto['descripcion'])) ?></p>

      <?php if ($adquirido): ?>
        <p class="h5 text-success mb-3">✔ Adquirido</p>
        <?php if ($producto['tipo'] === 'digital'): ?>
          <?php if ($producto['archivo_digital']): ?>
            <?php $enlaceDescarga = preg_match('~^https?://~i', $producto['archivo_digital']) ? $producto['archivo_digital'] : BASE_URL . '/' . $producto['archivo_digital']; ?>
            <a href="<?= htmlspecialchars($enlaceDescarga) ?>" class="btn btn-outline-success" target="_blank">Descargar</a>
          <?php else: ?>
            <p class="text-muted">El archivo estará disponible pronto — revisa <a href="<?= BASE_URL ?>/panel/index.php?action=mis_compras">Mis compras</a> más tarde.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted">Revisa el estado de tu envío en <a href="<?= BASE_URL ?>/panel/index.php?action=mis_compras">Mis compras</a>.</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="h4">$<?= number_format((float) $producto['precio'], 2) ?> MXN</p>
        <?php if ($agotado): ?>
          <p class="text-muted">Agotado por el momento.</p>
        <?php elseif (!$usuario): ?>
          <p class="mb-2">Regístrate para comprar este producto.</p>
          <a href="?action=registro" class="btn" style="background:#f7931e;color:#fff;">Regístrate</a>
        <?php else: ?>
          <a href="backend/pagos/checkout.php?producto_id=<?= $productoId ?>" class="btn" style="background:#f7931e;color:#fff;">Comprar</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
