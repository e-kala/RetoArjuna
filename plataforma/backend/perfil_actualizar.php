<?php
// Actualiza usuario/correo/teléfono del perfil propio (usuarios_perfil es la fuente
// de verdad de identidad, ya no hay un tercero que administre esto).
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioId = (int) $_SESSION['usuario_perfil_id'];
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$perfilPublico = isset($_POST['perfil_publico']) ? 1 : 0;

if ($username === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'El usuario y el correo son obligatorios.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'El correo no es válido.']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT id FROM usuarios_perfil WHERE (username_cache = ? OR email_cache = ?) AND id <> ? LIMIT 1'
);
$stmt->bind_param('ssi', $username, $email, $usuarioId);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    echo json_encode(['success' => false, 'message' => 'Ese usuario o correo ya está en uso.']);
    exit;
}

$stmt = $conn->prepare('UPDATE usuarios_perfil SET username_cache = ?, email_cache = ?, telefono = ?, perfil_publico = ? WHERE id = ?');
$stmt->bind_param('sssii', $username, $email, $telefono, $perfilPublico, $usuarioId);
$stmt->execute();
$stmt->close();

$_SESSION['username'] = $username;
$_SESSION['email'] = $email;

echo json_encode(['success' => true]);
