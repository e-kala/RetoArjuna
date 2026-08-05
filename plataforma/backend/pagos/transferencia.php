<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../cupones.php';
require_once __DIR__ . '/item_resolver.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$codigoCupon = trim($_POST['codigo_cupon'] ?? '');
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

$columna = columna_pago_para_item($item)['col'];
$itemId = $item['id'];

$stmt = $conn->prepare(
    "SELECT id FROM pagos WHERE usuario_id = ? AND {$columna} = ? AND metodo_pago = 'transferencia' AND estado = 'pendiente' LIMIT 1"
);
$stmt->bind_param('ii', $usuarioPerfilId, $itemId);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    echo json_encode(['success' => true, 'pago_id' => (int) $existente['id']]);
    exit;
}

$montoUnitario = $item['precio'];
$montoFinal = $montoUnitario * $cantidad;
$descuento = 0;
$cuponId = null;
if ($codigoCupon !== '') {
    $resultado = validar_cupon($conn, $codigoCupon, $montoFinal);
    if ($resultado) {
        $montoFinal = $resultado['monto_final'];
        $descuento = $resultado['descuento'];
        $cuponId = $resultado['cupon_id'];
    }
}

$stmt = $conn->prepare(
    "INSERT INTO pagos (usuario_id, {$columna}, cupon_id, monto, descuento, metodo_pago, estado, cantidad, direccion_envio)
     VALUES (?, ?, ?, ?, ?, 'transferencia', 'pendiente', ?, ?)"
);
$stmt->bind_param('iiiddis', $usuarioPerfilId, $itemId, $cuponId, $montoFinal, $descuento, $cantidad, $direccionEnvio);
$stmt->execute();
$pagoId = $stmt->insert_id;
$stmt->close();

if ($cuponId !== null) {
    incrementar_uso_cupon($conn, $cuponId);
}

echo json_encode(['success' => true, 'pago_id' => $pagoId]);
