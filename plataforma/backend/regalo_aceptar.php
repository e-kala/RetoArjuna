<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
$regalo = $codigo !== '' ? regalo_obtener_por_codigo($codigo) : null;

if (!$regalo) {
    echo json_encode(['success' => false, 'message' => 'Este regalo ya no existe.']);
    exit;
}

$esCurso = $regalo['curso_id'] !== null;
$slug = $esCurso ? $regalo['curso_slug'] : $regalo['evento_slug'];

if ((float) $regalo['descuento_pct'] >= 100) {
    $resultado = regalo_aceptar_gratis($regalo, $usuarioPerfilId);
    if (!$resultado['success']) {
        echo json_encode($resultado);
        exit;
    }
    $destino = $esCurso
        ? '?action=curso&slug=' . urlencode((string) $slug) . '&bienvenida=1'
        : '?action=evento&slug=' . urlencode((string) $slug) . '&pago=ok';
    echo json_encode(['success' => true, 'redirect' => $destino]);
    exit;
}

$resultado = regalo_aceptar_descuento($regalo, $usuarioPerfilId);
if (!$resultado['success']) {
    echo json_encode($resultado);
    exit;
}
echo json_encode([
    'success' => true,
    'redirect' => 'backend/pagos/checkout.php?' . ($esCurso ? 'curso_id=' . (int) $regalo['curso_id'] : 'evento_id=' . (int) $regalo['evento_id']) . '&regalo=' . urlencode($codigo),
]);
