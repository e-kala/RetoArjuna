<?php
// Sistema de notificaciones unificado para toda la plataforma (no solo el
// foro) — una sola tabla `notificaciones`, un solo punto de creación/lectura.
// Requiere backend/auth.php (conexión, $conn) ya incluido antes.

/**
 * Crea una notificación individual para un usuario. No hace nada si el
 * destinatario es quien mismo la generó (nadie se notifica a sí mismo —
 * mismo criterio que ya usaba foro_crear_notificacion()).
 */
function notificacion_crear(int $usuarioId, string $tipo, string $titulo, ?string $mensaje, string $enlace, ?int $actorUsuarioId = null): void
{
    global $conn;
    if ($actorUsuarioId !== null && $usuarioId === $actorUsuarioId) {
        return;
    }
    $stmt = $conn->prepare(
        'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, actor_usuario_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issssi', $usuarioId, $tipo, $titulo, $mensaje, $enlace, $actorUsuarioId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Notifica a TODOS los usuarios de la plataforma (contenido nuevo: curso,
 * evento, producto, noticia) — solo si ese tipo sigue activo en
 * notificacion_config (panel/admin/notificaciones_config.php). Registra la
 * campaña en notificaciones_difusiones (ver notificacion_difundir_insertar())
 * para que el admin pueda borrarla completa después desde
 * panel/admin/notificaciones_config.php.
 */
function notificacion_difundir(string $tipo, string $titulo, ?string $mensaje, string $enlace): void
{
    global $conn;
    $stmt = $conn->prepare('SELECT activo FROM notificacion_config WHERE tipo = ?');
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila || (int) $fila['activo'] !== 1) {
        return;
    }

    notificacion_difundir_insertar($tipo, $titulo, $mensaje, $enlace, null);
}

/**
 * Difusión compuesta a mano por un admin (panel/admin/notificacion_difusion_form.php)
 * — a diferencia de notificacion_difundir(), no depende de notificacion_config
 * (esa tabla solo gobierna los disparadores AUTOMÁTICOS de contenido nuevo;
 * aquí el admin ya decidió explícitamente enviarla al darle clic a "Enviar").
 * Devuelve cuántos usuarios la recibieron, para el mensaje de confirmación.
 */
function notificacion_difundir_personalizada(string $titulo, ?string $mensaje, string $enlace, int $creadoPor): int
{
    return notificacion_difundir_insertar('aviso_admin', $titulo, $mensaje, $enlace, $creadoPor);
}

/**
 * Compartido por notificacion_difundir() y notificacion_difundir_personalizada():
 * registra la campaña en notificaciones_difusiones y luego un solo
 * INSERT...SELECT hacia `notificaciones` (en vez de un loop en PHP, para no
 * hacer N queries cuando hay muchos usuarios), enlazando cada fila a esa
 * campaña vía difusion_id.
 */
function notificacion_difundir_insertar(string $tipo, string $titulo, ?string $mensaje, string $enlace, ?int $creadoPor): int
{
    global $conn;

    $stmt = $conn->prepare('INSERT INTO notificaciones_difusiones (tipo, titulo, mensaje, enlace, creado_por) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssi', $tipo, $titulo, $mensaje, $enlace, $creadoPor);
    $stmt->execute();
    $difusionId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace, actor_usuario_id, difusion_id, leida, created_at)
         SELECT id, ?, ?, ?, ?, NULL, ?, 0, NOW() FROM usuarios_perfil"
    );
    $stmt->bind_param('ssssi', $tipo, $titulo, $mensaje, $enlace, $difusionId);
    $stmt->execute();
    $totalDestinatarios = $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare('UPDATE notificaciones_difusiones SET total_destinatarios = ? WHERE id = ?');
    $stmt->bind_param('ii', $totalDestinatarios, $difusionId);
    $stmt->execute();
    $stmt->close();

    return $totalDestinatarios;
}

/**
 * Difusiones enviadas (contenido nuevo + avisos personalizados de admin),
 * más recientes primero — panel/admin/notificaciones_config.php.
 */
function notificaciones_difusiones_recientes(int $limite = 50): array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT d.*, u.username_cache AS creado_por_username
         FROM notificaciones_difusiones d
         LEFT JOIN usuarios_perfil u ON u.id = d.creado_por
         ORDER BY d.created_at DESC LIMIT ?'
    );
    $stmt->bind_param('i', $limite);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/**
 * Elimina una difusión completa — borra la campaña y, por ON DELETE CASCADE,
 * todas las notificaciones individuales que generó, quitándola de la
 * bandeja de todos los usuarios a la vez.
 */
function notificacion_difusion_eliminar(int $difusionId): void
{
    global $conn;
    $stmt = $conn->prepare('DELETE FROM notificaciones_difusiones WHERE id = ?');
    $stmt->bind_param('i', $difusionId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Icono de Bootstrap Icons por tipo de notificación — usado tanto en el
 * dropdown de la campana (navbar.php) como en la página completa
 * (panel/content/notificaciones.php), centralizado aquí para no repetir el
 * mismo array en los dos lugares.
 */
function notificacion_icono(string $tipo): string
{
    $iconos = [
        'respuesta' => 'bi-reply-fill',
        'mencion' => 'bi-at',
        'nuevo_curso' => 'bi-book',
        'nuevo_evento' => 'bi-calendar-event',
        'nuevo_producto' => 'bi-shop',
        'nueva_noticia' => 'bi-newspaper',
        'aviso_admin' => 'bi-megaphone-fill',
    ];
    return $iconos[$tipo] ?? 'bi-bell';
}

// Duplica foro_tiempo_relativo() (foro/backend/foro_helpers.php) a propósito
// — esta función es genérica de toda la plataforma, no debería depender del
// módulo del foro solo para formatear una fecha.
function notificacion_tiempo_relativo(string $fecha): string
{
    $diff = time() - strtotime($fecha);
    if ($diff < 60) return 'hace un momento';
    if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' h';
    if ($diff < 2592000) return 'hace ' . floor($diff / 86400) . ' días';
    return date('d/m/Y', strtotime($fecha));
}

function notificaciones_no_leidas(int $usuarioId): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM notificaciones WHERE usuario_id = ? AND leida = 0');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
}

function notificaciones_recientes(int $usuarioId, int $limite = 50): array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT n.*, a.username_cache AS actor_username
         FROM notificaciones n
         LEFT JOIN usuarios_perfil a ON a.id = n.actor_usuario_id
         WHERE n.usuario_id = ?
         ORDER BY n.created_at DESC LIMIT ?'
    );
    $stmt->bind_param('ii', $usuarioId, $limite);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/**
 * Marca como leída una notificación puntual (scoped al dueño, para que nadie
 * pueda marcar la de otro) o todas las de un usuario si $notificacionId es null.
 */
function notificacion_marcar_leida(int $usuarioId, ?int $notificacionId = null): void
{
    global $conn;
    if ($notificacionId === null) {
        $stmt = $conn->prepare('UPDATE notificaciones SET leida = 1 WHERE usuario_id = ?');
        $stmt->bind_param('i', $usuarioId);
    } else {
        $stmt = $conn->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?');
        $stmt->bind_param('ii', $notificacionId, $usuarioId);
    }
    $stmt->execute();
    $stmt->close();
}
