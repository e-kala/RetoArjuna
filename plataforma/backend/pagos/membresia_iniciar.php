<?php
// Inicia una suscripción vía Stripe Checkout Session (modo "subscription"): a
// diferencia del Payment Element de checkout.php (pagos únicos), aquí se redirige
// al usuario a una página alojada por Stripe — mucho menos código y Stripe se
// encarga de SCA/reintentos/facturas, razonable para el primer producto recurrente.
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

$usuario = current_user();
$membresiaId = (int) ($_POST['membresia_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM membresias WHERE id = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('i', $membresiaId);
$stmt->execute();
$membresia = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$membresia) {
    echo json_encode(['success' => false, 'message' => 'Membresía no disponible.']);
    exit;
}
if (!$membresia['stripe_price_id']) {
    echo json_encode(['success' => false, 'message' => 'Esta membresía todavía no está configurada. Intenta más tarde.']);
    exit;
}
if (usuario_tiene_membresia_activa($usuario['id'])) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes una membresía activa.']);
    exit;
}

// Reutiliza el Customer de Stripe si el usuario ya tuvo una suscripción antes
// (aunque esté cancelada), para no crear un Customer duplicado cada vez.
$stmt = $conn->prepare(
    "SELECT stripe_customer_id FROM membresia_suscripciones
     WHERE usuario_id = ? AND stripe_customer_id IS NOT NULL
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->bind_param('i', $usuario['id']);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stripeCustomerId = $fila['stripe_customer_id'] ?? null;

if (!$stripeCustomerId) {
    $resCustomer = stripe_api('POST', 'customers', [
        'email' => $usuario['email'],
        'name' => $usuario['username'],
        'metadata' => ['usuario_id' => $usuario['id']],
    ]);
    if (!$resCustomer['ok']) {
        $errStripe = $resCustomer['data']['error']['message'] ?? 'sin detalle';
        ra_registrar_error('Stripe customers falló: ' . $errStripe, __FILE__, __LINE__);
        echo json_encode(['success' => false, 'message' => 'No se pudo iniciar la suscripción: ' . $errStripe]);
        exit;
    }
    $stripeCustomerId = $resCustomer['data']['id'];
}

$urlBase = SITE_URL . '/index.php?action=membresia';
$resSesion = stripe_api('POST', 'checkout/sessions', [
    'mode' => 'subscription',
    'customer' => $stripeCustomerId,
    'line_items' => [
        ['price' => $membresia['stripe_price_id'], 'quantity' => 1],
    ],
    'success_url' => $urlBase . '&suscripcion=exito',
    'cancel_url' => $urlBase . '&suscripcion=cancelada',
    'metadata' => ['usuario_id' => $usuario['id'], 'membresia_id' => $membresia['id']],
]);

if (!$resSesion['ok'] || empty($resSesion['data']['url'])) {
    $errStripe = $resSesion['data']['error']['message'] ?? 'sin detalle';
    ra_registrar_error('Stripe checkout/sessions falló: ' . $errStripe, __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar la suscripción: ' . $errStripe]);
    exit;
}

echo json_encode(['success' => true, 'url' => $resSesion['data']['url']]);
