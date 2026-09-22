<?php
// Redirige al Billing Portal alojado por Stripe: ahí el usuario puede ver sus
// facturas, actualizar su tarjeta o cancelar la suscripción, sin que nosotros
// construyamos esa interfaz. Endpoint GET simple (no muta nada por sí mismo — solo
// crea una sesión temporal de Stripe para el customer ya ligado a este usuario).
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/stripe_helper.php';

require_login(BASE_URL . '/index.php?action=membresia');

$usuario = current_user();

// El Customer de Stripe es específico de modo (test/live) — se busca el que
// corresponda al modo activo ahora mismo, para no intentar abrir un portal
// LIVE con un customer de prueba o viceversa (ver membresia_iniciar.php).
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

if (!$fila || !config_esta_lista(STRIPE_SECRET_KEY)) {
    header('Location: ' . BASE_URL . '/index.php?action=membresia');
    exit;
}

$res = stripe_api('POST', 'billing_portal/sessions', [
    'customer' => $fila['stripe_customer_id'],
    'return_url' => SITE_URL . '/index.php?action=membresia',
]);

// Customer huérfano (creado en un modo test/live que ya no es el activo) —
// no hay nada que "abrir" en el portal para uno que ya no existe en Stripe,
// así que solo se limpia y se manda de vuelta (ver stripe_customer_id_invalido()
// en stripe_helper.php); la próxima suscripción regenerará uno válido.
if (!$res['ok'] && stripe_customer_id_invalido($res)) {
    header('Location: ' . BASE_URL . '/index.php?action=membresia');
    exit;
}

if (!$res['ok'] || empty($res['data']['url'])) {
    header('Location: ' . BASE_URL . '/index.php?action=membresia');
    exit;
}

header('Location: ' . $res['data']['url']);
exit;
