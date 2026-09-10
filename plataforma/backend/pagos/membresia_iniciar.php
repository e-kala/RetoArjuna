<?php
// Inicia una suscripción con Payment Element embebido — igual que checkout.php
// para pagos únicos, el usuario nunca sale del sitio. Se crea la Subscription
// directo (payment_behavior=default_incomplete) y se devuelve el client_secret
// de su primera factura para montar el Payment Element en membresia.php; SCA/
// reintentos los sigue manejando Stripe.js del lado del cliente (confirmPayment),
// igual que ya hace checkout.php.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../error_logger.php';
require_once __DIR__ . '/../ofertas.php';
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

// El Price ID de Stripe es específico de modo (test/live) — no sirve el de LIVE
// contra la llave TEST ni viceversa (ver STRIPE_SECRET_KEY_PRUEBA en
// stripe_helper.php). En modo prueba se usa el price de prueba de esta
// membresía si ya se configuró uno; si no, se avisa en vez de dejar que
// Stripe truene con un error críptico de "a similar object exists in test/live
// mode" (el mismo bug real que ya se vivió en producción, ver CLAUDE.md).
$priceId = $membresia['stripe_price_id'];
if (stripe_modo_prueba_activo()) {
    if (empty($membresia['stripe_price_id_prueba'])) {
        echo json_encode(['success' => false, 'message' => 'Estás en modo prueba de Stripe, pero esta membresía no tiene un Price ID de prueba configurado. Agrégalo en el admin o desactiva el modo prueba.']);
        exit;
    }
    $priceId = $membresia['stripe_price_id_prueba'];
}
if (usuario_tiene_membresia_activa($usuario['id'])) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes una membresía activa.']);
    exit;
}

// Motor de ofertas (checklist.txt OF01-OF10) — promoción pública/cupón
// también aplican a membresía (decisión confirmada). incluido_membresia/
// solo_miembros/descuento_miembro_pct no tienen sentido para la membresía
// misma, se pasan en false/null a propósito.
$codigoCupon = trim((string) ($_POST['codigo_cupon'] ?? '')) ?: null;
$ofertaItem = [
    'id' => $membresia['id'],
    'precio' => (float) $membresia['precio'],
    'gratuito' => false,
    'incluido_membresia' => false,
    'solo_miembros' => false,
    'descuento_miembro_pct' => null,
    'ya_tiene_acceso' => false,
];
$oferta = resolver_oferta($conn, 'membresia', $ofertaItem, $usuario, $codigoCupon);

