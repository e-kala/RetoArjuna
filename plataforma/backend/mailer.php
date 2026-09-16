<?php
// Envío de correo con la función mail() nativa de PHP (sin PHPMailer, coherente con
// el resto del proyecto). Cada intento se registra en notificaciones_log, éxito o
// error, porque mail() en hosting compartido suele fallar en silencio.
//
// Dos overrides opcionales, activados solo si sus constantes están definidas en
// config.local.php (ver config.example.php para la plantilla comentada):
// - EMAIL_SMTP_HOST (+ _PORT/_USER/_PASS): en vez de mail() nativo, envía por SMTP
//   con el PHPMailer ya vendorizado en plataforma/vendor/PHPMailer/. Pensado para
//   LOCAL, donde no hay ningún servidor de correo (MTA) instalado y mail() no puede
//   enviar nada real — en pruebas.arjuna.mx/producción no se define, ahí sigue
//   usándose mail() nativo del propio hosting.
// - EMAIL_FORZAR_DESTINATARIO: manda TODO correo a esta dirección sin importar el
//   destinatario real (solo cambia a quién LLEGA el correo; notificaciones_log
//   sigue registrando el destinatario original para que quede claro para quién era).
//   Pensado para probar en local/pruebas sin arriesgar mandarle algo a un usuario
//   real — nunca se define en el config.local.php de producción.
function enviar_email(?int $usuarioPerfilId, string $destinatario, string $asunto, string $cuerpoHtml, string $tipo): bool
{
    global $conn;

    $destinatarioEnvio = $destinatario;
    $asuntoEnvio = $asunto;
    if (defined('EMAIL_FORZAR_DESTINATARIO') && EMAIL_FORZAR_DESTINATARIO !== '') {
        $destinatarioEnvio = EMAIL_FORZAR_DESTINATARIO;
        $asuntoEnvio = '[Prueba → ' . $destinatario . '] ' . $asunto;
    }

    if (defined('EMAIL_SMTP_HOST') && EMAIL_SMTP_HOST !== '') {
        $enviado = enviar_email_smtp($destinatarioEnvio, $asuntoEnvio, $cuerpoHtml);
    } else {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: ' . EMAIL_REMITENTE_NOMBRE . ' <' . EMAIL_REMITENTE . ">\r\n";
        $enviado = @mail($destinatarioEnvio, $asuntoEnvio, $cuerpoHtml, $headers);
    }

    $estado = $enviado ? 'enviado' : 'error';
    $stmt = $conn->prepare(
        'INSERT INTO notificaciones_log (usuario_id, tipo, destinatario, asunto, estado) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $usuarioPerfilId, $tipo, $destinatario, $asunto, $estado);
    $stmt->execute();
    $stmt->close();

    return $enviado;
}

function enviar_email_smtp(string $destinatario, string $asunto, string $cuerpoHtml): bool
{
    require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = EMAIL_SMTP_HOST;
        $mail->Port = defined('EMAIL_SMTP_PORT') ? (int) EMAIL_SMTP_PORT : 587;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_SMTP_USER;
        $mail->Password = EMAIL_SMTP_PASS;
        // 465 = SSL directo (SMTPS), típico de cPanel; cualquier otro puerto
        // (587 de Gmail, etc.) usa STARTTLS — se detecta solo por el puerto
        // para no necesitar una constante más en config.local.php.
        $mail->SMTPSecure = $mail->Port === 465
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(EMAIL_REMITENTE, EMAIL_REMITENTE_NOMBRE);
        $mail->addAddress($destinatario);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpoHtml;
        return $mail->send();
    } catch (\Throwable $e) {
        error_log('enviar_email_smtp: ' . $e->getMessage());
        return false;
    }
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

// Busca una plantilla personalizada activa para este tipo (ver
// panel/admin/email_plantillas.php) — la tabla es un override opcional,
// nunca la única fuente de verdad: sin fila (o con activo=0), el llamador
// se queda con su texto default hardcodeado.
function email_plantilla_personalizada(string $tipo): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT asunto, titulo, mensaje_html, boton_texto FROM email_plantillas WHERE tipo = ? AND activo = 1');
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

// Placeholders simples disponibles en el mensaje/asunto de una plantilla
// personalizada — %usuario% es el único dato que cada correo transaccional
// ya trae siempre a mano (los demás, como %curso%, varían por tipo y se
// arman aparte en cada función de abajo antes de llegar aquí).
function email_reemplazar_placeholders(string $texto, array $valores): string
{
    foreach ($valores as $clave => $valor) {
        $texto = str_replace('%' . $clave . '%', $valor, $texto);
    }
    return $texto;
}

function enviar_email_bienvenida(int $usuarioPerfilId, string $destinatario): bool
{
    $personalizada = email_plantilla_personalizada('bienvenida');
    if ($personalizada) {
        $html = plantilla_email($personalizada['titulo'], $personalizada['mensaje_html'], $personalizada['boton_texto'] ?? '', SITE_URL . '/index.php?action=cursos');
        return enviar_email($usuarioPerfilId, $destinatario, $personalizada['asunto'], $html, 'bienvenida');
    }
    $html = plantilla_email(
        '¡Bienvenido a Reto Arjuna!',
        '<p>Tu cuenta ya está lista. Explora el catálogo de cursos cuando quieras.</p>',
        'Ver cursos',
        SITE_URL . '/index.php?action=cursos'
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Bienvenido a Reto Arjuna', $html, 'bienvenida');
}

function enviar_email_inscripcion(int $usuarioPerfilId, string $destinatario, string $cursoTitulo, string $cursoUrl): bool
{
    $personalizada = email_plantilla_personalizada('inscripcion');
    if ($personalizada) {
        $mensaje = email_reemplazar_placeholders($personalizada['mensaje_html'], ['curso' => htmlspecialchars($cursoTitulo, ENT_QUOTES)]);
        $html = plantilla_email($personalizada['titulo'], $mensaje, $personalizada['boton_texto'] ?? '', $cursoUrl);
        return enviar_email($usuarioPerfilId, $destinatario, $personalizada['asunto'], $html, 'inscripcion');
    }
    $html = plantilla_email(
        '¡Tu pago fue confirmado!',
        '<p>Tu acceso a <strong>' . htmlspecialchars($cursoTitulo, ENT_QUOTES) . '</strong> ya está activo.</p>',
        'Ir al curso',
        $cursoUrl
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Inscripción confirmada', $html, 'inscripcion');
}

function enviar_email_membresia_activada(int $usuarioPerfilId, string $destinatario, string $membresiaNombre): bool
{
    $personalizada = email_plantilla_personalizada('membresia');
    if ($personalizada) {
        $mensaje = email_reemplazar_placeholders($personalizada['mensaje_html'], ['membresia' => htmlspecialchars($membresiaNombre, ENT_QUOTES)]);
        $html = plantilla_email($personalizada['titulo'], $mensaje, $personalizada['boton_texto'] ?? '', SITE_URL . '/index.php?action=membresia');
        return enviar_email($usuarioPerfilId, $destinatario, $personalizada['asunto'], $html, 'membresia');
    }
    $html = plantilla_email(
        '¡Tu membresía ya está activa!',
        '<p>Tu membresía <strong>' . htmlspecialchars($membresiaNombre, ENT_QUOTES) . '</strong> fue activada — ya tienes acceso a todos los cursos y a los eventos exclusivos para miembros.</p>',
        'Entrar a mi membresía',
        SITE_URL . '/index.php?action=membresia'
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Tu membresía Camino Arjuna está activa', $html, 'membresia');
}

function enviar_email_recuperar_contrasena(int $usuarioPerfilId, string $destinatario, string $enlaceUrl): bool
{
    $personalizada = email_plantilla_personalizada('recuperar_contrasena');
    if ($personalizada) {
        $html = plantilla_email($personalizada['titulo'], $personalizada['mensaje_html'], $personalizada['boton_texto'] ?? '', $enlaceUrl);
        return enviar_email($usuarioPerfilId, $destinatario, $personalizada['asunto'], $html, 'recuperar_contrasena');
    }
    $html = plantilla_email(
        'Recupera tu contraseña',
        '<p>Recibimos una solicitud para restablecer tu contraseña. Si no fuiste tú, ignora este correo.</p>'
        . '<p>El enlace vence en 1 hora.</p>',
        'Restablecer contraseña',
        $enlaceUrl
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Recupera tu contraseña — Reto Arjuna', $html, 'recuperar_contrasena');
}

function enviar_email_finalizacion(int $usuarioPerfilId, string $destinatario, string $cursoTitulo, string $certificadoUrl): bool
{
    $personalizada = email_plantilla_personalizada('finalizacion');
    if ($personalizada) {
        $mensaje = email_reemplazar_placeholders($personalizada['mensaje_html'], ['curso' => htmlspecialchars($cursoTitulo, ENT_QUOTES)]);
        $html = plantilla_email($personalizada['titulo'], $mensaje, $personalizada['boton_texto'] ?? '', $certificadoUrl);
        return enviar_email($usuarioPerfilId, $destinatario, $personalizada['asunto'], $html, 'finalizacion');
    }
    $html = plantilla_email(
        '¡Completaste el curso!',
        '<p>Terminaste <strong>' . htmlspecialchars($cursoTitulo, ENT_QUOTES) . '</strong>. Tu certificado ya está disponible.</p>',
        'Ver certificado',
        $certificadoUrl
    );
    return enviar_email($usuarioPerfilId, $destinatario, 'Certificado emitido', $html, 'finalizacion');
}
