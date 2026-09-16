<?php
// Lógica de segmentación compartida entre email_campana_form.php (contar y
// crear la campaña) y email_campana_enviar_lote.php (resolver el mismo
// segmento lote por lote) — un solo lugar para la definición de cada
// segmento, para que contar y enviar nunca puedan divergir.

// Mismo criterio de "inactivo" que panel/admin/inactividad.php (30+ días
// sin ultimo_login, o desde created_at si nunca inició sesión). "Sin
// compra" mira solo pagos confirmados (no inscripciones gratuitas) — es la
// definición más útil para una campaña de "todavía no compras nada".
function email_campana_segmento_sql(string $segmento): string
{
    return match ($segmento) {
        'inactivos' => "SELECT id, username_cache, email_cache FROM usuarios_perfil
                         WHERE activo = 1
                           AND COALESCE(ultimo_login, created_at) < DATE_SUB(NOW(), INTERVAL 30 DAY)
                         ORDER BY id",
        'miembros' => "SELECT DISTINCT u.id, u.username_cache, u.email_cache FROM usuarios_perfil u
                        JOIN membresia_suscripciones s ON s.usuario_id = u.id
                        WHERE u.activo = 1 AND s.estado = 'activa'
                          AND (s.periodo_actual_fin IS NULL OR s.periodo_actual_fin >= NOW())
                        ORDER BY u.id",
        'sin_compra' => "SELECT id, username_cache, email_cache FROM usuarios_perfil u
                          WHERE activo = 1
                            AND NOT EXISTS (SELECT 1 FROM pagos p WHERE p.usuario_id = u.id AND p.estado = 'confirmado')
                          ORDER BY id",
        default => "SELECT id, username_cache, email_cache FROM usuarios_perfil WHERE activo = 1 ORDER BY id",
    };
}

function email_campana_contar(mysqli $conn, string $segmento): int
{
    $sql = email_campana_segmento_sql($segmento);
    return (int) $conn->query("SELECT COUNT(*) AS n FROM ({$sql}) t")->fetch_assoc()['n'];
}

$GLOBALS['email_campana_segmento_label'] = [
    'todos' => 'Todos los usuarios activos',
    'inactivos' => 'Inactivos (30+ días sin iniciar sesión)',
    'miembros' => 'Miembros activos',
    'sin_compra' => 'Sin ninguna compra confirmada',
];
