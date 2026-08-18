<?php
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();

if (!empty($_POST['todas'])) {
    $stmt = $conn->prepare('UPDATE foro_notificaciones SET leida = 1 WHERE usuario_id = ?');
    $stmt->bind_param('i', $usuario['id']);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

$notificacionId = (int) ($_POST['notificacion_id'] ?? 0);
if ($notificacionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Falta indicar la notificación.']);
    exit;
}

$stmt = $conn->prepare('UPDATE foro_notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?');
$stmt->bind_param('ii', $notificacionId, $usuario['id']);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
