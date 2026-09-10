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
if (!defined('DB_PASS')) define('DB_PASS', 'reemplazar_db_password');
if (!defined('DB_USER')) define('DB_USER', 'retoarju_platform');
if (!defined('DB_NAME')) define('DB_NAME', 'retoarju_platform');
//if (!defined('DB_NAME')) define('DB_NAME', 'retoarju_pruebas');
// --- Login con Google (placeholders: reemplazar antes de anunciar la plataforma) ---
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', 'reemplazar_google_client_id');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', 'reemplazar_google_client_secret');

// --- Pagos (placeholders: reemplazar antes de anunciar la plataforma) ---
if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', 'reemplazar_stripe_publishable');
if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', 'reemplazar_stripe_secret');
if (!defined('STRIPE_WEBHOOK_SECRET')) define('STRIPE_WEBHOOK_SECRET', 'reemplazar_stripe_webhook_secret');
// Llaves TEST opcionales — solo hacen falta si se quiere que el modo prueba de
// Stripe (activable por un admin, ver stripe_helper.php) tenga efecto real en
// este entorno. Si no se definen, el toggle sigue existiendo pero no cambia
// nada (siempre cae de vuelta a las llaves LIVE de arriba).
if (!defined('STRIPE_PUBLISHABLE_KEY_PRUEBA')) define('STRIPE_PUBLISHABLE_KEY_PRUEBA', 'reemplazar_stripe_publishable_prueba');
if (!defined('STRIPE_SECRET_KEY_PRUEBA')) define('STRIPE_SECRET_KEY_PRUEBA', 'reemplazar_stripe_secret_prueba');
if (!defined('STRIPE_WEBHOOK_SECRET_PRUEBA')) define('STRIPE_WEBHOOK_SECRET_PRUEBA', 'reemplazar_stripe_webhook_secret_prueba');
if (!defined('BANCO_NOMBRE')) define('BANCO_NOMBRE', 'BBVA');
if (!defined('BANCO_CLABE')) define('BANCO_CLABE', '012 320 01513856243 2');
if (!defined('BANCO_TITULAR')) define('BANCO_TITULAR', 'Srivas Das Cervantes Torres');
if (!defined('WHATSAPP_PAGOS')) define('WHATSAPP_PAGOS', '523321868372');

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

// date_default_timezone_set() de arriba solo afecta las funciones de fecha
// de PHP — MySQL calcula NOW()/CURRENT_TIMESTAMP() con el huso horario del
// propio sistema operativo del servidor de base de datos (casi siempre UTC
// en hosting compartido), sin importar nada de lo que haga PHP. Todo lo que
// compara contra NOW() en SQL (fecha_inicio de eventos, expira_en de
// recuperar contraseña, periodo_actual_fin de membresías, vigencia de
// promociones, publicada_at de noticias...) quedaba comparando una fecha en
// hora de CDMX contra un "ahora" en UTC — hasta 6 horas de diferencia. Se
// fija aquí con un offset numérico fijo (no por nombre de zona, que requiere
// las tablas de zonas horarias de MySQL cargadas — no garantizado en hosting
// compartido) — México ya no tiene horario de verano nacional desde 2022,
// así que CDMX es UTC-6 fijo todo el año, sin casos especiales que manejar.
$conn->query("SET time_zone = '-06:00'");
