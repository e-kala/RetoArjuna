<?php
// Registro nativo: valida datos, hashea la contraseña y crea el perfil directo.
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_form();

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

if ($username === '' || $email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Completa todos los campos.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'El correo no es válido.']);
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

$stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE username_cache = ? OR email_cache = ? LIMIT 1');
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    echo json_encode(['success' => false, 'message' => 'Ese usuario o correo ya está registrado.']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "INSERT INTO usuarios_perfil (username_cache, email_cache, password_hash, telefono, rol, activo)
     VALUES (?, ?, ?, ?, 'estudiante', 1)"
);
$stmt->bind_param('ssss', $username, $email, $hash, $telefono);
$stmt->execute();
$nuevoId = $stmt->insert_id;
$stmt->close();

login_user($nuevoId);

echo json_encode(['success' => true, 'redirect' => BASE_URL . '/panel/index.php?action=perfil']);
