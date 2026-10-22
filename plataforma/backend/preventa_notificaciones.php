<?php
// Avisos automáticos de módulos publicados para quienes compraron un curso
// durante una preventa. Se identifica la preventa por curso + fecha real de
// compra dentro de su vigencia; así también cubre compras donde la preventa
// se combinó con el descuento de membresía y pagos.promocion_id quedó NULL.
require_once __DIR__ . '/notificaciones.php';
require_once __DIR__ . '/mailer.php';

function preventa_notificar_leccion_publicada(int $cursoId, int $leccionId, string $cursoTitulo, string $leccionTitulo): int
{
    global $conn;
    $enlace = 'index.php?action=leccion&id=' . $leccionId;
    $stmt = $conn->prepare(
        "SELECT DISTINCT u.id, u.email_cache
         FROM pagos p
         JOIN usuarios_perfil u ON u.id = p.usuario_id
         JOIN promociones pr ON pr.curso_id = p.curso_id
            AND pr.modalidad = 'preventa'
            AND p.created_at BETWEEN pr.fecha_inicio AND pr.fecha_fin
         WHERE p.curso_id = ? AND p.estado = 'confirmado'"
    );
    $stmt->bind_param('i', $cursoId);
    $stmt->execute();
    $destinatarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total = 0;
    foreach ($destinatarios as $destinatario) {
        $usuarioId = (int) $destinatario['id'];
        // Idempotencia sin migración adicional: una notificación con este
        // enlace identifica de forma única la lección para este usuario.
        $stmt = $conn->prepare('SELECT 1 FROM notificaciones WHERE usuario_id = ? AND enlace = ? LIMIT 1');
        $stmt->bind_param('is', $usuarioId, $enlace);
        $stmt->execute();
        $yaAvisado = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        if ($yaAvisado) {
            continue;
        }

        notificacion_crear(
            $usuarioId,
            'nuevo_curso',
            'Nuevo contenido disponible',
            'Ya puedes acceder a “' . $leccionTitulo . '” de ' . $cursoTitulo . '.',
            $enlace
        );
        $html = plantilla_email(
            'Nuevo contenido disponible',
            '<p>Ya publicamos <strong>' . htmlspecialchars($leccionTitulo, ENT_QUOTES) . '</strong> de <strong>' . htmlspecialchars($cursoTitulo, ENT_QUOTES) . '</strong>.</p><p>Tu acceso de preventa ya incluye este nuevo módulo.</p>',
            'Ver nueva lección',
            SITE_URL . '/index.php?action=leccion&id=' . $leccionId
        );
        enviar_email($usuarioId, (string) $destinatario['email_cache'], 'Nuevo contenido en ' . $cursoTitulo, $html, 'preventa_contenido');
        $total++;
    }
    return $total;
}