// Reutiliza el Customer de Stripe si el usuario ya tuvo una suscripción antes
// (aunque esté cancelada), para no crear un Customer duplicado cada vez. Un
// Customer de Stripe es específico de modo (test/live), igual que el Price ID
// de arriba — nunca se reutiliza uno de otro modo, o Stripe responde "no such
// customer" con la llave que no corresponde.
$modoActual = stripe_modo_prueba_activo() ? 'prueba' : 'live';
$stmt = $conn->prepare(
    "SELECT stripe_customer_id FROM membresia_suscripciones
     WHERE usuario_id = ? AND stripe_customer_id IS NOT NULL AND modo = ?
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->bind_param('is', $usuario['id'], $modoActual);
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

// Si el motor de ofertas ya deja un descuento (cupón/promoción, parcial o al
// 100%), se aplica ANTES de crear la Subscription como un Coupon nativo de
// Stripe (amount_off = descuento en centavos, duration=once — solo afecta la
// primera factura). Tiene que ser así y no parchando el PaymentIntent
// después de crear la Subscription: Stripe RECHAZA cambiar el "amount" de un
// PaymentIntent que nació de una factura ("cannot be used when modifying a
// PaymentIntent that was created by an invoice") — a diferencia del
// PaymentIntent independiente que crea stripe_create_intent.php para pagos
// únicos, que sí acepta el parche (ver aplicar_cupon.php/checkout.php). Con
// el descuento aplicado a la Subscription, la factura ya nace con el monto
// correcto — si queda en $0, Stripe la marca 'paid' sola sin pedir tarjeta.
$discounts = [];
if ($oferta['estado'] === 'oferta' && $oferta['descuento_monto'] > 0) {
    $resCoupon = stripe_api('POST', 'coupons', [
        'amount_off' => (int) round($oferta['descuento_monto'] * 100),
        'currency' => 'mxn',
        'duration' => 'once',
        'name' => 'Cupón/promoción: ' . ($oferta['oferta_nombre'] ?? ''),
    ]);
    if ($resCoupon['ok']) {
        $discounts = [['coupon' => $resCoupon['data']['id']]];
    } else {
        ra_registrar_error('Stripe coupons (membresía) falló: ' . ($resCoupon['data']['error']['message'] ?? 'sin detalle'), __FILE__, __LINE__);
        // No se aborta — cae al flujo normal a precio completo más abajo
        // (peor experiencia, pero no rompe la suscripción).
    }
}

$resSub = stripe_api('POST', 'subscriptions', array_merge([
    'customer' => $stripeCustomerId,
    'items' => [['price' => $priceId]],
    'payment_behavior' => 'default_incomplete',
    'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
    // La versión de la API de Stripe de esta cuenta ya no expone
    // latest_invoice.payment_intent (patrón viejo, todavía en la mayoría de
    // los ejemplos de la documentación) — el client_secret ahora vive en
    // latest_invoice.confirmation_secret. Verificado a mano contra la API
    // real antes de asumir el shape viejo.
    'expand' => ['latest_invoice.confirmation_secret'],
    'metadata' => ['usuario_id' => $usuario['id'], 'membresia_id' => $membresia['id']],
], $discounts ? ['discounts' => $discounts] : []));

if (!$resSub['ok'] || empty($resSub['data']['id'])) {
    $errStripe = $resSub['data']['error']['message'] ?? 'sin detalle';
    ra_registrar_error('Stripe subscriptions falló: ' . $errStripe, __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar la suscripción: ' . $errStripe]);
    exit;
}

$subscriptionId = $resSub['data']['id'];
$cuponId = $oferta['oferta_tipo'] === 'cupon' ? $oferta['oferta_id'] : null;
$promocionId = $oferta['oferta_tipo'] === 'promocion' ? $oferta['oferta_id'] : null;

$confirmationSecret = $resSub['data']['latest_invoice']['confirmation_secret'] ?? null;
$clientSecret = ($confirmationSecret['type'] ?? '') === 'payment_intent' ? ($confirmationSecret['client_secret'] ?? null) : null;
// El id del PaymentIntent no viene aparte en esta respuesta — pero el
// client_secret de un PaymentIntent SIEMPRE tiene el formato
// "{payment_intent_id}_secret_{...}" (documentado y estable en la API de
// Stripe), así que se extrae de ahí en vez de pedir un expand adicional.
$paymentIntentId = $clientSecret && str_contains($clientSecret, '_secret_') ? strstr($clientSecret, '_secret_', true) : null;

// Precio en $0 (promoción/cupón al 100%) — Stripe marca la factura como
// pagada sola, sin pedir tarjeta ni generar PaymentIntent que confirmar.
// Se activa la suscripción de una vez, mismo criterio que ya usa la
// verificación síncrona de membresia.php al volver de un pago con tarjeta.
if ($oferta['acceso_gratis_automatico'] && ($resSub['data']['latest_invoice']['status'] ?? '') === 'paid') {
    $stmt = $conn->prepare(
        "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, cupon_id, promocion_id, metodo, modo, stripe_customer_id, stripe_subscription_id, estado)
         VALUES (?, ?, ?, ?, 'stripe', ?, ?, ?, 'activa')
         ON DUPLICATE KEY UPDATE membresia_id = VALUES(membresia_id), cupon_id = VALUES(cupon_id), promocion_id = VALUES(promocion_id), stripe_customer_id = VALUES(stripe_customer_id), estado = 'activa'"
    );
    $stmt->bind_param('iiiisss', $usuario['id'], $membresia['id'], $cuponId, $promocionId, $modoActual, $stripeCustomerId, $subscriptionId);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'activada_de_inmediato' => true,
        'subscription_id' => $subscriptionId,
    ]);
    exit;
}

if (!$clientSecret || !$paymentIntentId) {
    ra_registrar_error('Stripe subscriptions: respuesta sin confirmation_secret de payment_intent (sub ' . $subscriptionId . ')', __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo preparar el pago de la suscripción.']);
    exit;
}

// Se guarda de inmediato en 'pendiente' — a diferencia del flujo anterior con
// Checkout Session, aquí no hay ningún evento de webhook que avise "se creó
// la suscripción" (el usuario todavía ni ve el Payment Element). invoice.paid
// (ver stripe_webhook.php) la sube a 'activa' cuando de verdad se cobra, sin
// necesitar cambios ahí porque ya actualiza por stripe_subscription_id.
$stmt = $conn->prepare(
    "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, cupon_id, promocion_id, metodo, modo, stripe_customer_id, stripe_subscription_id, estado)
     VALUES (?, ?, ?, ?, 'stripe', ?, ?, ?, 'pendiente')
     ON DUPLICATE KEY UPDATE membresia_id = VALUES(membresia_id), cupon_id = VALUES(cupon_id), promocion_id = VALUES(promocion_id), stripe_customer_id = VALUES(stripe_customer_id)"
);
$stmt->bind_param('iiiisss', $usuario['id'], $membresia['id'], $cuponId, $promocionId, $modoActual, $stripeCustomerId, $subscriptionId);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'client_secret' => $clientSecret,
    'subscription_id' => $subscriptionId,
    'payment_intent_id' => $paymentIntentId,
    'mostrar_codigo_promocion' => (bool) $membresia['mostrar_codigo_promocion'],
    'precio_regular' => $oferta['precio_regular'],
    'precio_final' => $oferta['precio_final'],
    'oferta_nombre' => $oferta['oferta_nombre'],
]);
