<?php
// Aplica un código de promoción de Stripe a un PaymentIntent ya creado por
// stripe_create_intent.php (pagos únicos de curso/evento/producto). El
// Payment Element de Stripe.js no trae un campo nativo de código de
// promoción para PaymentIntents sueltos (a diferencia de Checkout Session,
// que sí lo trae y ya se usa en membresia_iniciar.php) — este endpoint es
// el reemplazo casero: valida el código contra la API de Stripe, calcula el
// descuento nosotros mismos, y actualiza el monto del PaymentIntent y de la
// fila de `pagos` ya insertada, sin crear una fila nueva.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../error_logger.php';
require_once __DIR__ . '/item_resolver.php';
require_once __DIR__ . '/stripe_helper.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

if (!config_esta_lista(STRIPE_SECRET_KEY)) {
    echo json_encode(['success' => false, 'message' => 'Los pagos con tarjeta aún no están configurados.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$paymentIntentId = trim($_POST['payment_intent_id'] ?? '');
$codigo = trim($_POST['codigo_promocion'] ?? '');
$cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));

if ($paymentIntentId === '' || $codigo === '') {
    echo json_encode(['success' => false, 'message' => 'Escribe un código de promoción.']);
    exit;
}
// El PaymentIntent tiene que ser el que ya se creó para ESTE usuario en esta
// compra — se confirma contra la fila de `pagos`, nunca contra un id que
// venga solo del cliente, para no dejar que alguien reduzca el monto de un
// PaymentIntent ajeno.
$stmt = $conn->prepare("SELECT id FROM pagos WHERE transaccion_id = ? AND usuario_id = ? AND estado = 'pendiente' LIMIT 1");
$stmt->bind_param('si', $paymentIntentId, $usuarioPerfilId);
$stmt->execute();
$pagoFila = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pagoFila) {
    echo json_encode(['success' => false, 'message' => 'No se pudo aplicar el código a este pago.']);
    exit;
}

$item = resolver_item_pago($conn, $_POST, $usuarioPerfilId);
if (!$item || !$item['activo'] || $item['gratuito']) {
    echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
    exit;
}
if (!$item['mostrar_codigo_promocion']) {
    // Defensa en profundidad — el campo ya está oculto en checkout.php cuando
    // el admin no activó esto para el artículo, pero nada impide un POST
    // directo a mano si no se revalida aquí también.
    echo json_encode(['success' => false, 'message' => 'Este artículo no admite código de promoción.']);
    exit;
}
if ($item['tipo'] !== 'producto') {
    $cantidad = 1;
}
$montoOriginal = $item['precio'] * $cantidad;

$resBusqueda = stripe_api('GET', 'promotion_codes?' . http_build_query(['code' => $codigo, 'active' => 'true', 'limit' => 1]));
$promocion = $resBusqueda['data']['data'][0] ?? null;
if (!$resBusqueda['ok'] || !$promocion || empty($promocion['coupon']['valid'])) {
    echo json_encode(['success' => false, 'message' => 'Ese código no es válido o ya expiró.']);
    exit;
}

$restricciones = $promocion['restrictions'] ?? [];
if (!empty($restricciones['first_time_transaction'])) {
    $stmt = $conn->prepare("SELECT id FROM pagos WHERE usuario_id = ? AND estado = 'confirmado' LIMIT 1");
    $stmt->bind_param('i', $usuarioPerfilId);
    $stmt->execute();
    $yaComprado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($yaComprado) {
        echo json_encode(['success' => false, 'message' => 'Ese código es válido solo para tu primera compra.']);
        exit;
    }
}
$montoOriginalCentavos = (int) round($montoOriginal * 100);
if (!empty($restricciones['minimum_amount']) && $montoOriginalCentavos < (int) $restricciones['minimum_amount']) {
    $minimo = number_format($restricciones['minimum_amount'] / 100, 2);
    echo json_encode(['success' => false, 'message' => "Ese código requiere una compra mínima de \$$minimo MXN."]);
    exit;
}

$coupon = $promocion['coupon'];
if (!empty($coupon['percent_off'])) {
    $montoFinalCentavos = (int) round($montoOriginalCentavos * (1 - $coupon['percent_off'] / 100));
} elseif (!empty($coupon['amount_off'])) {
    if (strtolower((string) ($coupon['currency'] ?? '')) !== 'mxn') {
        echo json_encode(['success' => false, 'message' => 'Ese código no aplica en pesos mexicanos.']);
        exit;
    }
    $montoFinalCentavos = max(0, $montoOriginalCentavos - (int) $coupon['amount_off']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ese código no tiene un descuento configurado.']);
    exit;
}
// Stripe rechaza un PaymentIntent con monto 0 (no hay nada que cobrar) — un
// código que deja el total en $0 no tiene forma de cobrarse con tarjeta.
if ($montoFinalCentavos <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ese código deja el total en $0 — usa la pestaña de transferencia o contáctanos.']);
    exit;
}

$resUpdate = stripe_api('POST', 'payment_intents/' . urlencode($paymentIntentId), ['amount' => $montoFinalCentavos]);
if (!$resUpdate['ok']) {
    $errStripe = $resUpdate['data']['error']['message'] ?? 'sin detalle';
    ra_registrar_error('Stripe payment_intents update (promoción) falló: ' . $errStripe, __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo aplicar el código: ' . $errStripe]);
    exit;
}

$montoFinal = $montoFinalCentavos / 100;
$stmt = $conn->prepare("UPDATE pagos SET monto = ? WHERE transaccion_id = ? AND usuario_id = ?");
$stmt->bind_param('dsi', $montoFinal, $paymentIntentId, $usuarioPerfilId);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'monto_original' => $montoOriginal,
    'monto_final' => $montoFinal,
    'descuento' => $montoOriginal - $montoFinal,
]);
