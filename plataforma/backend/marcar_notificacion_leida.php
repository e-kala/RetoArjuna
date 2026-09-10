<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

if (!empty($_POST['todas'])) {
    notificacion_marcar_leida($usuarioPerfilId);
    echo json_encode(['success' => true]);
    exit;
}

$notificacionId = (int) ($_POST['notificacion_id'] ?? 0);
if ($notificacionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Falta indicar la notificación.']);
    exit;
}

notificacion_marcar_leida($usuarioPerfilId, $notificacionId);
echo json_encode(['success' => true]);
