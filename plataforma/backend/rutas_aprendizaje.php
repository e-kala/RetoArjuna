<?php
require_once __DIR__ . '/auth.php';

function rutas_de_contenido(mysqli $conn, string $tipo, int $contenidoId, bool $soloPublicadas = true): array
{
    $sql = "SELECT r.*, ri.id AS item_id, ri.orden AS item_orden
            FROM ruta_aprendizaje_items ri
            JOIN rutas_aprendizaje r ON r.id = ri.ruta_id
            WHERE ri.tipo_contenido = ? AND ri.contenido_id = ?" . ($soloPublicadas ? " AND r.estado = 'publicada'" : '') . " ORDER BY r.orden, r.titulo";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $tipo, $contenidoId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ruta_items(mysqli $conn, int $rutaId): array
{
    $stmt = $conn->prepare("SELECT ri.*,
        CASE WHEN ri.tipo_contenido='curso' THEN c.titulo ELSE e.titulo END AS titulo,
        CASE WHEN ri.tipo_contenido='curso' THEN c.slug ELSE e.slug END AS slug,
        CASE WHEN ri.tipo_contenido='curso' THEN c.imagen_portada ELSE e.imagen_portada END AS imagen
        FROM ruta_aprendizaje_items ri
        LEFT JOIN cursos c ON ri.tipo_contenido='curso' AND c.id=ri.contenido_id
        LEFT JOIN eventos e ON ri.tipo_contenido='evento' AND e.id=ri.contenido_id
        WHERE ri.ruta_id=? ORDER BY ri.orden, ri.id");
    $stmt->bind_param('i', $rutaId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ruta_item_completado(mysqli $conn, int $usuarioId, array $item): bool
{
    $id = (int) $item['contenido_id'];
    if ($item['tipo_contenido'] === 'curso') {
        $stmt = $conn->prepare("SELECT COUNT(*) total, SUM(CASE WHEN p.completado=1 THEN 1 ELSE 0 END) hechas
            FROM lecciones l LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=?
            WHERE l.curso_id=? AND l.estado_publicacion='publicado'");
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) total, SUM(CASE WHEN p.completado=1 THEN 1 ELSE 0 END) hechas
            FROM lecciones l LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=?
            WHERE l.evento_id=? AND l.estado_publicacion='publicado'");
    }
    $stmt->bind_param('ii', $usuarioId, $id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int) $p['total'] > 0) return (int) $p['hechas'] >= (int) $p['total'];
    if ($item['tipo_contenido'] === 'evento') {
        $stmt = $conn->prepare("SELECT 1 FROM evento_inscripciones WHERE usuario_id=? AND evento_id=? AND estado='asistio' LIMIT 1");
        $stmt->bind_param('ii', $usuarioId, $id);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $ok;
    }
    return false;
}

function ruta_marcar_activa(mysqli $conn, int $usuarioId, int $rutaId, ?int $itemId = null): void
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('UPDATE usuario_rutas SET es_activa=0 WHERE usuario_id=?');
        $stmt->bind_param('i', $usuarioId); $stmt->execute(); $stmt->close();
        $stmt = $conn->prepare("INSERT INTO usuario_rutas (usuario_id,ruta_id,item_actual_id,estado,es_activa,iniciada_at,ultimo_acceso_at)
            VALUES (?,?,?,'en_progreso',1,NOW(),NOW()) ON DUPLICATE KEY UPDATE item_actual_id=COALESCE(VALUES(item_actual_id),item_actual_id), estado=IF(estado='completada',estado,'en_progreso'), es_activa=1, ultimo_acceso_at=NOW()");
        $stmt->bind_param('iii', $usuarioId, $rutaId, $itemId); $stmt->execute(); $stmt->close();
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}

function contenido_siguiente_sugerido(mysqli $conn, string $tipo, int $id): ?array
{
    $stmt = $conn->prepare("SELECT s.*, CASE WHEN s.destino_tipo='curso' THEN c.titulo ELSE e.titulo END titulo,
        CASE WHEN s.destino_tipo='curso' THEN c.slug ELSE e.slug END slug
        FROM contenido_sugerencias s
        LEFT JOIN cursos c ON s.destino_tipo='curso' AND c.id=s.destino_id AND c.activo=1
        LEFT JOIN eventos e ON s.destino_tipo='evento' AND e.id=s.destino_id AND e.activo=1
        WHERE s.origen_tipo=? AND s.origen_id=? LIMIT 1");
    $stmt->bind_param('si', $tipo, $id); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row && $row['titulo'] ? $row : null;
}
