<?php
// Cron diario de membresía OXXO — recurrencia simulada a mano (Stripe no
// soporta OXXO para cobros automáticos recurrentes, ver comentario en
// db/exportar_produccion.sql). Dos trabajos en un solo script, mismo patrón
// de deploy_auto.php (guard CLI + log propio + require directo de
// conexion.php):
//
//   1) Generar el voucher del SIGUIENTE mes a quien le vence pronto y
//      todavía no tiene uno pendiente — con margen de unos días para que
//      le dé tiempo de pagarlo en tienda antes de que se corte el acceso.
//   2) Revisar suscripciones ya vencidas: la primera vez pasan a 'gracia'
//      (2 días de acceso extra); si la gracia también se agota sin pago,
//      pasan a 'vencida' (pierde acceso — usuario_tiene_membresia_activa()
//      en auth.php ya no las cuenta).
//
// Uso en cron de cPanel (comando, no URL), una vez al día basta:
//   /usr/bin/php /home/USUARIO/public_html/plataforma/backend/membresia_oxxo_generar_vouchers.php
//
// No es accesible por navegador (ver el guard de abajo) — solo corre por CLI.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo se ejecuta por línea de comandos (cron).');
}

const DIAS_ANTICIPACION_VOUCHER = 5; // genera el voucher del próximo mes con esta anticipación
const DIAS_GRACIA = 2;               // margen tras vencer antes de cortar el acceso

$raiz = dirname(__DIR__, 2); // plataforma/backend/ -> plataforma/ -> raíz del sitio
$logDir = $raiz . '/logs';
$logFile = $logDir . '/membresia_oxxo.log';

