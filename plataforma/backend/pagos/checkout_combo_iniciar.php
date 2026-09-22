<?php
// Combo Membresía + Evento/Curso — para un evento/curso donde ser miembro
// da algún beneficio (incluido_membresia=1, acceso gratis, o
// descuento_miembro_pct>0, precio con descuento) comprado por alguien que
// todavía no es miembro, este endpoint arma UNA Subscription de Stripe
// (recurrente, se renueva sola cada mes — decisión confirmada con el
// usuario, no es un cobro de un solo mes) cuya PRIMERA factura incluye
// también el precio del evento/curso (ya con su descuento de miembro
// aplicado, si aplica) como cargo único (add_invoice_items). Mismo patrón
// que membresia_iniciar.php (Subscription con payment_behavior=default_incomplete
// + Payment Element embebido), pero sin tocar ese archivo: no arriesga el
// flujo de "solo membresía" que ya funciona en producción.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../error_logger.php';
require_once __DIR__ . '/../ofertas.php';
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

$usuario = current_user();
$usuarioPerfilId = (int) $usuario['id'];
$tipo = (string) ($_POST['tipo'] ?? '');
$itemId = (int) ($_POST['item_id'] ?? 0);
$codigoCupon = trim((string) ($_POST['codigo_cupon'] ?? '')) ?: null;

if (!in_array($tipo, ['evento', 'curso'], true) || !$itemId) {
    echo json_encode(['success' => false, 'message' => 'Artículo no válido para el combo.']);
    exit;
}

// El cupón del combo es de la MEMBRESÍA (ver más abajo, resolver_oferta con
// tipo='membresia') — resolver_item_pago() no debe intentar aplicarlo al
// evento/curso, así que se resuelve sin cupón propio.
$paramsItem = $tipo === 'evento' ? ['evento_id' => $itemId] : ['curso_id' => $itemId];
$item = resolver_item_pago($conn, $paramsItem, $usuarioPerfilId, null);

// El combo se ofrece si ser miembro le da CUALQUIER beneficio a este ítem —
// acceso gratis (incluido_membresia) o descuento (descuento_miembro_pct) —
// mismo criterio que checkout.php usa para decidir si mostrar la opción
// (ver $mostrarCombo ahí). Si no hay ningún beneficio, no hay nada que
// promocionar con este combo específico.
$tieneBeneficioMembresia = $item && ($item['incluido_membresia'] === true || (float) ($item['descuento_miembro_pct'] ?? 0) > 0);
if (!$item || !$item['activo'] || !$tieneBeneficioMembresia
    || in_array($item['estado'], ['acceso', 'incluido_membresia', 'exclusivo_bloqueado', 'gratuito'], true)) {
    echo json_encode(['success' => false, 'message' => 'Este artículo no está disponible para el combo de membresía.']);
    exit;
}

// resolver_item_pago() calcula precio_final asumiendo el estado ACTUAL del
// usuario — que en el combo siempre es "no miembro todavía" (ver el guard
// de más abajo), así que nunca aplica el beneficio real: ni el $0 de
// incluido_membresia, ni el % de descuento_miembro_pct (ambos casos, en
// ofertas.php, exigen $esMiembro=true). Se recalcula aquí a mano,
// simulando que el pago YA hizo al usuario miembro — que es exactamente lo
// que va a pasar en cuanto confirme.
if ($item['incluido_membresia'] === true) {
    $item['precio_final'] = 0.0;
} elseif ((float) ($item['descuento_miembro_pct'] ?? 0) > 0) {
    $precioConDescuentoMiembro = round($item['precio_regular'] * (1 - (float) $item['descuento_miembro_pct'] / 100), 2);
    // No empeora un precio ya más bajo por cupón/promoción pública vigente —
    // se queda con el que sea mejor para quien compra.
    $item['precio_final'] = min($item['precio_final'], max(0.0, $precioConDescuentoMiembro));
}

