<?php
// Solicitud de "olvidé mi contraseña": genera un token de un solo uso (solo se
// guarda su hash, ver password_resets en exportar_produccion.sql) y manda el
// enlace por correo. Responde el mismo mensaje exista o no la cuenta, para no
// revelar si un correo está registrado en la plataforma.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
header('Content-Type: application/json');
requerir_csrf_form();

$email = trim($_POST['email'] ?? '');
$mensajeGenerico = 'Si el correo está registrado, te llegará un enlace para restablecer tu contraseña.';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Ingresa un correo válido.']);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE email_cache = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($usuario) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expira = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare('INSERT INTO password_resets (usuario_id, token_hash, expira_en) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $usuario['id'], $tokenHash, $expira);
    $stmt->execute();
    $stmt->close();

    $enlace = SITE_URL . '/index.php?action=restablecer_contrasena&token=' . $token;
    enviar_email_recuperar_contrasena((int) $usuario['id'], $email, $enlace);
}

echo json_encode(['success' => true, 'message' => $mensajeGenerico]);
