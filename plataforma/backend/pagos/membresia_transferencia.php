<?php
// Registra la intención de pagar la membresía por transferencia: crea (o
// actualiza) una fila 'pendiente' en membresia_suscripciones con el
// comprobante si se subió uno — el admin la confirma desde
// panel/admin/membresias.php (ahí se define la fecha de inicio real).
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../uploads.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$membresiaId = (int) ($_POST['membresia_id'] ?? 0);

$stmt = $conn->prepare('SELECT id FROM membresias WHERE id = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('i', $membresiaId);
$stmt->execute();
$membresia = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$membresia) {
    echo json_encode(['success' => false, 'message' => 'Membresía no disponible.']);
    exit;
}
if (usuario_tiene_membresia_activa($usuarioPerfilId)) {
    echo json_encode(['success' => false, 'message' => 'Ya tienes una membresía activa.']);
    exit;
}

try {
    $comprobante = procesar_subida_comprobante('comprobante_file', 'comprobantes');
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id FROM membresia_suscripciones WHERE usuario_id = ? AND membresia_id = ? AND estado = 'pendiente' LIMIT 1"
);
$stmt->bind_param('ii', $usuarioPerfilId, $membresiaId);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    if ($comprobante !== null) {
        $stmt = $conn->prepare('UPDATE membresia_suscripciones SET comprobante_url = ? WHERE id = ?');
        $stmt->bind_param('si', $comprobante, $existente['id']);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, metodo, estado, comprobante_url)
     VALUES (?, ?, 'transferencia', 'pendiente', ?)"
);
$stmt->bind_param('iis', $usuarioPerfilId, $membresiaId, $comprobante);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
