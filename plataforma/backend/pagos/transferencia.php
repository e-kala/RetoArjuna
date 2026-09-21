<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../uploads.php';
require_once __DIR__ . '/item_resolver.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));
$direccionEnvio = trim($_POST['direccion_envio'] ?? '');
// Teléfono de WhatsApp capturado junto al comprobante — a dónde llegará el
// aviso de "tu comprobante fue confirmado" (ver notificacion_crear() en
// _pagos_acciones.php). Opcional: si se deja vacío, ese aviso simplemente
// no se manda por WhatsApp (sigue yendo por correo + campana). Se valida el
// formato (solo dígitos/espacios/+/guiones, 7-20 caracteres) — no para
// impedir que alguien compre a nombre de un tercero (caso de uso legítimo,
// ej. un regalo), sino para que este campo nunca meta basura al payload
// real de Graph API ni se use para colar algo que no sea un teléfono.
$whatsappTelefono = trim($_POST['whatsapp_telefono'] ?? '') ?: null;
if ($whatsappTelefono !== null && !preg_match('/^[\d\s+\-()]{7,20}$/', $whatsappTelefono)) {
    echo json_encode(['success' => false, 'message' => 'El número de WhatsApp no es válido.']);
    exit;
}

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
if ($item['tipo'] !== 'producto') {
    $cantidad = 1;
}
if ($item['es_fisico'] && $direccionEnvio === '') {
    echo json_encode(['success' => false, 'message' => 'Indica una dirección de envío.']);
    exit;
}

$columna = columna_pago_para_item($item)['col'];
$itemId = $item['id'];

try {
    $comprobante = procesar_subida_comprobante('comprobante_file', 'comprobantes');
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// El teléfono capturado aquí también se guarda en el perfil del usuario —
// así queda disponible para cualquier otro aviso de WhatsApp (no solo el de
// este pago) sin que tenga que volver a escribirlo la próxima vez. Mismo
// campo que edita perfil_actualizar.php, incondicional (sin validar
// formato), mismo criterio que ese archivo.
if ($whatsappTelefono !== null) {
    $stmt = $conn->prepare('UPDATE usuarios_perfil SET telefono = ? WHERE id = ?');
    $stmt->bind_param('si', $whatsappTelefono, $usuarioPerfilId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare(
    "SELECT id FROM pagos WHERE usuario_id = ? AND {$columna} = ? AND metodo_pago = 'transferencia' AND estado = 'pendiente' LIMIT 1"
);
$stmt->bind_param('ii', $usuarioPerfilId, $itemId);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    if ($comprobante !== null || $whatsappTelefono !== null) {
        $stmt = $conn->prepare(
            'UPDATE pagos SET comprobante_url = COALESCE(?, comprobante_url), whatsapp_telefono = COALESCE(?, whatsapp_telefono) WHERE id = ?'
        );
        $stmt->bind_param('ssi', $comprobante, $whatsappTelefono, $existente['id']);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true, 'pago_id' => (int) $existente['id']]);
    exit;
}

$montoUnitario = $item['precio_final'];
$montoFinal = $montoUnitario * $cantidad;
$cuponId = $item['oferta_tipo'] === 'cupon' ? $item['oferta_id'] : null;
$promocionId = $item['oferta_tipo'] === 'promocion' ? $item['oferta_id'] : null;
$regaloId = $item['oferta_tipo'] === 'regalo' ? $item['oferta_id'] : null;

$stmt = $conn->prepare(
    "INSERT INTO pagos (usuario_id, {$columna}, cupon_id, promocion_id, regalo_id, monto, metodo_pago, estado, cantidad, direccion_envio, comprobante_url, whatsapp_telefono)
     VALUES (?, ?, ?, ?, ?, ?, 'transferencia', 'pendiente', ?, ?, ?, ?)"
);
$stmt->bind_param('iiiiidisss', $usuarioPerfilId, $itemId, $cuponId, $promocionId, $regaloId, $montoFinal, $cantidad, $direccionEnvio, $comprobante, $whatsappTelefono);
$stmt->execute();
$pagoId = $stmt->insert_id;
$stmt->close();

echo json_encode(['success' => true, 'pago_id' => $pagoId]);
