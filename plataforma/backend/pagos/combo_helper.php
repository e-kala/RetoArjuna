<?php
// Combo Membresía + Evento/Curso: cuando alguien sin membresía compra un
// evento/curso marcado incluido_membresia=1, checkout.php le ofrece comprar
// la membresía junto con ese evento/curso en el mismo pago (una Subscription
// real de Stripe, recurrente — ver checkout_combo_iniciar.php). El vínculo
// evento/curso↔suscripción no se guarda en ninguna columna nueva de
// membresia_suscripciones: vive en el metadata de Stripe (de la línea de
// invoice item, o de la propia Subscription como respaldo), y solo hace
// falta leerlo una vez, en la ventana entre "se creó la Subscription" y "se
// confirma el primer pago" — de ahí en adelante ya no importa.
require_once __DIR__ . '/stripe_helper.php';

/**
 * Lee de la factura (payload de invoice.paid, o la respuesta de un GET a
 * /invoices) si trae la línea de invoice item del combo — camino barato, sin
 * llamar a Stripe otra vez. Si no aparece ahí (por ejemplo, alguna versión de
 * la API que no exponga metadata de línea en el payload del evento), cae a
 * un GET a la Subscription, que sí trae el metadata completo.
 *
 * Devuelve ['tipo' => 'evento'|'curso', 'item_id' => int] o null si esta
 * factura no es de un combo.
 */
function extraer_combo_metadata_de_invoice(array $factura, string $subscriptionId, ?string $llave = null): ?array
{
    foreach ($factura['lines']['data'] ?? [] as $linea) {
        $tipo = $linea['metadata']['tipo_combo_item'] ?? null;
        $itemId = $linea['metadata']['combo_item_id'] ?? null;
        if ($tipo && $itemId) {
            return ['tipo' => $tipo, 'item_id' => (int) $itemId];
        }
    }

    $resSub = stripe_api('GET', 'subscriptions/' . urlencode($subscriptionId), [], $llave);
    if ($resSub['ok'] && ($resSub['data']['metadata']['combo'] ?? '') === '1') {
        $tipo = $resSub['data']['metadata']['combo_tipo'] ?? null;
        $itemId = $resSub['data']['metadata']['combo_item_id'] ?? null;
        if ($tipo && $itemId) {
            return ['tipo' => $tipo, 'item_id' => (int) $itemId];
        }
    }

    return null;
}

/**
 * Inscribe al usuario en el evento/curso del combo — mismo patrón ya usado
 * en checkout.php/stripe_webhook.php para un evento comprado suelto
 * (ON DUPLICATE KEY UPDATE, revive una inscripción cancelada). curso_inscripciones
 * no tiene columna estado, solo UNIQUE KEY (usuario_id, curso_id), así que ahí
 * basta INSERT IGNORE.
 */
function activar_combo_inscripcion(mysqli $conn, int $usuarioId, string $tipo, int $itemId): void
{
    if ($tipo === 'evento') {
        $stmt = $conn->prepare(
            "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado) VALUES (?, ?, 'inscrito')
             ON DUPLICATE KEY UPDATE estado = IF(estado = 'cancelado', 'inscrito', estado)"
        );
        $stmt->bind_param('ii', $usuarioId, $itemId);
        $stmt->execute();
        $stmt->close();
    } elseif ($tipo === 'curso') {
        $stmt = $conn->prepare('INSERT IGNORE INTO curso_inscripciones (usuario_id, curso_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $usuarioId, $itemId);
        $stmt->execute();
        $stmt->close();
    }
}
