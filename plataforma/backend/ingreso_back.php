<?php
// Login nativo: verifica usuario/correo + contraseña contra usuarios_perfil.
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_form();

$identification = trim($_POST['identification'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($identification === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Ingresa tu usuario/correo y tu contraseña.']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT id, password_hash, activo FROM usuarios_perfil WHERE username_cache = ? OR email_cache = ? LIMIT 1'
);
$stmt->bind_param('ss', $identification, $identification);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario || !$usuario['password_hash'] || !password_verify($password, $usuario['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
    exit;
}
if ((int) $usuario['activo'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Tu cuenta está desactivada.']);
    exit;
}

login_user((int) $usuario['id']);

echo json_encode(['success' => true, 'redirect' => BASE_URL . '/panel/index.php']);
