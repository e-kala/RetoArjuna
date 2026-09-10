<?php
require_once __DIR__ . '/../auth.php';
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

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));
$direccionEnvio = trim($_POST['direccion_envio'] ?? '');

$codigoCupon = trim((string) ($_POST['codigo_cupon'] ?? '')) ?: null;
$item = resolver_item_pago($conn, $_POST, $usuarioPerfilId, $codigoCupon);
if (!$item || !$item['activo'] || $item['gratuito'] || in_array($item['estado'], ['incluido_membresia', 'exclusivo_bloqueado'], true)) {
    echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
    exit;
}
if ($item['ya_tiene_acceso']) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes acceso a este artículo.']);
    exit;
}
if ($item['acceso_gratis_automatico']) {
    echo json_encode(['success' => false, 'message' => 'Este artículo ya quedó en $0 — usa el botón "Obtener gratis".']);
    exit;
}
if ($item['tipo'] !== 'producto') {
    $cantidad = 1;
}
if ($item['es_fisico'] && $direccionEnvio === '') {
    echo json_encode(['success' => false, 'message' => 'Indica una dirección de envío.']);
    exit;
}

// El precio ya trae aplicada la mejor oferta vigente (promoción/cupón/
// descuento de miembro — ver resolver_oferta() en ofertas.php).
$montoUnitario = $item['precio_final'];
$montoFinal = $montoUnitario * $cantidad;

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_USERPWD => stripe_secret_key_activa() . ':',
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

// modo se guarda en el momento de crear el PaymentIntent (con la llave que
// realmente se usó) — no se recalcula después, para que quede fijo aunque
// quien lo activó apague el modo prueba antes de que el webhook confirme el
// pago (ver stripe_modo_prueba_activo() en stripe_helper.php). Un pago en
// modo prueba SÍ otorga acceso (ver usuario_tiene_acceso_curso()) pero nunca
// cuenta como venta real (reportes.php sigue filtrando modo = 'live').
$modo = stripe_modo_prueba_activo() ? 'prueba' : 'live';
$columna = columna_pago_para_item($item)['col'];
// oferta_tipo 'combinado' no persiste un solo id (nació de sumar varios
// descuentos) — se registra el cupón si participó, y si no, la promoción.
$cuponId = $item['oferta_tipo'] === 'cupon' ? $item['oferta_id'] : null;
$promocionId = $item['oferta_tipo'] === 'promocion' ? $item['oferta_id'] : null;
$regaloId = $item['oferta_tipo'] === 'regalo' ? $item['oferta_id'] : null;
$stmt = $conn->prepare(
    "INSERT INTO pagos (usuario_id, {$columna}, cupon_id, promocion_id, regalo_id, monto, metodo_pago, modo, transaccion_id, estado, cantidad, direccion_envio)
     VALUES (?, ?, ?, ?, ?, ?, 'stripe', ?, ?, 'pendiente', ?, ?)"
);
$itemId = $item['id'];
$stmt->bind_param('iiiiidssis', $usuarioPerfilId, $itemId, $cuponId, $promocionId, $regaloId, $montoFinal, $modo, $data['id'], $cantidad, $direccionEnvio);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'client_secret' => $data['client_secret'], 'payment_intent_id' => $data['id']]);
