<?php
// Calificaciones de curso/evento — solo se puede calificar tras terminar
// (certificado ya emitido, ver certificados.php: cursos = todas las
// lecciones completadas, eventos = asistencia marcada por el admin). Mismo
// criterio de "terminado" que ya usa la plataforma, no uno nuevo.

function usuario_puede_calificar_curso(int $usuarioId, int $cursoId): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT 1 FROM certificados WHERE usuario_id = ? AND curso_id = ? LIMIT 1');
    $stmt->bind_param('ii', $usuarioId, $cursoId);
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $existe;
}

function usuario_puede_calificar_evento(int $usuarioId, int $eventoId): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT 1 FROM certificados WHERE usuario_id = ? AND evento_id = ? LIMIT 1');
    $stmt->bind_param('ii', $usuarioId, $eventoId);
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $existe;
}

/** Calificación propia del usuario para un curso o evento (nunca ambos). */
function obtener_calificacion_usuario(int $usuarioId, ?int $cursoId, ?int $eventoId): ?array
{
    global $conn;

    $columna = $cursoId !== null ? 'curso_id' : 'evento_id';
    $itemId = $cursoId !== null ? $cursoId : $eventoId;
    $stmt = $conn->prepare("SELECT puntuacion, comentario FROM calificaciones WHERE usuario_id = ? AND $columna = ?");
    $stmt->bind_param('ii', $usuarioId, $itemId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

/** Promedio y total de calificaciones de un curso o evento, para mostrar a cualquiera. */
function calificacion_resumen(?int $cursoId, ?int $eventoId): array
{
    global $conn;

    $columna = $cursoId !== null ? 'curso_id' : 'evento_id';
    $itemId = $cursoId !== null ? $cursoId : $eventoId;
    $stmt = $conn->prepare("SELECT AVG(puntuacion) AS promedio, COUNT(*) AS total FROM calificaciones WHERE $columna = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'promedio' => $fila['total'] > 0 ? round((float) $fila['promedio'], 1) : null,
        'total' => (int) $fila['total'],
    ];
}
