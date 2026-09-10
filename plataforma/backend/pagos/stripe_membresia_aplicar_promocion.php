<?php
// Aplica un código de promoción de Stripe a una Subscription ya creada por
// membresia_iniciar.php. Igual que stripe_aplicar_promocion.php (pagos
// únicos): el Payment Element no trae un campo nativo de código de promoción,
// así que se valida el código a mano contra la API y se aplica en dos sitios:
//   1) a la propia Subscription (discounts), para que las renovaciones
//      futuras también lleven el descuento;
//   2) al PaymentIntent de la factura ya generada (amount), porque aplicar
//      un descuento a una Subscription NO ajusta retroactivamente la factura
//      que ya existía al crearla — sin este segundo paso el Payment Element
//      ya montado seguiría cobrando el precio completo en este primer pago.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../error_logger.php';
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
$subscriptionId = trim($_POST['subscription_id'] ?? '');
$paymentIntentId = trim($_POST['payment_intent_id'] ?? '');
$codigo = trim($_POST['codigo_promocion'] ?? '');

if ($subscriptionId === '' || $paymentIntentId === '' || $codigo === '') {
    echo json_encode(['success' => false, 'message' => 'Escribe un código de promoción.']);
    exit;
}

// La Subscription tiene que ser la que ya se creó para ESTE usuario y seguir
// pendiente de pago — se confirma contra la fila de membresia_suscripciones,
// nunca contra un id que venga solo del cliente.
$stmt = $conn->prepare(
    "SELECT membresia_id FROM membresia_suscripciones
     WHERE stripe_subscription_id = ? AND usuario_id = ? AND estado = 'pendiente' LIMIT 1"
);
$stmt->bind_param('si', $subscriptionId, $usuarioPerfilId);
$stmt->execute();
$suscripcionFila = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$suscripcionFila) {
    echo json_encode(['success' => false, 'message' => 'No se pudo aplicar el código a esta suscripción.']);
    exit;
}

$membresiaId = (int) $suscripcionFila['membresia_id'];
$stmt = $conn->prepare('SELECT precio, mostrar_codigo_promocion FROM membresias WHERE id = ?');
$stmt->bind_param('i', $membresiaId);
$stmt->execute();
$membresia = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$membresia) {
    echo json_encode(['success' => false, 'message' => 'Membresía no disponible.']);
    exit;
}
if (!$membresia['mostrar_codigo_promocion']) {
    // Defensa en profundidad — el campo ya está oculto en membresia.php cuando
    // el admin no activó esto, pero nada impide un POST directo a mano.
    echo json_encode(['success' => false, 'message' => 'Esta membresía no admite código de promoción.']);
    exit;
}
$montoOriginal = (float) $membresia['precio'];

$resBusqueda = stripe_api('GET', 'promotion_codes?' . http_build_query(['code' => $codigo, 'active' => 'true', 'limit' => 1]));
$promocion = $resBusqueda['data']['data'][0] ?? null;
if (!$resBusqueda['ok'] || !$promocion || empty($promocion['coupon']['valid'])) {
    echo json_encode(['success' => false, 'message' => 'Ese código no es válido o ya expiró.']);
    exit;
}

$restricciones = $promocion['restrictions'] ?? [];
if (!empty($restricciones['first_time_transaction'])) {
    $stmt = $conn->prepare("SELECT id FROM membresia_suscripciones WHERE usuario_id = ? AND estado <> 'pendiente' LIMIT 1");
    $stmt->bind_param('i', $usuarioPerfilId);
    $stmt->execute();
    $yaTuvoMembresia = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($yaTuvoMembresia) {
        echo json_encode(['success' => false, 'message' => 'Ese código es válido solo para tu primera suscripción.']);
        exit;
    }
}
$montoOriginalCentavos = (int) round($montoOriginal * 100);
if (!empty($restricciones['minimum_amount']) && $montoOriginalCentavos < (int) $restricciones['minimum_amount']) {
    $minimo = number_format($restricciones['minimum_amount'] / 100, 2);
    echo json_encode(['success' => false, 'message' => "Ese código requiere un monto mínimo de \$$minimo MXN."]);
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
// Igual que en pagos únicos: un PaymentIntent no puede cobrar $0.
if ($montoFinalCentavos <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ese código deja el total en $0 — contáctanos para activarla manualmente.']);
    exit;
}

$resDiscount = stripe_api('POST', 'subscriptions/' . urlencode($subscriptionId), [
    'discounts' => [['promotion_code' => $promocion['id']]],
]);
if (!$resDiscount['ok']) {
    $errStripe = $resDiscount['data']['error']['message'] ?? 'sin detalle';
    ra_registrar_error('Stripe subscriptions update (promoción) falló: ' . $errStripe, __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo aplicar el código: ' . $errStripe]);
    exit;
}

$resUpdate = stripe_api('POST', 'payment_intents/' . urlencode($paymentIntentId), ['amount' => $montoFinalCentavos]);
if (!$resUpdate['ok']) {
    $errStripe = $resUpdate['data']['error']['message'] ?? 'sin detalle';
    ra_registrar_error('Stripe payment_intents update (promoción membresía) falló: ' . $errStripe, __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo aplicar el código: ' . $errStripe]);
    exit;
}

$montoFinal = $montoFinalCentavos / 100;
echo json_encode([
    'success' => true,
    'monto_original' => $montoOriginal,
    'monto_final' => $montoFinal,
    'descuento' => $montoOriginal - $montoFinal,
]);
