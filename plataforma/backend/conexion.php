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

// SITE_URL es BASE_URL pero con esquema+host — BASE_URL por sí solo es solo una
// ruta relativa (sirve para href/Location, que el navegador resuelve contra la
// página actual), pero Stripe (success_url/cancel_url/return_url) y los enlaces
// dentro de correos NO tienen "página actual" contra qué resolver una ruta
// relativa — ahí hace falta la URL completa, o Stripe rechaza con "Not a valid
// URL" y los botones de los correos apuntarían al dominio equivocado.
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $esquema . $host . BASE_URL);

// --- Base de datos de la plataforma de cursos ---
// DB_HOST/DB_USER/DB_NAME antes se fijaban sin guardia — un config.local.php de
// otro entorno (ej. pruebas.arjuna.mx con retoarju_pruebas) no podía cambiarlos.
// Con el guardia, cualquier entorno puede definir su propia base sin tocar este
// archivo (que sí va en git).
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'retoarju_platform');
if (!defined('DB_PASS')) define('DB_PASS', 'reemplazar_db_password');
if (!defined('DB_NAME')) define('DB_NAME', 'retoarju_platform');

// --- Login con Google (placeholders: reemplazar antes de anunciar la plataforma) ---
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', 'reemplazar_google_client_id');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', 'reemplazar_google_client_secret');

// --- Pagos (placeholders: reemplazar antes de anunciar la plataforma) ---
if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', 'reemplazar_stripe_publishable');
if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', 'reemplazar_stripe_secret');
if (!defined('STRIPE_WEBHOOK_SECRET')) define('STRIPE_WEBHOOK_SECRET', 'reemplazar_stripe_webhook_secret');
if (!defined('BANCO_NOMBRE')) define('BANCO_NOMBRE', 'Reemplazar Banco');
if (!defined('BANCO_CLABE')) define('BANCO_CLABE', '000000000000000000');
if (!defined('BANCO_TITULAR')) define('BANCO_TITULAR', 'Reemplazar Titular');
if (!defined('WHATSAPP_PAGOS')) define('WHATSAPP_PAGOS', '5210000000000');

// --- Correo saliente ---
if (!defined('EMAIL_REMITENTE')) define('EMAIL_REMITENTE', 'noreply@retoarjuna.local');
if (!defined('EMAIL_REMITENTE_NOMBRE')) define('EMAIL_REMITENTE_NOMBRE', 'Reto Arjuna');

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
