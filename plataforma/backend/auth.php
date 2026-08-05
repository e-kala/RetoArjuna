<?php
// Helper central de sesión/roles para toda la plataforma. usuarios_perfil es la
// fuente de verdad de identidad (usuario/correo/contraseña/Google). Cualquier
// página que necesite saber quién está logueado o proteger una ruta debe incluir
// este archivo en vez de leer $_SESSION directamente.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/csrf.php';

/**
 * Convierte cualquier formato de URL de YouTube (watch?v=, youtu.be/, shorts/,
 * live/) a su URL de embed (la única que YouTube permite cargar dentro de un
 * <iframe> — cualquier otro formato es rechazado por su X-Frame-Options). Si la
 * URL ya es de embed, o no es de YouTube (p.ej. otro proveedor de video, o una
 * ruta a PDF), se devuelve sin cambios.
 */
function normalizar_url_youtube(string $url): string
{
    $url = trim($url);
    if ($url === '' || stripos($url, 'youtube.com/embed/') !== false) {
        return $url;
    }

    $videoId = null;
    if (preg_match('~youtu\.be/([a-zA-Z0-9_-]{6,})~i', $url, $m)) {
        $videoId = $m[1];
    } elseif (preg_match('~youtube\.com/(?:watch\?v=|shorts/|live/)([a-zA-Z0-9_-]{6,})~i', $url, $m)) {
        $videoId = $m[1];
    }

    return $videoId ? 'https://www.youtube.com/embed/' . $videoId : $url;
}

/**
 * Arranca la sesión de la plataforma para un usuario ya autenticado (contraseña
 * verificada o Google ya validado). Único punto de entrada a la sesión.
 */
function login_user(int $usuarioPerfilId): void
{
    global $conn;

    $stmt = $conn->prepare(
        'SELECT id, rol, avatar_cache, username_cache, email_cache FROM usuarios_perfil WHERE id = ?'
    );
    $stmt->bind_param('i', $usuarioPerfilId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return;
    }

    session_regenerate_id(true);
    $_SESSION['login'] = true;
    $_SESSION['usuario_perfil_id'] = (int) $row['id'];
    $_SESSION['rol'] = $row['rol'];
    $_SESSION['username'] = $row['username_cache'];
    $_SESSION['email'] = $row['email_cache'];
    $_SESSION['avatar'] = $row['avatar_cache'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['login']) && !empty($_SESSION['usuario_perfil_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['usuario_perfil_id'],
        'rol' => $_SESSION['rol'] ?? 'estudiante',
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'avatar' => $_SESSION['avatar'] ?? null,
    ];
}

function require_login(?string $redirectTo = null): void
{
    if (!is_logged_in()) {
        header('Location: ' . ($redirectTo ?? BASE_URL . '/index.php?action=ingreso'));
        exit;
    }
}

function require_role(string $rolRequerido): void
{
    require_login();
    $niveles = ['estudiante' => 1, 'instructor' => 2, 'admin' => 3];
    $actual = $niveles[$_SESSION['rol'] ?? 'estudiante'] ?? 1;
    $requerido = $niveles[$rolRequerido] ?? 99;
    if ($actual < $requerido) {
        http_response_code(403);
        echo 'No tienes permiso para acceder a esta sección.';
        exit;
    }
}

/**
 * Gate único de acceso a un curso: gratuito, o pago confirmado. Se reutiliza en el
 * detalle de curso, el visor de lección y el checkout.
 */
function usuario_tiene_acceso_curso(int $usuarioPerfilId, int $cursoId): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT gratuito FROM cursos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $cursoId);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$curso) {
        return false;
    }
    if ((int) $curso['gratuito'] === 1) {
        return true;
    }

    $stmt = $conn->prepare(
        "SELECT id FROM pagos WHERE usuario_id = ? AND curso_id = ? AND estado = 'confirmado' LIMIT 1"
    );
    $stmt->bind_param('ii', $usuarioPerfilId, $cursoId);
    $stmt->execute();
    $tieneAcceso = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $tieneAcceso;
}

/**
 * Gate de eventos: gratuito (basta con estar inscrito), o pago confirmado.
 */
function usuario_esta_inscrito_evento(int $usuarioPerfilId, int $eventoId): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT gratuito FROM eventos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$evento) {
        return false;
    }

    if ((int) $evento['gratuito'] === 1) {
        $stmt = $conn->prepare(
            "SELECT id FROM evento_inscripciones WHERE usuario_id = ? AND evento_id = ? AND estado <> 'cancelado' LIMIT 1"
        );
        $stmt->bind_param('ii', $usuarioPerfilId, $eventoId);
        $stmt->execute();
        $inscrito = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $inscrito;
    }

    $stmt = $conn->prepare(
        "SELECT id FROM pagos WHERE usuario_id = ? AND evento_id = ? AND estado = 'confirmado' LIMIT 1"
    );
    $stmt->bind_param('ii', $usuarioPerfilId, $eventoId);
    $stmt->execute();
    $pagado = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $pagado;
}
