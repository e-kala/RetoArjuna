<?php
// Alta de membresía pagada con OXXO — a diferencia de membresia_iniciar.php
// (Stripe Subscription real, cobro automático con tarjeta), aquí solo se
// crea la fila 'pendiente' y el primer voucher del mes 1; no hay Subscription
// de Stripe de por medio (ver membresia_oxxo_helper.php). El mismo webhook
// payment_intent.succeeded que ya existe para curso/evento/producto se
// extiende (stripe_webhook.php) para activar esta fila cuando se paga.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../ofertas.php';
require_once __DIR__ . '/membresia_oxxo_helper.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

if (!config_esta_lista(STRIPE_SECRET_KEY)) {
    echo json_encode(['success' => false, 'message' => 'Los pagos con OXXO aún no están configurados.']);
    exit;
}

if (!membresia_oxxo_visible_para_usuario_actual()) {
    echo json_encode(['success' => false, 'message' => 'Este método de pago no está disponible todavía.']);
    exit;
}

$usuario = current_user();
$usuarioPerfilId = (int) $usuario['id'];
$membresiaId = (int) ($_POST['membresia_id'] ?? 0);
$codigoCupon = trim((string) ($_POST['codigo_cupon'] ?? '')) ?: null;

$stmt = $conn->prepare('SELECT * FROM membresias WHERE id = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('i', $membresiaId);
$stmt->execute();
$membresia = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$membresia) {
    echo json_encode(['success' => false, 'message' => 'Membresía no disponible.']);
    exit;
}
if (usuario_tiene_membresia_activa($usuarioPerfilId)) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes una membresía activa.']);
    exit;
}

// Un cupón/promoción que deje el primer mes en $0 no tiene voucher que
// generar — ese caso ya lo resuelve membresia_transferencia.php
// (acceso_gratis_automatico), OXXO no aporta nada ahí. Se rechaza en vez
// de generar un voucher de $0 (Stripe no lo permite).
$ofertaItem = [
    'id' => $membresia['id'], 'precio' => (float) $membresia['precio'], 'gratuito' => false,
    'incluido_membresia' => false, 'solo_miembros' => false, 'descuento_miembro_pct' => null,
    'ya_tiene_acceso' => false,
];
$oferta = resolver_oferta($conn, 'membresia', $ofertaItem, $usuario, $codigoCupon);
if ($oferta['acceso_gratis_automatico']) {
    echo json_encode(['success' => false, 'message' => 'Este cupón deja la membresía en $0 — usa el botón de transferencia, ahí se activa directo sin necesitar voucher.']);
    exit;
}

$modo = stripe_modo_prueba_activo() ? 'prueba' : 'live';
$cuponId = $oferta['oferta_tipo'] === 'cupon' ? $oferta['oferta_id'] : null;
$promocionId = $oferta['oferta_tipo'] === 'promocion' ? $oferta['oferta_id'] : null;

// Reusa una fila 'pendiente' de oxxo_recurrente si ya existía (el usuario
// cerró la pestaña a medias y volvió a intentar) en vez de acumular filas
// huérfanas — mismo criterio que membresia_transferencia.php.
$stmt = $conn->prepare(
    "SELECT id FROM membresia_suscripciones
     WHERE usuario_id = ? AND membresia_id = ? AND metodo = 'oxxo_recurrente' AND estado = 'pendiente' LIMIT 1"
);
$stmt->bind_param('ii', $usuarioPerfilId, $membresiaId);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    $suscripcionId = (int) $existente['id'];
} else {
    $stmt = $conn->prepare(
        "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, cupon_id, promocion_id, metodo, modo, estado)
         VALUES (?, ?, ?, ?, 'oxxo_recurrente', ?, 'pendiente')"
    );
    $stmt->bind_param('iiiis', $usuarioPerfilId, $membresiaId, $cuponId, $promocionId, $modo);
    $stmt->execute();
    $suscripcionId = $stmt->insert_id;
    $stmt->close();
}

$periodoInicio = date('Y-m-d');
$periodoFinObj = new DateTime($periodoInicio);
$periodoFinObj->modify($membresia['intervalo'] === 'anual' ? '+1 year' : '+1 month');
$periodoFin = $periodoFinObj->format('Y-m-d');

$resultado = membresia_oxxo_generar_voucher($conn, $suscripcionId, $oferta['precio_final'], $periodoInicio, $periodoFin);
if (!$resultado['success']) {
    echo json_encode($resultado);
    exit;
}

echo json_encode([
    'success' => true,
    'client_secret' => $resultado['client_secret'],
    'payment_intent_id' => $resultado['payment_intent_id'],
]);