if (usuario_tiene_membresia_activa($usuarioPerfilId)) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes una membresía activa.']);
    exit;
}

$yaTieneAcceso = $tipo === 'evento'
    ? usuario_esta_inscrito_evento($usuarioPerfilId, $itemId)
    : usuario_tiene_acceso_curso($usuarioPerfilId, $itemId);
if ($yaTieneAcceso) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes acceso a este ' . $tipo . '.']);
    exit;
}

// "La" membresía visible del sitio — mismo criterio que content/membresia.php
// (no hay selector de membresía en el combo, el usuario nunca elige cuál).
$membresia = $conn->query('SELECT * FROM membresias WHERE activo = 1 ORDER BY orden ASC LIMIT 1')->fetch_assoc();
if (!$membresia) {
    echo json_encode(['success' => false, 'message' => 'La membresía no está disponible.']);
    exit;
}
if (!$membresia['stripe_price_id']) {
    echo json_encode(['success' => false, 'message' => 'Esta membresía todavía no está configurada. Intenta más tarde.']);
    exit;
}

$priceIdMembresia = $membresia['stripe_price_id'];
if (stripe_modo_prueba_activo()) {
    if (empty($membresia['stripe_price_id_prueba'])) {
        echo json_encode(['success' => false, 'message' => 'Estás en modo prueba de Stripe, pero esta membresía no tiene un Price ID de prueba configurado.']);
        exit;
    }
    $priceIdMembresia = $membresia['stripe_price_id_prueba'];
}

// Cupón/promoción — SOLO afecta el precio de membresía, nunca el del
// evento/curso (que ya trae su precio_final resuelto arriba, sin tocar).
$ofertaItemMembresia = [
    'id' => $membresia['id'],
    'precio' => (float) $membresia['precio'],
    'gratuito' => false,
    'incluido_membresia' => false,
    'solo_miembros' => false,
    'descuento_miembro_pct' => null,
    'ya_tiene_acceso' => false,
];
$ofertaMembresia = resolver_oferta($conn, 'membresia', $ofertaItemMembresia, $usuario, $codigoCupon);

