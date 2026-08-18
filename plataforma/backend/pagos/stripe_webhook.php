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

if (!config_esta_lista(STRIPE_WEBHOOK_SECRET) || !verificar_firma_stripe($payload, $firma, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    echo 'Firma inválida';
    exit;
}

$evento = json_decode($payload, true);
if (($evento['type'] ?? '') === 'payment_intent.succeeded') {
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
            "SELECT p.usuario_id, u.email_cache AS email,
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

        if ($info && $info['email']) {
            $accion = ['curso' => 'curso', 'evento' => 'evento', 'producto' => 'producto'][$info['tipo']];
            $enlace = SITE_URL . '/index.php?action=' . $accion . '&slug=' . urlencode($info['slug']);
            if ($info['tipo'] === 'curso') {
                $enlace .= '&bienvenida=1';
            }
            enviar_email_inscripcion((int) $info['usuario_id'], $info['email'], $info['titulo'], $enlace);
        }
    }
} elseif (($evento['type'] ?? '') === 'checkout.session.completed') {
    $sesion = $evento['data']['object'] ?? [];
    if (($sesion['mode'] ?? '') === 'subscription') {
        $usuarioId = (int) ($sesion['metadata']['usuario_id'] ?? 0);
        $membresiaId = (int) ($sesion['metadata']['membresia_id'] ?? 0);
        $customerId = (string) ($sesion['customer'] ?? '');
        $subscriptionId = (string) ($sesion['subscription'] ?? '');

        if ($usuarioId && $membresiaId && $subscriptionId) {
            $stmt = $conn->prepare(
                "INSERT INTO membresia_suscripciones (usuario_id, membresia_id, stripe_customer_id, stripe_subscription_id, estado)
                 VALUES (?, ?, ?, ?, 'activa')
                 ON DUPLICATE KEY UPDATE estado = 'activa', stripe_customer_id = VALUES(stripe_customer_id)"
            );
            $stmt->bind_param('iiss', $usuarioId, $membresiaId, $customerId, $subscriptionId);
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
        $finPeriodoFecha = date('Y-m-d H:i:s', (int) $finPeriodo);
        $stmt = $conn->prepare(
            "UPDATE membresia_suscripciones SET estado = 'activa', periodo_actual_fin = ?
             WHERE stripe_subscription_id = ?"
        );
        $stmt->bind_param('ss', $finPeriodoFecha, $subscriptionId);
        $stmt->execute();
        $stmt->close();
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
