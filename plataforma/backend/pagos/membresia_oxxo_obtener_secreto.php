<?php
// Devuelve el client_secret de un voucher OXXO ya creado pero aún sin
// confirmar por el cliente — caso del voucher que genera el cron
// (membresia_oxxo_generar_vouchers.php) para la renovación mensual, donde
// no hay ningún Payment Element de por medio todavía (a diferencia del alta,
// que lo monta de inmediato en membresia.php). mi_membresia.php llama a este
// endpoint para poder montar ese mismo Payment Element y que el usuario
// confirme el método OXXO — sin eso, Stripe nunca genera
// next_action.oxxo_display_details y el voucher se queda sin numero/url para
// siempre (ver membresia_oxxo_helper.php).
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/membresia_oxxo_helper.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) current_user()['id'];
$suscripcionId = (int) ($_POST['suscripcion_id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT id, modo FROM membresia_suscripciones
     WHERE id = ? AND usuario_id = ? AND metodo = 'oxxo_recurrente' LIMIT 1"
);
$stmt->bind_param('ii', $suscripcionId, $usuarioPerfilId);
$stmt->execute();
$suscripcion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$suscripcion) {
    echo json_encode(['success' => false, 'message' => 'Suscripción no encontrada.']);
    exit;
}

$voucher = membresia_oxxo_voucher_pendiente($conn, $suscripcionId);
if (!$voucher) {
    echo json_encode(['success' => false, 'message' => 'No hay ningún voucher pendiente de confirmar.']);
    exit;
}

$llave = $suscripcion['modo'] === 'prueba' && defined('STRIPE_SECRET_KEY_PRUEBA') && config_esta_lista(STRIPE_SECRET_KEY_PRUEBA)
    ? STRIPE_SECRET_KEY_PRUEBA
    : STRIPE_SECRET_KEY;
$res = stripe_api('GET', 'payment_intents/' . urlencode($voucher['payment_intent_id']), [], $llave);
if (!$res['ok'] || empty($res['data']['client_secret'])) {
    echo json_encode(['success' => false, 'message' => 'No se pudo recuperar el voucher desde Stripe.']);
    exit;
}

echo json_encode([
    'success' => true,
    'client_secret' => $res['data']['client_secret'],
    'ya_confirmado' => ($res['data']['status'] ?? '') !== 'requires_payment_method',
]);