$modoActual = stripe_modo_prueba_activo() ? 'prueba' : 'live';
$stmt = $conn->prepare(
    "SELECT stripe_customer_id FROM membresia_suscripciones
     WHERE usuario_id = ? AND stripe_customer_id IS NOT NULL AND modo = ?
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->bind_param('is', $usuarioPerfilId, $modoActual);
$stmt->execute();
$filaCustomer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stripeCustomerId = $filaCustomer['stripe_customer_id'] ?? null;
if (!$stripeCustomerId) {
    $resCustomer = stripe_api('POST', 'customers', [
        'email' => $usuario['email'],
        'name' => $usuario['username'],
        'metadata' => ['usuario_id' => $usuarioPerfilId],
    ]);
    if (!$resCustomer['ok']) {
        $errStripe = $resCustomer['data']['error']['message'] ?? 'sin detalle';
        ra_registrar_error('Stripe customers (combo) falló: ' . $errStripe, __FILE__, __LINE__);
        echo json_encode(['success' => false, 'message' => 'No se pudo iniciar el combo: ' . $errStripe]);
        exit;
    }
    $stripeCustomerId = $resCustomer['data']['id'];
}

// Mismo criterio de duración de cupón que membresia_iniciar.php.
$duracionCupon = ['duration' => 'once'];
if ($ofertaMembresia['oferta_tipo'] === 'cupon' && $ofertaMembresia['oferta_id']) {
    $stmtCupon = $conn->prepare('SELECT vigencia_tipo, vigencia_meses FROM cupones WHERE id = ?');
    $stmtCupon->bind_param('i', $ofertaMembresia['oferta_id']);
    $stmtCupon->execute();
    $filaCupon = $stmtCupon->get_result()->fetch_assoc();
    $stmtCupon->close();
    if ($filaCupon && $filaCupon['vigencia_tipo'] === 'meses' && $filaCupon['vigencia_meses']) {
        $duracionCupon = ['duration' => 'repeating', 'duration_in_months' => (int) $filaCupon['vigencia_meses']];
    }
}

$discounts = [];
if ($ofertaMembresia['estado'] === 'oferta' && $ofertaMembresia['descuento_monto'] > 0) {
    $resCoupon = stripe_api('POST', 'coupons', array_merge([
        'amount_off' => (int) round($ofertaMembresia['descuento_monto'] * 100),
        'currency' => 'mxn',
        'name' => 'Cupón/promoción: ' . ($ofertaMembresia['oferta_nombre'] ?? ''),
    ], $duracionCupon));
    if ($resCoupon['ok']) {
        $discounts = [['coupon' => $resCoupon['data']['id']]];
    } else {
        ra_registrar_error('Stripe coupons (combo) falló: ' . ($resCoupon['data']['error']['message'] ?? 'sin detalle'), __FILE__, __LINE__);
    }
}

// add_invoice_items[].price_data espera un Product ya existente en `product`
// — a diferencia de checkout.session.create (que sí acepta price_data.product_data
// inline), aquí Stripe responde "unknown parameter product_data. Did you mean
// product?" (confirmado contra la API real en modo prueba). Se crea un
// Product nuevo por cada combo: es efímero, solo describe este cargo puntual
// en esta factura — no hace falta un catálogo reusable de "productos combo".
$resProducto = stripe_api('POST', 'products', ['name' => ucfirst($tipo) . ': ' . $item['titulo']]);
if (!$resProducto['ok'] || empty($resProducto['data']['id'])) {
    $errStripe = $resProducto['data']['error']['message'] ?? 'sin detalle';
    ra_registrar_error('Stripe products (combo) falló: ' . $errStripe, __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar el combo: ' . $errStripe]);
    exit;
}
$productoIdCombo = $resProducto['data']['id'];

$armarPayloadSub = fn (string $customerId) => array_merge([
    'customer' => $customerId,
    'items' => [['price' => $priceIdMembresia]],
    'add_invoice_items' => [[
        'price_data' => [
            'currency' => 'mxn',
            'product' => $productoIdCombo,
            'unit_amount' => (int) round($item['precio_final'] * 100),
        ],
        // El cupón de membresía nunca debe descontar este cargo — sin esto,
        // un discount a nivel Subscription podría aplicarse también aquí.
        'discountable' => 'false',
        'metadata' => ['tipo_combo_item' => $tipo, 'combo_item_id' => (string) $itemId],
    ]],
    'payment_behavior' => 'default_incomplete',
    'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
    'expand' => ['latest_invoice.confirmation_secret'],
    'metadata' => [
        'usuario_id' => $usuarioPerfilId,
        'membresia_id' => $membresia['id'],
        'combo' => '1',
        'combo_tipo' => $tipo,
        'combo_item_id' => $itemId,
    ],
], $discounts ? ['discounts' => $discounts] : []);

$resSub = stripe_api('POST', 'subscriptions', $armarPayloadSub($stripeCustomerId));

// El customer_id guardado en membresia_suscripciones puede haber quedado
// huérfano (se creó en un modo test/live que ya no es el activo — ver
// stripe_customer_id_invalido()). En ese caso se regenera una sola vez y se
// reintenta, en vez de tronar con un mensaje críptico de Stripe.
if (!$resSub['ok'] && stripe_customer_id_invalido($resSub)) {
    $stripeCustomerId = stripe_customer_regenerar($conn, $usuarioPerfilId, $usuario['email'], $usuario['username'], $modoActual);
    if ($stripeCustomerId) {
        $resSub = stripe_api('POST', 'subscriptions', $armarPayloadSub($stripeCustomerId));
    }
}

if (!$resSub['ok'] || empty($resSub['data']['id'])) {
    $errStripe = $resSub['data']['error']['message'] ?? 'sin detalle';
    ra_registrar_error('Stripe subscriptions (combo) falló: ' . $errStripe, __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar el combo: ' . $errStripe]);
    exit;
}

$subscriptionId = $resSub['data']['id'];
$cuponId = $ofertaMembresia['oferta_tipo'] === 'cupon' ? $ofertaMembresia['oferta_id'] : null;
$promocionId = $ofertaMembresia['oferta_tipo'] === 'promocion' ? $ofertaMembresia['oferta_id'] : null;

$confirmationSecret = $resSub['data']['latest_invoice']['confirmation_secret'] ?? null;
$clientSecret = ($confirmationSecret['type'] ?? '') === 'payment_intent' ? ($confirmationSecret['client_secret'] ?? null) : null;
$paymentIntentId = $clientSecret && str_contains($clientSecret, '_secret_') ? strstr($clientSecret, '_secret_', true) : null;

// Caso $0 (cupón cubre el 100% de la membresía Y el evento/curso también
// queda en $0 — solo así la factura completa llega a cero): Stripe marca la
// factura pagada sola, sin PaymentIntent que confirmar. Se activa todo de
// una vez, sin esperar invoice.paid (que no llega en este caso).
if ($ofertaMembresia['acceso_gratis_automatico'] && $item['precio_final'] <= 0.0 && ($resSub['data']['latest_invoice']['status'] ?? '') === 'paid') {
    require_once __DIR__ . '/combo_helper.php';
    $stmt = $conn->prepare(
        "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, cupon_id, promocion_id, metodo, modo, stripe_customer_id, stripe_subscription_id, estado)
         VALUES (?, ?, ?, ?, 'stripe', ?, ?, ?, 'activa')
         ON DUPLICATE KEY UPDATE membresia_id = VALUES(membresia_id), cupon_id = VALUES(cupon_id), promocion_id = VALUES(promocion_id), stripe_customer_id = VALUES(stripe_customer_id), estado = 'activa'"
    );
    $stmt->bind_param('iiiisss', $usuarioPerfilId, $membresia['id'], $cuponId, $promocionId, $modoActual, $stripeCustomerId, $subscriptionId);
    $stmt->execute();
    $stmt->close();

    activar_combo_inscripcion($conn, $usuarioPerfilId, $tipo, $itemId);

    echo json_encode(['success' => true, 'activada_de_inmediato' => true, 'subscription_id' => $subscriptionId]);
    exit;
}

if (!$clientSecret || !$paymentIntentId) {
    ra_registrar_error('Stripe subscriptions (combo): respuesta sin confirmation_secret de payment_intent (sub ' . $subscriptionId . ')', __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'No se pudo preparar el pago del combo.']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, cupon_id, promocion_id, metodo, modo, stripe_customer_id, stripe_subscription_id, estado)
     VALUES (?, ?, ?, ?, 'stripe', ?, ?, ?, 'pendiente')
     ON DUPLICATE KEY UPDATE membresia_id = VALUES(membresia_id), cupon_id = VALUES(cupon_id), promocion_id = VALUES(promocion_id), stripe_customer_id = VALUES(stripe_customer_id)"
);
$stmt->bind_param('iiiisss', $usuarioPerfilId, $membresia['id'], $cuponId, $promocionId, $modoActual, $stripeCustomerId, $subscriptionId);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'client_secret' => $clientSecret,
    'subscription_id' => $subscriptionId,
    'payment_intent_id' => $paymentIntentId,
    'precio_membresia_regular' => $ofertaMembresia['precio_regular'],
    'precio_membresia_final' => $ofertaMembresia['precio_final'],
    'precio_item' => $item['precio_final'],
    'precio_total' => round($ofertaMembresia['precio_final'] + $item['precio_final'], 2),
    'oferta_nombre' => $ofertaMembresia['oferta_nombre'],
]);
