<?php
// Aplica un cupón interno (tabla `cupones`, calculado en la plataforma, no
// vía Stripe Promotion Codes — ese mecanismo sigue vivo aparte en
// stripe_aplicar_promocion.php/stripe_membresia_aplicar_promocion.php, sin
// tocar) a la compra en curso. Calca su estructura, cambiando solo la fuente
// de validación.
//
// Dos casos:
// - Ya existe un PaymentIntent (pestaña Tarjeta ya montada, o la primera
//   factura de una Subscription de membresía): se parcha su monto en Stripe
//   y se actualiza la fila de `pagos`/`membresia_suscripciones` ya insertada
//   — igual que stripe_aplicar_promocion.php.
// - Todavía no hay PaymentIntent (pestaña Transferencia, o la página apenas
//   cargó): solo se devuelve el desglose para actualizar la UI; el monto
//   real se calcula limpio cuando el usuario de verdad envíe el pago
//   (transferencia.php/stripe_create_intent.php/membresia_iniciar.php ya
//   llaman resolver_oferta() con el código).
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

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$paymentIntentId = trim($_POST['payment_intent_id'] ?? '');
$codigo = trim($_POST['codigo_cupon'] ?? '');
$cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));
$esMembresia = ($_POST['tipo'] ?? '') === 'membresia';

if ($codigo === '') {
    echo json_encode(['success' => false, 'message' => 'Escribe un código de cupón.']);
    exit;
}

// La membresía no pasa por resolver_item_pago() (solo conoce curso/evento/
// producto) — se resuelve aparte con el mismo criterio que
// membresia_iniciar.php, cantidad siempre 1 (una suscripción a la vez).
if ($esMembresia) {
    $cantidad = 1;
    $membresiaId = (int) ($_POST['membresia_id'] ?? 0);
    $stmt = $conn->prepare('SELECT * FROM membresias WHERE id = ? AND activo = 1 LIMIT 1');
    $stmt->bind_param('i', $membresiaId);
    $stmt->execute();
    $membresia = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$membresia) {
        echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
        exit;
    }
    if (usuario_tiene_membresia_activa($usuarioPerfilId)) {
        echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
        exit;
    }
    $ofertaItem = [
        'id' => $membresia['id'],
        'precio' => (float) $membresia['precio'],
        'gratuito' => false,
        'incluido_membresia' => false,
        'solo_miembros' => false,
        'descuento_miembro_pct' => null,
        'ya_tiene_acceso' => false,
    ];
    $item = array_merge($ofertaItem, resolver_oferta($conn, 'membresia', $ofertaItem, current_user(), $codigo));
    $item['tipo'] = 'membresia';
    $item['activo'] = true;
    $item['mostrar_codigo_promocion'] = (bool) $membresia['mostrar_codigo_promocion'];
} else {
    $item = resolver_item_pago($conn, $_POST, $usuarioPerfilId, $codigo);
}
if (!$item || !$item['activo'] || $item['gratuito'] || in_array($item['estado'], ['incluido_membresia', 'exclusivo_bloqueado'], true)) {
    echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
    exit;
}
if (!$item['mostrar_codigo_promocion']) {
    // Defensa en profundidad — el campo ya está oculto en checkout.php cuando
    // el admin no activó esto para el artículo.
    echo json_encode(['success' => false, 'message' => 'Este artículo no admite código de cupón.']);
    exit;
}
if ($item['cupon_error']) {
    echo json_encode(['success' => false, 'message' => $item['cupon_error']]);
    exit;
}
if ($item['oferta_tipo'] !== 'cupon' && $item['oferta_tipo'] !== 'combinado') {
    // El cupón es válido pero una promoción pública ya da mejor precio —
    // resolver_oferta() ya eligió esa; se lo decimos claro al usuario.
    echo json_encode(['success' => false, 'message' => 'Ya tienes una promoción vigente mejor que este cupón.']);
    exit;
}

