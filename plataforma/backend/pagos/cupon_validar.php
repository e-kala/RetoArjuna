<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../cupones.php';
require_once __DIR__ . '/item_resolver.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$codigo = trim($_POST['codigo'] ?? '');

$item = resolver_item_pago($conn, $_POST, $usuarioPerfilId);
if (!$item || !$item['activo'] || $item['gratuito']) {
    echo json_encode(['success' => false, 'message' => 'Artículo no disponible.']);
    exit;
}

$resultado = validar_cupon($conn, $codigo, $item['precio']);
if (!$resultado) {
    echo json_encode(['success' => false, 'message' => 'Cupón inválido, vencido o agotado.']);
    exit;
}

echo json_encode(['success' => true] + $resultado);
