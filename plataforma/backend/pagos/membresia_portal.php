<?php
// Redirige al Billing Portal alojado por Stripe: ahí el usuario puede ver sus
// facturas, actualizar su tarjeta o cancelar la suscripción, sin que nosotros
// construyamos esa interfaz. Endpoint GET simple (no muta nada por sí mismo — solo
// crea una sesión temporal de Stripe para el customer ya ligado a este usuario).
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/stripe_helper.php';

require_login(BASE_URL . '/index.php?action=membresia');

$usuario = current_user();

$stmt = $conn->prepare(
    "SELECT stripe_customer_id FROM membresia_suscripciones
     WHERE usuario_id = ? AND stripe_customer_id IS NOT NULL
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->bind_param('i', $usuario['id']);
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

if (!$res['ok'] || empty($res['data']['url'])) {
    header('Location: ' . BASE_URL . '/index.php?action=membresia');
    exit;
}

header('Location: ' . $res['data']['url']);
exit;
