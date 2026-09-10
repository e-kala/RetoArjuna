<?php
// Consume el token de password_resets y fija la nueva contraseña. Invalida
// TODOS los tokens pendientes de ese usuario al terminar (no solo el usado),
// para que un enlace viejo sin usar no siga vigente tras un reset exitoso.
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_form();

$token = (string) ($_POST['token'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Enlace inválido.']);
    exit;
}
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres, con letras y números.']);
    exit;
}
if ($password !== $passwordConfirm) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
    exit;
}

$tokenHash = hash('sha256', $token);
$stmt = $conn->prepare(
    'SELECT id, usuario_id FROM password_resets WHERE token_hash = ? AND usado = 0 AND expira_en >= NOW() LIMIT 1'
);
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila) {
    echo json_encode(['success' => false, 'message' => 'Este enlace ya expiró o ya se usó. Solicita uno nuevo.']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE usuarios_perfil SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $hash, $fila['usuario_id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('UPDATE password_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0');
$stmt->bind_param('i', $fila['usuario_id']);
$stmt->execute();
$stmt->close();

// Ya demostró ser dueño de la cuenta (token de un solo uso recibido en su
// correo), así que no tiene sentido pedirle usuario/contraseña otra vez —
// se loguea directo, mismo punto de entrada que login nativo/Google.
login_user((int) $fila['usuario_id']);

echo json_encode(['success' => true, 'redirect' => redirect_post_login()]);
