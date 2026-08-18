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

/**
 * A dónde mandar a alguien justo después de autenticarse (login nativo,
 * registro o Google) — admins van al panel administrativo de siempre,
 * cualquier otro rol va al dashboard de estudiante (panel/dashboard.php),
 * pensado para sentirse como plataforma educativa y no como consola admin.
 */
function redirect_post_login(string $accion = ''): string
{
    $rol = $_SESSION['rol'] ?? 'estudiante';
    $destino = BASE_URL . '/panel/' . ($rol === 'admin' ? 'index.php' : 'dashboard.php');
    return $accion !== '' ? $destino . '?action=' . $accion : $destino;
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
 * Gate único de acceso a un curso: miembro activo, inscripción explícita a un
 * curso gratuito (curso_inscripciones), o pago confirmado. gratuito=1 YA NO
 * otorga acceso solo por existir — antes lo hacía, y un usuario recién
 * registrado aparecía con "acceso" a cualquier curso gratuito sin haberlo
 * pedido. Se reutiliza en el detalle de curso, el visor de lección y el checkout.
 */
function usuario_tiene_acceso_curso(int $usuarioPerfilId, int $cursoId): bool
{
    global $conn;

    if (usuario_tiene_membresia_activa($usuarioPerfilId)) {
        $stmt = $conn->prepare('SELECT incluido_membresia FROM cursos WHERE id = ?');
        $stmt->bind_param('i', $cursoId);
        $stmt->execute();
        $curso = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($curso && (int) $curso['incluido_membresia'] === 1) {
            return true;
        }
    }

    $stmt = $conn->prepare(
        "SELECT 1 FROM curso_inscripciones WHERE usuario_id = ? AND curso_id = ?
         UNION SELECT 1 FROM pagos WHERE usuario_id = ? AND curso_id = ? AND estado = 'confirmado'
         LIMIT 1"
    );
    $stmt->bind_param('iiii', $usuarioPerfilId, $cursoId, $usuarioPerfilId, $cursoId);
    $stmt->execute();
    $tieneAcceso = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $tieneAcceso;
}

/**
 * Gate de eventos: inscripción activa (evento_inscripciones, incluida la que
 * se crea gratis por membresía en eventos incluido_membresia/solo_miembros —
 * ver evento_inscribir.php) o pago confirmado. Antes esto se revisaba según
 * `gratuito` (inscripción para eventos gratis, pago para el resto), lo que
 * dejaba sin reconocer la inscripción gratuita por membresía en un evento de
 * pago — nunca aparecía en `pagos`. Revisar ambas fuentes sin importar
 * `gratuito` lo resuelve y evita depender de un bypass aparte para membresía.
 */
function usuario_esta_inscrito_evento(int $usuarioPerfilId, int $eventoId): bool
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT 1 FROM evento_inscripciones WHERE usuario_id = ? AND evento_id = ? AND estado <> 'cancelado'
         UNION SELECT 1 FROM pagos WHERE usuario_id = ? AND evento_id = ? AND estado = 'confirmado'
         LIMIT 1"
    );
    $stmt->bind_param('iiii', $usuarioPerfilId, $eventoId, $usuarioPerfilId, $eventoId);
    $stmt->execute();
    $tieneAcceso = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $tieneAcceso;
}

/**
 * true si el usuario tiene al menos una compra confirmada de este producto.
 * A diferencia de curso/evento, esto NO se usa para bloquear el checkout (un
 * producto sí se puede volver a comprar, p.ej. otra playera) — sirve solo para
 * decidir si se le muestra el enlace de descarga de un producto digital.
 */
function usuario_compro_producto(int $usuarioPerfilId, int $productoId): bool
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT id FROM pagos WHERE usuario_id = ? AND producto_id = ? AND estado = 'confirmado' LIMIT 1"
    );
    $stmt->bind_param('ii', $usuarioPerfilId, $productoId);
    $stmt->execute();
    $compro = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $compro;
}

