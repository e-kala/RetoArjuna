<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/item_resolver.php';
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
$cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));
$direccionEnvio = trim($_POST['direccion_envio'] ?? '');

$item = resolver_item_pago($conn, $_POST, $usuarioPerfilId);
if (!$item || !$item['activo'] || $item['gratuito']) {
    echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
    exit;
}
if ($item['ya_tiene_acceso']) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes acceso a este artículo.']);
    exit;
}
if ($item['tipo'] !== 'producto') {
    $cantidad = 1;
}
if ($item['es_fisico'] && $direccionEnvio === '') {
    echo json_encode(['success' => false, 'message' => 'Indica una dirección de envío.']);
    exit;
}

$montoUnitario = $item['precio'];
$montoFinal = $montoUnitario * $cantidad;

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_POSTFIELDS => http_build_query([
        'amount' => (int) round($montoFinal * 100),
        'currency' => 'mxn',
        'description' => ucfirst($item['tipo']) . ': ' . $item['titulo'],
        'metadata' => [
            'usuario_id' => $usuarioPerfilId,
            'tipo' => $item['tipo'],
            'item_id' => $item['id'],
        ],
    ]),
]);
$respuesta = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string) $respuesta, true);
if ($status !== 200 || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar el pago con tarjeta.']);
    exit;
}

$columna = columna_pago_para_item($item)['col'];
$stmt = $conn->prepare(
    "INSERT INTO pagos (usuario_id, {$columna}, monto, metodo_pago, transaccion_id, estado, cantidad, direccion_envio)
     VALUES (?, ?, ?, 'stripe', ?, 'pendiente', ?, ?)"
);
$itemId = $item['id'];
$stmt->bind_param('iidsis', $usuarioPerfilId, $itemId, $montoFinal, $data['id'], $cantidad, $direccionEnvio);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'client_secret' => $data['client_secret']]);
