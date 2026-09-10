<?php
require_once __DIR__ . '/../backend/ofertas.php';
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

$codigoCupon = $_GET['cupon'] ?? ($_SESSION['cupon_pendiente'] ?? null);
$ofertaItem = [
    'id' => $productoId,
    'precio' => (float) $producto['precio'],
    'gratuito' => false,
    'incluido_membresia' => false,
    'solo_miembros' => false,
    'descuento_miembro_pct' => null,
    'ya_tiene_acceso' => $adquirido,
];
$oferta = resolver_oferta($conn, 'producto', $ofertaItem, $usuario, $codigoCupon);
$volverActual = urlencode((string) ($_SERVER['REQUEST_URI'] ?? ''));
// Mismo mecanismo que curso_detalle.php/evento_detalle.php: &auto=1 en el
// volver de crear cuenta/login ahorra el clic extra en "Comprar" al
// regresar — aquí siempre manda a checkout.php (no hay un botón "gratis"
// inline en esta página, ni para acceso_gratis_automatico: checkout.php ya
// resuelve ambos casos).
$volverActualConAuto = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '') . (str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ? '&' : '?') . 'auto=1');
if ($usuario && !$adquirido && !$agotado && ($_GET['auto'] ?? '') === '1') {
    header('Location: backend/pagos/checkout.php?producto_id=' . $productoId . ($codigoCupon ? '&cupon=' . urlencode($codigoCupon) : ''));
    exit;
}
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
        <?php if ($oferta['estado'] === 'gratuito'): ?>
          <p class="h4">Gratis</p>
        <?php elseif ($oferta['estado'] === 'oferta'): ?>
          <p class="h5 text-decoration-line-through text-muted mb-0">$<?= number_format($oferta['precio_regular'], 2) ?> MXN</p>
          <p class="h4"><?= $oferta['precio_final'] > 0 ? '$' . number_format($oferta['precio_final'], 2) . ' MXN' : 'Gratis' ?> <span class="badge bg-secondary"><?= htmlspecialchars((string) $oferta['oferta_nombre']) ?></span></p>
        <?php else: ?>
          <p class="h4">$<?= number_format((float) $producto['precio'], 2) ?> MXN</p>
        <?php endif; ?>
        <?php if ($agotado): ?>
          <p class="text-muted">Agotado por el momento.</p>
        <?php elseif (!$usuario): ?>
          <p class="mb-2">Crea tu Cuenta Arjuna para <?= $oferta['acceso_gratis_automatico'] ? 'obtener' : 'comprar' ?> este producto — al terminar, vas directo <?= $oferta['acceso_gratis_automatico'] ? 'a tu acceso' : 'al pago' ?>, sin pasos extra.</p>
          <a href="?action=registro&volver=<?= $volverActualConAuto ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn" style="background:#f7931e;color:#fff;"><?= $oferta['acceso_gratis_automatico'] ? 'Inscribirme gratis' : 'Comprar' ?></a>
        <?php else: ?>
          <a href="backend/pagos/checkout.php?producto_id=<?= $productoId ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn" style="background:#f7931e;color:#fff;"><?= $oferta['acceso_gratis_automatico'] ? 'Inscribirme gratis' : 'Comprar' ?></a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
