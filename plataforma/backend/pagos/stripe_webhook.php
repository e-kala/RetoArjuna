<?php
// Webhook público de Stripe: no lleva sesión ni CSRF (Stripe no puede enviarlos),
// la seguridad viene de verificar la firma HMAC de la cabecera Stripe-Signature.
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../mailer.php';

function verificar_firma_stripe(string $payload, string $header, string $secret): bool
{
    $partes = [];
    foreach (explode(',', $header) as $par) {
        [$clave, $valor] = array_pad(explode('=', $par, 2), 2, null);
        $partes[$clave][] = $valor;
    }
    if (empty($partes['t'][0]) || empty($partes['v1'][0])) {
        return false;
    }
    if (abs(time() - (int) $partes['t'][0]) > 300) {
        return false; // Tolerancia de 5 min recomendada por Stripe, evita ataques de repetición.
    }
    $esperado = hash_hmac('sha256', $partes['t'][0] . '.' . $payload, $secret);
    return hash_equals($esperado, $partes['v1'][0]);
}

$payload = file_get_contents('php://input');
$firma = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Un webhook no tiene sesión (lo llama Stripe, no un usuario logueado), así que
// no hay forma de preguntar "¿el modo prueba está activo?" como en el resto de
// pagos/ — en su lugar, se prueba la firma contra los dos secretos posibles
// (LIVE y, si está configurado, PRUEBA) y el que valide dice de qué modo es
// este evento. Así un solo endpoint sirve para los dos modos sin duplicar URL.
$modoWebhook = null;
if (config_esta_lista(STRIPE_WEBHOOK_SECRET) && verificar_firma_stripe($payload, $firma, STRIPE_WEBHOOK_SECRET)) {
    $modoWebhook = 'live';
} elseif (config_esta_lista(STRIPE_WEBHOOK_SECRET_PRUEBA) && verificar_firma_stripe($payload, $firma, STRIPE_WEBHOOK_SECRET_PRUEBA)) {
    $modoWebhook = 'prueba';
}

if ($modoWebhook === null) {
    http_response_code(400);
    echo 'Firma inválida';
    exit;
}