/**
 * Gate de membresía: true si el usuario tiene una suscripción activa (y, si
 * Stripe ya reportó fin de periodo, que todavía no haya pasado).
 */
function usuario_tiene_membresia_activa(int $usuarioPerfilId): bool
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT id FROM membresia_suscripciones
         WHERE usuario_id = ? AND estado = 'activa'
           AND (periodo_actual_fin IS NULL OR periodo_actual_fin >= NOW())
         LIMIT 1"
    );
    $stmt->bind_param('i', $usuarioPerfilId);
    $stmt->execute();
    $activa = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $activa;
}

/**
 * Activa o reactiva una suscripción de membresía ya existente (fila nueva,
 * una 'pendiente' de transferencia, o una ya activa que se está editando),
 * calculando periodo_actual_fin a partir de la fecha de inicio + el intervalo
 * de la membresía — salvo que $caduca sea false, en cuyo caso queda NULL
 * (usuario_tiene_membresia_activa() ya trata NULL como "nunca vence").
 * Único punto de escritura para altas/ediciones manuales o por transferencia,
 * usado por el editor modal compartido (membresia_suscripcion_guardar.php,
 * usado desde panel/admin/usuarios.php y panel/admin/membresias.php).
 */
function activar_suscripcion_membresia(
    mysqli $conn,
    int $suscripcionId,
    string $fechaInicio,
    int $activadaPorId,
    string $metodo,
    bool $caduca = true,
    bool $renovacionAutomatica = false
): void {
    $stmt = $conn->prepare(
        'SELECT s.id, m.intervalo FROM membresia_suscripciones s JOIN membresias m ON m.id = s.membresia_id WHERE s.id = ?'
    );
    $stmt->bind_param('i', $suscripcionId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) {
        return;
    }

    $inicio = new DateTime($fechaInicio !== '' ? $fechaInicio : 'today');
    $inicioStr = $inicio->format('Y-m-d');

    $finStr = null;
    if ($caduca) {
        $fin = clone $inicio;
        $fin->modify($fila['intervalo'] === 'anual' ? '+1 year' : '+1 month');
        $finStr = $fin->format('Y-m-d H:i:s');
    }

    $renovacionInt = $renovacionAutomatica ? 1 : 0;
    $stmt = $conn->prepare(
        "UPDATE membresia_suscripciones
         SET estado = 'activa', metodo = ?, fecha_inicio = ?, periodo_actual_fin = ?, activada_por = ?, renovacion_automatica = ?
         WHERE id = ?"
    );
    $stmt->bind_param('sssiii', $metodo, $inicioStr, $finStr, $activadaPorId, $renovacionInt, $suscripcionId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Links del navbar/footer administrables desde el panel. $area filtra por
 * 'nav'/'footer' pero siempre incluye los marcados 'ambos'. Cada contexto de
 * render (raíz, plataforma/, foro/) antepone el prefijo relativo que le
 * corresponda a la `url` guardada (que siempre es relativa a la raíz del sitio).
 */
function obtener_navbar_links(string $area): array
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT texto, url, abre_nueva_pestana FROM navbar_links
         WHERE activo = 1 AND (area = ? OR area = 'ambos')
         ORDER BY orden ASC, texto ASC"
    );
    $stmt->bind_param('s', $area);
    $stmt->execute();
    $links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $links;
}

/**
 * Resuelve la `url` guardada (relativa a la raíz del sitio, o absoluta/externa)
 * al href real desde el contexto que esté renderizando: $prefijoRelativo es ''
 * en la raíz del sitio, '../' desde plataforma/ o foro/ (ambas un nivel bajo la
 * raíz). Las URLs externas (http/https) o absolutas (/...) se devuelven tal cual.
 */
function navbar_href(string $url, string $prefijoRelativo): string
{
    if (preg_match('~^(https?:)?//~i', $url) || strpos($url, '/') === 0) {
        return $url;
    }
    return $prefijoRelativo . $url;
}