function membresia_oxxo_log(string $msg): void
{
    global $logFile, $logDir;
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

require_once $raiz . '/plataforma/backend/conexion.php';
require_once $raiz . '/plataforma/backend/pagos/membresia_oxxo_helper.php';
require_once $raiz . '/plataforma/backend/notificaciones.php';
require_once $raiz . '/plataforma/backend/mailer.php';

membresia_oxxo_log('--- inicio ---');

// === 1) Generar voucher del próximo mes ===
// Candidatas: activas, método oxxo_recurrente, vencen dentro de
// DIAS_ANTICIPACION_VOUCHER días, y sin ya un voucher 'pendiente' (evita
// generar dos vouchers para el mismo mes si el cron corre más de una vez
// antes de que se pague el primero).
$stmt = $conn->prepare(
    "SELECT s.id, s.usuario_id, s.modo, s.periodo_actual_fin, m.precio, m.intervalo
     FROM membresia_suscripciones s
     JOIN membresias m ON m.id = s.membresia_id
     WHERE s.metodo = 'oxxo_recurrente' AND s.estado = 'activa'
       AND s.periodo_actual_fin IS NOT NULL
       AND s.periodo_actual_fin <= DATE_ADD(NOW(), INTERVAL ? DAY)
       AND NOT EXISTS (
         SELECT 1 FROM membresia_vouchers_oxxo v
         WHERE v.suscripcion_id = s.id AND v.estado = 'pendiente'
       )"
);
$diasAnticipacion = DIAS_ANTICIPACION_VOUCHER; // bind_param exige una variable, no la constante directo
$stmt->bind_param('i', $diasAnticipacion);
$stmt->execute();
$candidatasVoucher = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($candidatasVoucher as $s) {
    $periodoInicio = (new DateTime($s['periodo_actual_fin']))->format('Y-m-d');
    $periodoFinObj = new DateTime($periodoInicio);
    $periodoFinObj->modify($s['intervalo'] === 'anual' ? '+1 year' : '+1 month');
    $periodoFin = $periodoFinObj->format('Y-m-d');

    $resultado = membresia_oxxo_generar_voucher($conn, (int) $s['id'], (float) $s['precio'], $periodoInicio, $periodoFin, $s['modo']);
    if (!$resultado['success']) {
        membresia_oxxo_log('ERROR generando voucher para suscripción ' . $s['id'] . ': ' . ($resultado['message'] ?? 'sin detalle'));
        continue;
    }
    membresia_oxxo_completar_datos_voucher($conn, $resultado['payment_intent_id'], $s['modo']);
    membresia_oxxo_log('Voucher generado para suscripción ' . $s['id'] . ' (periodo ' . $periodoInicio . ' a ' . $periodoFin . ')');

    $usuarioIdCron = (int) $s['usuario_id'];
    $stmtU = $conn->prepare('SELECT email_cache FROM usuarios_perfil WHERE id = ?');
    $stmtU->bind_param('i', $usuarioIdCron);
    $stmtU->execute();
    $email = $stmtU->get_result()->fetch_assoc()['email_cache'] ?? null;
    $stmtU->close();

    notificacion_crear(
        (int) $s['usuario_id'],
        'voucher_membresia',
        'Tu voucher OXXO del mes está listo',
        'Paga antes de que venza para no perder tu acceso a Camino Arjuna.',
        BASE_URL . '/panel/dashboard.php?action=mi_membresia'
    );

    if ($email) {
        $cuerpo = plantilla_email(
            'Tu voucher OXXO está listo',
            '<p>Genera tu pago en tienda para seguir con tu membresía Camino Arjuna. Puedes verlo y pagarlo desde tu panel.</p>',
            'Ver mi voucher',
            SITE_URL . '/panel/dashboard.php?action=mi_membresia'
        );
        enviar_email((int) $s['usuario_id'], $email, 'Tu voucher OXXO está listo — Camino Arjuna', $cuerpo, 'voucher_membresia');
    }
}

// === 2) Vencimientos: activa -> gracia -> vencida ===
// activa -> gracia NO requiere sincronizar eventos regulares: 'gracia'
// sigue contando como membresía activa en usuario_tiene_membresia_activa()
// (auth.php), así que no se pierde ningún acceso todavía en esta transición.
$stmt = $conn->prepare(
    "UPDATE membresia_suscripciones
     SET estado = 'gracia'
     WHERE metodo = 'oxxo_recurrente' AND estado = 'activa' AND periodo_actual_fin < NOW()"
);
$stmt->execute();
$pasaronAGracia = $stmt->affected_rows;
$stmt->close();
membresia_oxxo_log("Suscripciones que pasaron a 'gracia': $pasaronAGracia");

// gracia -> vencida SÍ pierde acceso — hay que capturar qué usuarios se ven
// afectados ANTES del UPDATE (que actúa sobre el conjunto de golpe) para
// poder sincronizar sus eventos regulares después.
$stmt = $conn->prepare(
    "SELECT usuario_id FROM membresia_suscripciones
     WHERE metodo = 'oxxo_recurrente' AND estado = 'gracia'
       AND periodo_actual_fin < DATE_SUB(NOW(), INTERVAL ? DAY)"
);
$diasGracia = DIAS_GRACIA; // bind_param exige una variable, no la constante directo
$stmt->bind_param('i', $diasGracia);
$stmt->execute();
$usuariosQuePierdenAccesoRegular = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'usuario_id');
$stmt->close();

$stmt = $conn->prepare(
    "UPDATE membresia_suscripciones
     SET estado = 'vencida'
     WHERE metodo = 'oxxo_recurrente' AND estado = 'gracia'
       AND periodo_actual_fin < DATE_SUB(NOW(), INTERVAL ? DAY)"
);
$stmt->bind_param('i', $diasGracia);
$stmt->execute();
$pasaronAVencida = $stmt->affected_rows;
$stmt->close();
membresia_oxxo_log("Suscripciones que pasaron a 'vencida': $pasaronAVencida");

require_once __DIR__ . '/eventos_regulares.php';
foreach ($usuariosQuePierdenAccesoRegular as $uid) {
    sincronizar_inscripciones_regulares((int) $uid, false);
}

membresia_oxxo_log('--- fin ---');