$evento = json_decode($payload, true);
if (($evento['type'] ?? '') === 'payment_intent.succeeded' && ($evento['data']['object']['metadata']['tipo'] ?? '') === 'membresia_oxxo') {
    // Voucher OXXO de membresía pagado (alta inicial o renovación mensual —
    // ver backend/pagos/membresia_oxxo_helper.php). Distinto del bloque de
    // abajo (que es para pagos.curso/evento/producto): esta rama nunca toca
    // la tabla `pagos`, solo membresia_vouchers_oxxo y
    // membresia_suscripciones. Se resuelve aquí y no cae al bloque
    // genérico de abajo (los metadata no se parecen en nada).
    require_once __DIR__ . '/membresia_oxxo_helper.php';
    $intentObj = $evento['data']['object'];
    $intentId = (string) ($intentObj['id'] ?? '');
    $suscripcionId = (int) ($intentObj['metadata']['suscripcion_id'] ?? 0);
    $periodoFin = (string) ($intentObj['metadata']['periodo_fin'] ?? '');

    $stmt = $conn->prepare("UPDATE membresia_vouchers_oxxo SET estado = 'pagado' WHERE payment_intent_id = ? AND estado <> 'pagado'");
    $stmt->bind_param('s', $intentId);
    $stmt->execute();
    $afectadosVoucher = $stmt->affected_rows;
    $stmt->close();

    if ($afectadosVoucher > 0 && $suscripcionId && $periodoFin) {
        $stmt = $conn->prepare(
            "SELECT s.estado, s.usuario_id, u.email_cache, m.nombre AS membresia_nombre
             FROM membresia_suscripciones s
             JOIN usuarios_perfil u ON u.id = s.usuario_id
             JOIN membresias m ON m.id = s.membresia_id
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $suscripcionId);
        $stmt->execute();
        $suscripcionPrevia = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($suscripcionPrevia) {
            $eraPrimeraVez = $suscripcionPrevia['estado'] === 'pendiente';
            $finDatetime = $periodoFin . ' 23:59:59';
            $stmt = $conn->prepare(
                "UPDATE membresia_suscripciones SET estado = 'activa', fecha_inicio = COALESCE(fecha_inicio, CURDATE()), periodo_actual_fin = ? WHERE id = ?"
            );
            $stmt->bind_param('si', $finDatetime, $suscripcionId);
            $stmt->execute();
            $stmt->close();

            if ($eraPrimeraVez && $suscripcionPrevia['email_cache']) {
                enviar_email_membresia_activada((int) $suscripcionPrevia['usuario_id'], $suscripcionPrevia['email_cache'], $suscripcionPrevia['membresia_nombre']);
            }
        }
    }
} elseif (($evento['type'] ?? '') === 'payment_intent.succeeded') {
    $intentId = $evento['data']['object']['id'] ?? '';

    $stmt = $conn->prepare(
        "UPDATE pagos SET estado = 'confirmado', fecha_pago = NOW(), fecha_validacion = NOW()
         WHERE transaccion_id = ? AND metodo_pago = 'stripe' AND estado <> 'confirmado'"
    );
    $stmt->bind_param('s', $intentId);
    $stmt->execute();
    $afectados = $stmt->affected_rows;
    $stmt->close();

    if ($afectados > 0) {
        $stmt = $conn->prepare(
            "SELECT p.usuario_id, p.evento_id, u.email_cache AS email,
                    COALESCE(c.titulo, e.titulo, pr.nombre) AS titulo,
                    CASE WHEN p.curso_id IS NOT NULL THEN 'curso' WHEN p.evento_id IS NOT NULL THEN 'evento' ELSE 'producto' END AS tipo,
                    COALESCE(c.slug, e.slug, pr.slug) AS slug
             FROM pagos p
             JOIN usuarios_perfil u ON u.id = p.usuario_id
             LEFT JOIN cursos c ON c.id = p.curso_id
             LEFT JOIN eventos e ON e.id = p.evento_id
             LEFT JOIN productos pr ON pr.id = p.producto_id
             WHERE p.transaccion_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $intentId);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Un evento pagado con Stripe también necesita su fila en
        // evento_inscripciones — no solo el registro en `pagos` — porque
        // panel/content/mis_eventos.php (y el cupo máximo del evento) leen
        // de ahí directo, sin pasar por usuario_esta_inscrito_evento()
        // (auth.php), que sí acepta cualquiera de las dos fuentes. Sin este
        // INSERT, el usuario tenía acceso real al evento pero nunca aparecía
        // en "Mis eventos" — mismo INSERT que ya usa evento_inscribir.php
        // para el camino gratuito/incluido por membresía.
        if ($info && $info['tipo'] === 'evento' && $info['evento_id']) {
            $stmtInscribe = $conn->prepare(
                "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado) VALUES (?, ?, 'inscrito')
                 ON DUPLICATE KEY UPDATE estado = IF(estado = 'cancelado', 'inscrito', estado)"
            );
            $stmtInscribe->bind_param('ii', $info['usuario_id'], $info['evento_id']);
            $stmtInscribe->execute();
            $stmtInscribe->close();
        }

        if ($info && $info['email']) {
            $accion = ['curso' => 'curso', 'evento' => 'evento', 'producto' => 'producto'][$info['tipo']];
            $enlace = SITE_URL . '/index.php?action=' . $accion . '&slug=' . urlencode($info['slug']);
            if ($info['tipo'] === 'curso') {
                $enlace .= '&bienvenida=1';
            }
            enviar_email_inscripcion((int) $info['usuario_id'], $info['email'], $info['titulo'], $enlace);
        }
    }
} elseif (($evento['type'] ?? '') === 'payment_intent.payment_failed') {
    // Vencimiento de un voucher OXXO sin pagarse (documentado por Stripe:
    // payment_intent.payment_failed = "el cliente no pagó el vale OXXO antes
    // del vencimiento" — https://docs.stripe.com/payments/oxxo). El mismo
    // tipo de evento también existe para una tarjeta rechazada, pero ese
    // caso el usuario ya lo ve al instante en checkout.php (el error de
    // confirmPayment() se muestra ahí mismo) — para no confundir ambos casos
    // sin depender de inspeccionar un código de error interno, se usa
    // payment_method_types del propio PaymentIntent (siempre presente,
    // documentado y estable): solo se marca 'rechazado' cuando el intento
    // se limitó a ['oxxo'] — nunca cuando 'card' es una opción, ese caso el
    // usuario todavía puede reintentar con otra tarjeta sobre el mismo
    // PaymentIntent y no debe perder la fila en 'pendiente'.
    $intentFallido = $evento['data']['object'] ?? [];
    $intentId = (string) ($intentFallido['id'] ?? '');
    $metodosIntento = $intentFallido['payment_method_types'] ?? [];
    if ($intentId && $metodosIntento === ['oxxo']) {
        $stmt = $conn->prepare(
            "UPDATE pagos SET estado = 'rechazado'
             WHERE transaccion_id = ? AND metodo_pago = 'stripe' AND estado = 'pendiente'"
        );
        $stmt->bind_param('s', $intentId);
        $stmt->execute();
        $stmt->close();

        // Mismo evento, esta vez para un voucher de MEMBRESÍA vencido — solo
        // se marca el voucher como 'vencido' aquí. La membresía en sí (pasar
        // a 'gracia' y, tras 2 días más, a 'vencida') la decide el cron
        // membresia_oxxo_generar_vouchers.php, no este webhook — así, sin
        // importar si Stripe tarda en avisar (hasta 10 días documentados),
        // el cron es la única fuente de verdad de cuándo se acaban los 2
        // días de gracia, contados desde periodo_actual_fin real.
        $stmt = $conn->prepare("UPDATE membresia_vouchers_oxxo SET estado = 'vencido' WHERE payment_intent_id = ? AND estado = 'pendiente'");
        $stmt->bind_param('s', $intentId);
        $stmt->execute();
        $stmt->close();
    }
} elseif (($evento['type'] ?? '') === 'checkout.session.completed') {
    // Colchón de transición, ya no es la vía principal: desde que
    // membresia_iniciar.php crea la Subscription directo (Payment Element
    // embebido, sin Checkout Session), este evento nunca se vuelve a disparar
    // para una suscripción nueva. Se deja sin borrar solo por si queda alguna
    // sesión de Checkout vieja a medias justo antes de este cambio — si esa
    // sesión se completa después del despliegue, esta es la única red que le
    // crea su fila (sin esto, ese cliente pagaría sin quedar registrado).
    $sesion = $evento['data']['object'] ?? [];
    if (($sesion['mode'] ?? '') === 'subscription') {
        $usuarioId = (int) ($sesion['metadata']['usuario_id'] ?? 0);
        $membresiaId = (int) ($sesion['metadata']['membresia_id'] ?? 0);
        $customerId = (string) ($sesion['customer'] ?? '');
        $subscriptionId = (string) ($sesion['subscription'] ?? '');

        if ($usuarioId && $membresiaId && $subscriptionId) {
            $stmt = $conn->prepare(
                "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, stripe_customer_id, stripe_subscription_id, estado, modo)
                 VALUES (?, ?, ?, ?, 'activa', ?)
                 ON DUPLICATE KEY UPDATE estado = 'activa', stripe_customer_id = VALUES(stripe_customer_id)"
            );
            $stmt->bind_param('iisss', $usuarioId, $membresiaId, $customerId, $subscriptionId, $modoWebhook);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('SELECT email_cache FROM usuarios_perfil WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $usuarioId);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($u && $u['email_cache']) {
                $cuerpo = plantilla_email(
                    '¡Bienvenido al Camino Arjuna!',
                    '<p>Tu membresía ya está activa. Este es tu espacio de práctica continua — el lugar al que vuelves para reorientarte.</p>',
                    'Entrar a mi membresía',
                    SITE_URL . '/index.php?action=membresia'
                );
                enviar_email($usuarioId, $u['email_cache'], 'Tu membresía Camino Arjuna está activa', $cuerpo, 'membresia');
            }
        }
    }
} elseif (($evento['type'] ?? '') === 'invoice.paid') {
    $factura = $evento['data']['object'] ?? [];
    $subscriptionId = (string) ($factura['subscription'] ?? '');
    $finPeriodo = $factura['lines']['data'][0]['period']['end'] ?? null;

    if ($subscriptionId && $finPeriodo) {
        // Se lee el estado ANTES de actualizar para saber si esta es la
        // primera factura pagada (pendiente -> activa, con el flujo nuevo de
        // Subscription creada directo desde membresia_iniciar.php) o una
        // renovación (activa/vencida -> activa) — el correo de bienvenida
        // solo se manda en la primera transición, no en cada mes.
        $stmt = $conn->prepare(
            "SELECT s.estado, s.usuario_id, u.email_cache, m.nombre AS membresia_nombre
             FROM membresia_suscripciones s
             JOIN usuarios_perfil u ON u.id = s.usuario_id
             JOIN membresias m ON m.id = s.membresia_id
             WHERE s.stripe_subscription_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $subscriptionId);
        $stmt->execute();
        $suscripcionPrevia = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $finPeriodoFecha = date('Y-m-d H:i:s', (int) $finPeriodo);
        $stmt = $conn->prepare(
            "UPDATE membresia_suscripciones SET estado = 'activa', periodo_actual_fin = ?
             WHERE stripe_subscription_id = ?"
        );
        $stmt->bind_param('ss', $finPeriodoFecha, $subscriptionId);
        $stmt->execute();
        $stmt->close();

        // Mismo criterio que la verificación síncrona en content/membresia.php:
        // descarta otros intentos de Stripe abandonados del mismo usuario, para
        // que no se queden huérfanos en "Pendientes de confirmar".
        if ($suscripcionPrevia) {
            $stmtLimpia = $conn->prepare(
                "DELETE FROM membresia_suscripciones
                 WHERE usuario_id = ? AND metodo = 'stripe' AND estado = 'pendiente' AND stripe_subscription_id <> ?"
            );
            $stmtLimpia->bind_param('is', $suscripcionPrevia['usuario_id'], $subscriptionId);
            $stmtLimpia->execute();
            $stmtLimpia->close();
        }

        if ($suscripcionPrevia && $suscripcionPrevia['estado'] === 'pendiente' && $suscripcionPrevia['email_cache']) {
            enviar_email_membresia_activada(
                (int) $suscripcionPrevia['usuario_id'],
                $suscripcionPrevia['email_cache'],
                $suscripcionPrevia['membresia_nombre']
            );
        }
    }
} elseif (($evento['type'] ?? '') === 'customer.subscription.deleted') {
    $suscripcion = $evento['data']['object'] ?? [];
    $subscriptionId = (string) ($suscripcion['id'] ?? '');

    if ($subscriptionId) {
        $stmt = $conn->prepare("UPDATE membresia_suscripciones SET estado = 'cancelada' WHERE stripe_subscription_id = ?");
        $stmt->bind_param('s', $subscriptionId);
        $stmt->execute();
        $stmt->close();
    }
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true]);
