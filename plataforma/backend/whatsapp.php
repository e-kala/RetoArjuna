<?php
// Envío de notificaciones por WhatsApp (Cloud API oficial de Meta) — función
// EXPERIMENTAL, deliberadamente restringida a la cuenta de caiman.mistico@gmail.com
// (ver es_super_admin() en auth.php) mientras se prueba. No confundir con
// WHATSAPP_PAGOS (el número de "wa.me" al que el usuario le escribe a mano
// para mandar su comprobante desde checkout.php) — esto es lo opuesto: NOSOTROS
// le escribimos a él.
//
// Requiere dos credenciales de Meta Business (config.local.php, ver
// config.example.php):
//   WHATSAPP_TOKEN     — access token del System User / app de Meta
//   WHATSAPP_PHONE_ID  — ID del número de WhatsApp Business dado de alta
// Mientras no estén configuradas (placeholder "reemplazar_..."), whatsapp_esta_listo()
// da false y whatsapp_enviar_plantilla() no intenta nada — mismo patrón que
// Stripe/SMTP en el resto del proyecto (ver config_esta_lista()).
//
// LA REGLA QUE HAY QUE RESPETAR: un mensaje que NOSOTROS iniciamos (el
// usuario no nos escribió primero, o pasaron más de 24h desde su último
// mensaje) solo puede mandarse como "template" — una plantilla de texto con
// variables {{1}}, {{2}}... dada de alta y aprobada de antemano en Meta
// Business Manager (Business Manager → WhatsApp Manager → Plantillas de
// mensajes). No hay forma de mandar texto libre aquí; Graph API lo rechaza.
// Por eso whatsapp_enviar_plantilla() pide el NOMBRE de una plantilla ya
// aprobada, no un mensaje armado — cambiar el texto de una plantilla después
// de aprobada exige volver a aprobarla.

function whatsapp_esta_listo(): bool
{
    return defined('WHATSAPP_TOKEN') && config_esta_lista(WHATSAPP_TOKEN)
        && defined('WHATSAPP_PHONE_ID') && config_esta_lista(WHATSAPP_PHONE_ID);
}

/**
 * Manda un mensaje de plantilla ya aprobada por Meta. $variables es la lista
 * ordenada de valores para los {{1}}, {{2}}... del cuerpo de la plantilla —
 * en el mismo orden en que se definieron al crearla en Meta Business Manager
 * (Graph API no valida nombres, solo posición).
 *
 * $telefono se limpia a solo dígitos y debe venir en formato internacional
 * completo (código de país + número, ej. "52" + "3321868372"), igual que
 * WHATSAPP_PAGOS — un usuario que guardó su teléfono a 10 dígitos (formato
 * mexicano local, sin "52") no recibirá nada hasta que WHATSAPP_LADA_DEFECTO
 * se le anteponga (ver whatsapp_normalizar_telefono()).
 *
 * Registra el intento en notificaciones_log (mismo patrón que enviar_email())
 * con tipo 'whatsapp_<tipo>', para que quede rastro de éxito/error igual que
 * el correo — panel/admin no tiene todavía una pantalla para leer este log,
 * pero ya existe para correo y conviene no duplicar el mecanismo.
 */
function whatsapp_enviar_plantilla(?int $usuarioPerfilId, string $telefono, string $nombrePlantilla, string $idiomaPlantilla, array $variables, string $tipo): bool
{
    global $conn;

    if (!whatsapp_esta_listo()) {
        return false;
    }
    $telefonoNormalizado = whatsapp_normalizar_telefono($telefono);
    if ($telefonoNormalizado === null) {
        return false;
    }

    $parametros = array_map(fn ($valor) => ['type' => 'text', 'text' => (string) $valor], $variables);
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $telefonoNormalizado,
        'type' => 'template',
        'template' => [
            'name' => $nombrePlantilla,
            'language' => ['code' => $idiomaPlantilla],
            'components' => $parametros ? [['type' => 'body', 'parameters' => $parametros]] : [],
        ],
    ];

    $enviado = whatsapp_llamar_api($payload, $error);

    $estado = $enviado ? 'enviado' : 'error';
    $stmt = $conn->prepare(
        'INSERT INTO notificaciones_log (usuario_id, tipo, destinatario, asunto, estado) VALUES (?, ?, ?, ?, ?)'
    );
    $tipoLog = 'whatsapp_' . $tipo;
    $asuntoLog = $nombrePlantilla . ($error ? (' — ' . mb_substr($error, 0, 150)) : '');
    $stmt->bind_param('issss', $usuarioPerfilId, $tipoLog, $telefonoNormalizado, $asuntoLog, $estado);
    $stmt->execute();
    $stmt->close();

    return $enviado;
}