if ($item['tipo'] !== 'producto') {
    $cantidad = 1;
}
$montoOriginal = $item['precio_regular'] * $cantidad;
$montoFinal = $item['precio_final'] * $cantidad;

// Si queda en $0, no hay nada que parchar en Stripe (rechaza PaymentIntents
// de $0) — se le pide al frontend recargar, y la página ya sabe mostrar el
// botón "Obtener gratis" en vez del Payment Element (checkout.php). Hay que
// guardar el cupón en sesión ANTES de responder: sin esto, el recargar()
// del frontend perdía el código (nunca llegó por la URL, solo se tecleó en
// el campo) y checkout.php/membresia.php volvían a resolver el precio de
// lista, deshaciendo el $0 justo después de mostrarlo.
if ($montoFinal <= 0) {
    guardar_cupon_sesion($codigo);
    echo json_encode([
        'success' => true,
        'monto_original' => $montoOriginal,
        'monto_final' => 0,
        'descuento' => $montoOriginal,
        'recargar' => true,
    ]);
    exit;
}

if ($paymentIntentId !== '' && $esMembresia) {
    // A diferencia de un PaymentIntent independiente (pagos únicos), el de
    // una Subscription nace ligado a una factura — Stripe RECHAZA cambiarle
    // el "amount" después de creado ("cannot be used when modifying a
    // PaymentIntent that was created by an invoice"; ver membresia_iniciar.php,
    // que ya aplica el cupón como Coupon nativo de Stripe ANTES de crear la
    // Subscription para evitar justo este problema). Para este caso — el
    // usuario ya le dio clic a "Suscribirme" con precio completo y AHORA
    // escribe un cupón — no hay forma de re-precificar esa Subscription ya
    // creada sin cancelarla y crear una nueva desde cero; se le pide
    // recargar para que el cupón se aplique desde el inicio del flujo, ya
    // con el precio correcto.
    echo json_encode(['success' => false, 'message' => 'Ya iniciaste el pago de la suscripción con el precio anterior. Recarga la página, escribe el cupón antes de dar clic en "Suscribirme con tarjeta" y vuelve a intentar.']);
    exit;
} elseif ($paymentIntentId !== '') {
    // El PaymentIntent tiene que ser el que ya se creó para ESTE usuario en
    // esta compra — se confirma contra la fila de `pagos`, nunca contra un
    // id que venga solo del cliente.
    $stmt = $conn->prepare("SELECT id FROM pagos WHERE transaccion_id = ? AND usuario_id = ? AND estado = 'pendiente' LIMIT 1");
    $stmt->bind_param('si', $paymentIntentId, $usuarioPerfilId);
    $stmt->execute();
    $pagoFila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$pagoFila) {
        echo json_encode(['success' => false, 'message' => 'No se pudo aplicar el cupón a este pago.']);
        exit;
    }

    $resUpdate = stripe_api('POST', 'payment_intents/' . urlencode($paymentIntentId), ['amount' => (int) round($montoFinal * 100)]);
    if (!$resUpdate['ok']) {
        $errStripe = $resUpdate['data']['error']['message'] ?? 'sin detalle';
        ra_registrar_error('Stripe payment_intents update (cupón) falló: ' . $errStripe, __FILE__, __LINE__);
        echo json_encode(['success' => false, 'message' => 'No se pudo aplicar el cupón: ' . $errStripe]);
        exit;
    }

    $cuponId = $item['oferta_tipo'] === 'cupon' ? $item['oferta_id'] : null;
    $stmt = $conn->prepare("UPDATE pagos SET monto = ?, cupon_id = ? WHERE transaccion_id = ? AND usuario_id = ?");
    $stmt->bind_param('disi', $montoFinal, $cuponId, $paymentIntentId, $usuarioPerfilId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'monto_original' => $montoOriginal,
    'monto_final' => $montoFinal,
    'descuento' => $montoOriginal - $montoFinal,
    'recargar' => false,
]);
