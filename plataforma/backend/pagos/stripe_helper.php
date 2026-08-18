<?php
// Cliente mínimo hacia la API de Stripe (cURL crudo, mismo estilo que el resto del
// proyecto — sin SDK). Compartido por los endpoints de membresía; los pagos únicos
// (stripe_create_intent.php) tienen su propia llamada inline, no se tocó para no
// arriesgar ese flujo ya probado.

function stripe_api(string $metodo, string $ruta, array $campos = []): array
{
    $ch = curl_init('https://api.stripe.com/v1/' . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($campos) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($campos));
    }
    $respuesta = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $respuesta, true) ?? [];
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $data];
}
