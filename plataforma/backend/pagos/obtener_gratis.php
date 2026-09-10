<?php
// Otorga un producto directo, sin pasar por Stripe, cuando una promoción o
// cupón lo dejó en $0 exacto (resolver_oferta(): acceso_gratis_automatico) —
// Stripe rechaza PaymentIntents de $0, así que no hay nada que cobrar.
// Curso/evento en $0 se resuelven en su propio "Inscribirme gratis"
// (curso_inscribir.php/evento_inscribir.php extendidos), no aquí.
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

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));
$direccionEnvio = trim($_POST['direccion_envio'] ?? '');
$codigoCupon = trim((string) ($_POST['codigo_cupon'] ?? '')) ?: null;

$item = resolver_item_pago($conn, $_POST, $usuarioPerfilId, $codigoCupon);
if (!$item || !$item['activo'] || $item['tipo'] !== 'producto') {
    echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
    exit;
}
if ($item['ya_tiene_acceso']) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes acceso a este artículo.']);
    exit;
}
// Nunca confiar en que el cliente diga "ya es gratis" — se recalcula aquí
// mismo, server-side, con el mismo código de cupón recibido.
if (!$item['acceso_gratis_automatico']) {
    echo json_encode(['success' => false, 'message' => 'Este artículo ya no está en $0 — recarga la página.']);
    exit;
}
if ($item['es_fisico'] && $direccionEnvio === '') {
    echo json_encode(['success' => false, 'message' => 'Indica una dirección de envío.']);
    exit;
}

$columna = columna_pago_para_item($item)['col'];
$itemId = $item['id'];
$cuponId = $item['oferta_tipo'] === 'cupon' ? $item['oferta_id'] : null;
$promocionId = $item['oferta_tipo'] === 'promocion' ? $item['oferta_id'] : null;
// Mismo criterio que stripe_create_intent.php: si quien canjea el
// cupón/promoción tiene el modo prueba de Stripe activo, esto SÍ otorga
// acceso (usuario_compro_producto()) pero nunca cuenta como ingreso real
// (los reportes siguen filtrando modo = 'live').
$modo = stripe_modo_prueba_activo() ? 'prueba' : 'live';

$stmt = $conn->prepare(
    "INSERT INTO pagos (usuario_id, {$columna}, cupon_id, promocion_id, monto, metodo_pago, modo, estado, cantidad, direccion_envio)
     VALUES (?, ?, ?, ?, 0, 'transferencia', ?, 'confirmado', ?, ?)"
);
$stmt->bind_param('iiiisis', $usuarioPerfilId, $itemId, $cuponId, $promocionId, $modo, $cantidad, $direccionEnvio);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'redirect' => BASE_URL . '/panel/index.php?action=mis_compras&pago=ok',
]);
