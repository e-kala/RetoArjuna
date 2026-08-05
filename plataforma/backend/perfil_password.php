<?php
// Cambia la contraseña propia. Si la cuenta ya tenía password_hash, exige la actual;
// si entró solo por Google (password_hash NULL), permite fijar la primera sin pedirla.
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioId = (int) $_SESSION['usuario_perfil_id'];
$passwordActual = (string) ($_POST['password_actual'] ?? '');
$passwordNueva = (string) ($_POST['password_nueva'] ?? '');
$passwordNuevaConfirm = (string) ($_POST['password_nueva_confirm'] ?? '');

if (strlen($passwordNueva) < 8 || !preg_match('/[A-Za-z]/', $passwordNueva) || !preg_match('/\d/', $passwordNueva)) {
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres, con letras y números.']);
    exit;
}
if ($passwordNueva !== $passwordNuevaConfirm) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
    exit;
}

$stmt = $conn->prepare('SELECT password_hash FROM usuarios_perfil WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($fila && $fila['password_hash'] && !password_verify($passwordActual, $fila['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Tu contraseña actual no es correcta.']);
    exit;
}

$hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE usuarios_perfil SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $hash, $usuarioId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
