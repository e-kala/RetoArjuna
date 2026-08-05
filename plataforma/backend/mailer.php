<?php
// Envío de correo con la función mail() nativa de PHP (sin PHPMailer, coherente con
// el resto del proyecto). Cada intento se registra en notificaciones_log, éxito o
// error, porque mail() en hosting compartido suele fallar en silencio.

function enviar_email(?int $usuarioPerfilId, string $destinatario, string $asunto, string $cuerpoHtml, string $tipo): bool
{
    global $conn;

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . EMAIL_REMITENTE_NOMBRE . ' <' . EMAIL_REMITENTE . ">\r\n";

    $enviado = @mail($destinatario, $asunto, $cuerpoHtml, $headers);

    $estado = $enviado ? 'enviado' : 'error';
    $stmt = $conn->prepare(
        'INSERT INTO notificaciones_log (usuario_id, tipo, destinatario, asunto, estado) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $usuarioPerfilId, $tipo, $destinatario, $asunto, $estado);
    $stmt->execute();
    $stmt->close();

    return $enviado;
}

function plantilla_email(string $titulo, string $mensajeHtml, string $botonTexto = '', string $botonUrl = ''): string
{
    $boton = '';
    if ($botonTexto !== '' && $botonUrl !== '') {
        $boton = '<p style="margin:24px 0;"><a href="' . htmlspecialchars($botonUrl, ENT_QUOTES) . '" '
            . 'style="background:#f7931e;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">'
            . htmlspecialchars($botonTexto, ENT_QUOTES) . '</a></p>';
    }

    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">'
        . '<h2 style="color:#f7931e;">' . htmlspecialchars($titulo, ENT_QUOTES) . '</h2>'
        . '<div style="color:#333;line-height:1.5;">' . $mensajeHtml . '</div>'
        . $boton
        . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">'
        . '<p style="color:#999;font-size:12px;">Reto Arjuna</p>'
        . '</div>';
}

function enviar_email_bienvenida(int $usuarioPerfilId, string $destinatario): bool
{
    $html = plantilla_email(
        '¡Bienvenido a Reto Arjuna!',
        '<p>Tu cuenta ya está lista. Explora el catálogo de cursos cuando quieras.</p>',
        'Ver cursos',
        BASE_URL . '/index.php?action=cursos'
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Bienvenido a Reto Arjuna', $html, 'bienvenida');
}

function enviar_email_inscripcion(int $usuarioPerfilId, string $destinatario, string $cursoTitulo, string $cursoUrl): bool
{
    $html = plantilla_email(
        '¡Tu pago fue confirmado!',
        '<p>Tu acceso a <strong>' . htmlspecialchars($cursoTitulo, ENT_QUOTES) . '</strong> ya está activo.</p>',
        'Ir al curso',
        $cursoUrl
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Inscripción confirmada', $html, 'inscripcion');
}

function enviar_email_finalizacion(int $usuarioPerfilId, string $destinatario, string $cursoTitulo, string $certificadoUrl): bool
{
    $html = plantilla_email(
        '¡Completaste el curso!',
        '<p>Terminaste <strong>' . htmlspecialchars($cursoTitulo, ENT_QUOTES) . '</strong>. Tu certificado ya está disponible.</p>',
        'Ver certificado',
        $certificadoUrl
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Certificado emitido', $html, 'finalizacion');
}
