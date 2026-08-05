<?php
// Emisión de certificados. Se invoca tras marcar una lección o aprobar un quiz.

function verificar_y_emitir_certificado(int $usuarioPerfilId, int $cursoId): void
{
    global $conn;

    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM lecciones WHERE curso_id = ?');
    $stmt->bind_param('i', $cursoId);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($total === 0) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS completadas FROM progreso
         WHERE usuario_id = ? AND curso_id = ? AND completado = 1"
    );
    $stmt->bind_param('ii', $usuarioPerfilId, $cursoId);
    $stmt->execute();
    $completadas = (int) $stmt->get_result()->fetch_assoc()['completadas'];
    $stmt->close();

    if ($completadas < $total) {
        return;
    }

    $stmt = $conn->prepare('SELECT id FROM certificados WHERE usuario_id = ? AND curso_id = ? LIMIT 1');
    $stmt->bind_param('ii', $usuarioPerfilId, $cursoId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        return;
    }
    $stmt->close();

    $codigo = strtoupper(bin2hex(random_bytes(6)));
    $stmt = $conn->prepare('INSERT INTO certificados (usuario_id, curso_id, codigo) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $usuarioPerfilId, $cursoId, $codigo);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT u.email_cache AS email, c.titulo FROM usuarios_perfil u, cursos c
         WHERE u.id = ? AND c.id = ?'
    );
    $stmt->bind_param('ii', $usuarioPerfilId, $cursoId);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($info && $info['email']) {
        require_once __DIR__ . '/mailer.php';
        enviar_email_finalizacion(
            $usuarioPerfilId,
            $info['email'],
            $info['titulo'],
            BASE_URL . '/certificado.php?codigo=' . urlencode($codigo)
        );
    }
}

/**
 * Reconocimiento por asistencia a un evento (no hay "lecciones" que completar
 * aquí: el criterio es simplemente que el admin haya marcado la asistencia,
 * ver panel/admin/evento_inscritos.php). Mismo patrón idempotente que los
 * certificados de curso.
 */
function verificar_y_emitir_reconocimiento_evento(int $usuarioPerfilId, int $eventoId): void
{
    global $conn;

    $stmt = $conn->prepare('SELECT id FROM certificados WHERE usuario_id = ? AND evento_id = ? LIMIT 1');
    $stmt->bind_param('ii', $usuarioPerfilId, $eventoId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        return;
    }
    $stmt->close();

    $codigo = strtoupper(bin2hex(random_bytes(6)));
    $stmt = $conn->prepare("INSERT INTO certificados (usuario_id, evento_id, tipo, codigo) VALUES (?, ?, 'evento', ?)");
    $stmt->bind_param('iis', $usuarioPerfilId, $eventoId, $codigo);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT u.email_cache AS email, e.titulo FROM usuarios_perfil u, eventos e
         WHERE u.id = ? AND e.id = ?'
    );
    $stmt->bind_param('ii', $usuarioPerfilId, $eventoId);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($info && $info['email']) {
        require_once __DIR__ . '/mailer.php';
        enviar_email_finalizacion(
            $usuarioPerfilId,
            $info['email'],
            $info['titulo'],
            BASE_URL . '/certificado.php?codigo=' . urlencode($codigo)
        );
    }
}
