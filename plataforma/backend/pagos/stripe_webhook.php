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
            $enlace = BASE_URL . '/index.php?action=' . $accion . '&slug=' . urlencode($info['slug']);
            if ($info['tipo'] === 'curso') {
                $enlace .= '&bienvenida=1';
            }
            enviar_email_inscripcion((int) $info['usuario_id'], $info['email'], $info['titulo'], $enlace);
        }
    }
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true]);
