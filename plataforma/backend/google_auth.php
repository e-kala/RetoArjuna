<?php
// Recibe el id_token que entrega el botón "Continuar con Google" (Google Identity
// Services, cargado en ingreso.php/registro.php) y crea o vincula el usuario por
// email. Sin SDK: el id_token se valida contra el endpoint público de Google.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ofertas.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json');
requerir_csrf_json();

$id_token = $_POST['id_token'] ?? '';

if ($id_token === '') {
    echo json_encode(['success' => false, 'message' => 'Falta el token de Google.']);
    exit;
}

if (!config_esta_lista(GOOGLE_CLIENT_ID)) {
    echo json_encode(['success' => false, 'message' => 'Google Sign-In no está configurado todavía.']);
    exit;
}

// cURL en vez de file_get_contents(): muchos hostings compartidos traen
// allow_url_fopen deshabilitado por seguridad, pero cURL casi siempre está disponible.
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$respuesta = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($respuesta === false || $status !== 200) {
    echo json_encode(['success' => false, 'message' => 'No se pudo verificar el token de Google.']);
    exit;
}

$payload = json_decode($respuesta, true);

if (!$payload || ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    echo json_encode(['success' => false, 'message' => 'Token de Google inválido.']);
    exit;
}

$google_id = $payload['sub'];
$email = $payload['email'] ?? '';
$nombre = $payload['name'] ?? explode('@', $email)[0];

if ($email === '' || empty($payload['email_verified'])) {
    echo json_encode(['success' => false, 'message' => 'Tu cuenta de Google no tiene un correo verificado.']);
    exit;
}

// 1) ¿Ya existe un usuario vinculado a este google_id?
$stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE google_id = ? LIMIT 1');
$stmt->bind_param('s', $google_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($usuario) {
    login_user((int) $usuario['id']);
    guardar_cupon_sesion((string) ($_POST['cupon'] ?? ''));
    echo json_encode(['success' => true, 'redirect' => redirect_post_login('', (string) ($_POST['volver'] ?? ''))]);
    exit;
}

// 2) ¿Existe una cuenta con ese correo (registrada antes con usuario/contraseña)? La vinculamos.
$stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE email_cache = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($usuario) {
    $stmt = $conn->prepare('UPDATE usuarios_perfil SET google_id = ? WHERE id = ?');
    $stmt->bind_param('si', $google_id, $usuario['id']);
    $stmt->execute();
    $stmt->close();
    login_user((int) $usuario['id']);
    guardar_cupon_sesion((string) ($_POST['cupon'] ?? ''));
    echo json_encode(['success' => true, 'redirect' => redirect_post_login('', (string) ($_POST['volver'] ?? ''))]);
    exit;
}

// 3) Usuario nuevo: username derivado del correo, garantizado único.
$usernameBase = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]) ?: 'usuario';
$username = $usernameBase;
$sufijo = 1;
while (true) {
    $stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE username_cache = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$existe) {
        break;
    }
    $sufijo++;
    $username = $usernameBase . $sufijo;
}

$stmt = $conn->prepare(
    "INSERT INTO usuarios_perfil (username_cache, email_cache, google_id, rol, activo)
     VALUES (?, ?, ?, 'estudiante', 1)"
);
$stmt->bind_param('sss', $username, $email, $google_id);
$stmt->execute();
$nuevoId = $stmt->insert_id;
$stmt->close();

login_user($nuevoId);
guardar_cupon_sesion((string) ($_POST['cupon'] ?? ''));
enviar_email_bienvenida($nuevoId, $email);
echo json_encode(['success' => true, 'redirect' => redirect_post_login('perfil', (string) ($_POST['volver'] ?? ''))]);
