<?php
// Membresía pagada con OXXO — recurrencia simulada a mano (ver comentario
// en db/exportar_produccion.sql: Stripe no soporta OXXO para cobros
// automáticos recurrentes). Un PaymentIntent OXXO nuevo se genera cada vez
// que hace falta un mes más: la primera vez desde membresia_oxxo_iniciar.php
// (alta), y cada mes desde el cron membresia_oxxo_generar_vouchers.php
// (renovación) — ambos comparten esta misma función para no duplicar la
// llamada a Stripe ni el registro en membresia_vouchers_oxxo.
require_once __DIR__ . '/stripe_helper.php';

/**
 * Crea un PaymentIntent OXXO por el precio de UN mes de la membresía y
 * registra el voucher en membresia_vouchers_oxxo. $periodoInicio/$periodoFin
 * son el rango de acceso que este voucher, si se paga, otorga (no las
 * fechas del voucher en sí — esas son $venceEn, que Stripe decide).
 *
 * $modo ('live'|'prueba'), si se pasa, fuerza qué llave usar sin depender
 * de sesión — necesario para el cron (backend/membresia_oxxo_generar_vouchers.php),
 * que corre por CLI sin ningún usuario logueado: ahí se usa el modo YA
 * guardado en membresia_suscripciones.modo desde el alta, nunca
 * stripe_modo_prueba_activo() (que sin sesión siempre da 'live'). El
 * endpoint de alta (con sesión real) puede omitir $modo y dejar que se
 * resuelva solo por el toggle de quien está comprando.
 *
 * Devuelve ['success'=>bool, 'voucher_id', 'payment_intent_id',
 * 'client_secret', 'message'] — mismo shape que ya usan los demás
 * endpoints de pagos/ para que el JS del cliente no tenga que aprender un
 * contrato nuevo.
 */
function membresia_oxxo_generar_voucher(mysqli $conn, int $suscripcionId, float $monto, string $periodoInicio, string $periodoFin, ?string $modo = null): array
{
    $llave = $modo === 'prueba' && defined('STRIPE_SECRET_KEY_PRUEBA') && config_esta_lista(STRIPE_SECRET_KEY_PRUEBA)
        ? STRIPE_SECRET_KEY_PRUEBA
        : ($modo === null ? stripe_secret_key_activa() : STRIPE_SECRET_KEY);

    $ch = curl_init('https://api.stripe.com/v1/payment_intents');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $llave . ':',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query([
            'amount' => (int) round($monto * 100),
            'currency' => 'mxn',
            'description' => 'Membresía Camino Arjuna — periodo ' . $periodoInicio . ' a ' . $periodoFin,
            'payment_method_types' => ['oxxo'],
            'metadata' => [
                'tipo' => 'membresia_oxxo',
                'suscripcion_id' => $suscripcionId,
                'periodo_inicio' => $periodoInicio,
                'periodo_fin' => $periodoFin,
            ],
        ]),
    ]);
    $respuesta = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $respuesta, true);

    if ($status !== 200 || empty($data['id'])) {
        return ['success' => false, 'message' => 'No se pudo generar el voucher OXXO.'];
    }

    // El PaymentIntent recién creado no confirma el método de pago todavía
    // (eso lo hace el cliente con confirmPayment()/confirmOxxoPayment()),
    // así que next_action.oxxo_display_details no existe aún — numero/
    // url_voucher/vence_en se completan después, cuando el webhook o la
    // consulta síncrona post-confirmación traen esos datos (mismo patrón
    // que checkout.php con curso/evento/producto).
    $stmt = $conn->prepare(
        'INSERT INTO membresia_vouchers_oxxo (suscripcion_id, payment_intent_id, periodo_inicio, periodo_fin, estado)
         VALUES (?, ?, ?, ?, \'pendiente\')'
    );
    $stmt->bind_param('isss', $suscripcionId, $data['id'], $periodoInicio, $periodoFin);
    $stmt->execute();
    $voucherId = $stmt->insert_id;
    $stmt->close();

    return [
        'success' => true,
        'voucher_id' => $voucherId,
        'payment_intent_id' => $data['id'],
        'client_secret' => $data['client_secret'],
    ];
}

/**
 * Completa numero/url_voucher/vence_en de un voucher ya creado, consultando
 * el PaymentIntent a Stripe — se llama justo después de que el cliente
 * confirma el pago (el next_action.oxxo_display_details solo existe desde
 * ese momento). Mismo dato que ya expone checkout.php tras
 * redirect_status=processing. $modo: ver membresia_oxxo_generar_voucher().
 */
function membresia_oxxo_completar_datos_voucher(mysqli $conn, string $paymentIntentId, ?string $modo = null): void
{
    $llave = $modo === 'prueba' && defined('STRIPE_SECRET_KEY_PRUEBA') && config_esta_lista(STRIPE_SECRET_KEY_PRUEBA)
        ? STRIPE_SECRET_KEY_PRUEBA
        : ($modo === null ? stripe_secret_key_activa() : STRIPE_SECRET_KEY);
    $res = stripe_api('GET', 'payment_intents/' . urlencode($paymentIntentId), [], $llave);
    $detalle = $res['data']['next_action']['oxxo_display_details'] ?? null;
    if (!$res['ok'] || !$detalle) {
        return;
    }
    $numero = $detalle['number'] ?? null;
    $url = $detalle['hosted_voucher_url'] ?? null;
    $venceEn = !empty($detalle['expires_after']) ? date('Y-m-d H:i:s', (int) $detalle['expires_after']) : null;

    $stmt = $conn->prepare(
        'UPDATE membresia_vouchers_oxxo SET numero = ?, url_voucher = ?, vence_en = ? WHERE payment_intent_id = ?'
    );
    $stmt->bind_param('ssss', $numero, $url, $venceEn, $paymentIntentId);
    $stmt->execute();
    $stmt->close();
}

/**
 * El voucher OXXO pendiente más reciente de una suscripción, con los datos
 * ya listos para mostrar en el panel (mi_membresia.php) — null si no hay
 * ninguno pendiente (ej. ya se pagó, o todavía no se generó el del próximo mes).
 */
function membresia_oxxo_voucher_pendiente(mysqli $conn, int $suscripcionId): ?array
{
    $stmt = $conn->prepare(
        "SELECT * FROM membresia_vouchers_oxxo
         WHERE suscripcion_id = ? AND estado = 'pendiente'
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->bind_param('i', $suscripcionId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}