/**
 * POST a Graph API — separado de whatsapp_enviar_plantilla() para poder
 * probarlo/reusarlo sin volver a armar el payload (ej. un futuro botón
 * "mandar de prueba" en el panel). $error queda con el mensaje de Graph API
 * si algo falló, para diagnosticar sin tener que ir a los logs del servidor.
 */
function whatsapp_llamar_api(array $payload, ?string &$error = null): bool
{
    $error = null;
    $url = 'https://graph.facebook.com/v21.0/' . WHATSAPP_PHONE_ID . '/messages';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WHATSAPP_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10, // mismo criterio que enviar_email_smtp(): nunca dejar la petición del usuario colgada por un servicio externo lento
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        $error = 'cURL: ' . $errorCurl;
        return false;
    }
    $datos = json_decode($respuesta, true);
    if ($codigoHttp >= 200 && $codigoHttp < 300 && isset($datos['messages'])) {
        return true;
    }
    $error = $datos['error']['message'] ?? ('HTTP ' . $codigoHttp);
    return false;
}

/**
 * Deja el teléfono en el formato que Graph API espera (código de país +
 * número, solo dígitos, sin "+"/espacios/guiones). usuarios_perfil.telefono
 * se captura libre en el formulario de perfil (ver perfil.php) — casi
 * siempre a 10 dígitos, sin código de país, porque hoy solo se usa para
 * mostrarlo, nunca para marcar. Si ya trae 12+ dígitos se asume que el
 * usuario incluyó su código de país y se respeta tal cual.
 */
function whatsapp_normalizar_telefono(string $telefono): ?string
{
    $soloDigitos = preg_replace('/\D+/', '', $telefono);
    if ($soloDigitos === '' || $soloDigitos === null) {
        return null;
    }
    if (strlen($soloDigitos) <= 10) {
        $ladaDefecto = defined('WHATSAPP_LADA_DEFECTO') ? WHATSAPP_LADA_DEFECTO : '52';
        $soloDigitos = $ladaDefecto . $soloDigitos;
    }
    return $soloDigitos;
}

/**
 * Plantilla configurada para un tipo de notificación (whatsapp_plantillas,
 * editable desde panel/admin/notificaciones_config.php) — null si no hay
 * fila, no tiene nombre_plantilla capturado, o está desactivada. Centraliza
 * esta lectura para que whatsapp_notificar_tipo() y el panel de admin lean
 * exactamente el mismo criterio.
 */
function whatsapp_plantilla_para_tipo(string $tipo): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT nombre_plantilla, idioma FROM whatsapp_plantillas WHERE tipo = ? AND activo = 1');
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila || trim($fila['nombre_plantilla']) === '') {
        return null;
    }
    return $fila;
}

/**
 * Punto de entrada único desde notificacion_crear() (backend/notificaciones.php)
 * — intenta mandar WhatsApp para una notificación que ya se creó en
 * plataforma+correo, sin duplicar ahí la lógica de "¿está todo listo?". No
 * hace nada (silenciosamente) si falta cualquier requisito: Cloud API sin
 * configurar, tipo sin plantilla activa, o no hay ningún teléfono a dónde
 * mandarlo — WhatsApp es un canal extra, nunca debe poder tumbar el flujo
 * principal de notificación (por eso nunca lanza, solo devuelve bool).
 *
 * $telefonoOverride (opcional) tiene prioridad sobre usuarios_perfil.telefono
 * — usado cuando el teléfono viene de otro lado más específico que el
 * perfil (ej. pagos.whatsapp_telefono, capturado junto al comprobante de
 * transferencia).
 */
function whatsapp_notificar_tipo(int $usuarioId, string $tipo, array $variables, ?string $telefonoOverride = null): bool
{
    global $conn;
    if (!whatsapp_esta_listo()) {
        return false;
    }
    $plantilla = whatsapp_plantilla_para_tipo($tipo);
    if (!$plantilla) {
        return false;
    }
    $telefono = trim((string) $telefonoOverride);
    if ($telefono === '') {
        $stmt = $conn->prepare('SELECT telefono FROM usuarios_perfil WHERE id = ?');
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $telefono = trim((string) ($stmt->get_result()->fetch_assoc()['telefono'] ?? ''));
        $stmt->close();
    }
    if ($telefono === '') {
        return false;
    }
    return whatsapp_enviar_plantilla($usuarioId, $telefono, $plantilla['nombre_plantilla'], $plantilla['idioma'], $variables, $tipo);
}
