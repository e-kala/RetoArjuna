<?php
// Conexión y configuración global de la plataforma de cursos.
// La identidad (usuario/correo/contraseña) vive en usuarios_perfil (ver auth.php).

require_once __DIR__ . '/error_logger.php';

// Credenciales reales de este entorno (nunca en git — ver .gitignore). Si no
// existe, el sitio sigue funcionando con los valores de relleno de abajo.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

date_default_timezone_set('America/Mexico_City');

// BASE_URL se calcula en vez de fijarse a mano: foro/config.php ya tuvo una URL
// obsoleta tras un rename del proyecto, y este cálculo evita repetir ese error.
$raiz_proyecto = realpath(__DIR__ . '/..');
$raiz_publica = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: $raiz_proyecto;
$base_url = '';
if ($raiz_proyecto && $raiz_publica && strpos($raiz_proyecto, $raiz_publica) === 0) {
    $base_url = substr($raiz_proyecto, strlen($raiz_publica));
}
define('BASE_URL', str_replace('\\', '/', $base_url));

// --- Base de datos de la plataforma de cursos ---
define('DB_HOST', 'localhost');
define('DB_USER', 'retoarju_platform');
if (!defined('DB_PASS')) define('DB_PASS', 'reemplazar_db_password');
define('DB_NAME', 'retoarju_platform');

// --- Login con Google (placeholders: reemplazar antes de anunciar la plataforma) ---
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', 'reemplazar_google_client_id');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', 'reemplazar_google_client_secret');

// --- Pagos (placeholders: reemplazar antes de anunciar la plataforma) ---
if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', 'reemplazar_stripe_publishable');
if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', 'reemplazar_stripe_secret');
if (!defined('STRIPE_WEBHOOK_SECRET')) define('STRIPE_WEBHOOK_SECRET', 'reemplazar_stripe_webhook_secret');
define('BANCO_NOMBRE', 'Reemplazar Banco');
define('BANCO_CLABE', '000000000000000000');
define('BANCO_TITULAR', 'Reemplazar Titular');
define('WHATSAPP_PAGOS', '5210000000000');

// --- Correo saliente ---
define('EMAIL_REMITENTE', 'noreply@retoarjuna.local');
define('EMAIL_REMITENTE_NOMBRE', 'Reto Arjuna');

function config_esta_lista(string $valor): bool
{
    return stripos($valor, 'reemplazar') === false;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    ra_registrar_error('No se pudo conectar a la base de datos: ' . $conn->connect_error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar a la base de datos.']);
    exit;
}
$conn->set_charset('utf8mb4');
