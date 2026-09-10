<?php
// Cliente mínimo hacia la API de Stripe (cURL crudo, mismo estilo que el resto del
// proyecto — sin SDK). Compartido por los endpoints de membresía; los pagos únicos
// (stripe_create_intent.php) tienen su propia llamada inline, no se tocó para no
// arriesgar ese flujo ya probado.

// Modo prueba de Stripe: un admin O una cuenta marcada `es_prueba` (ver
// usuarios_perfil.es_prueba — "Cuenta de prueba (no es un usuario real)" en
// panel/admin/usuario_form.php) puede activarlo desde el menú de usuario (ver
// navbar.php) para poder probar el flujo completo de pagos (tarjeta y
// membresía) sin mover dinero real, mientras cualquier otro usuario (incluido
// un admin o cuenta de prueba que no lo haya activado) sigue viendo siempre
// las llaves LIVE. Se guarda por usuario en `usuarios_perfil.stripe_modo_prueba`
// (persistente hasta que se apague a mano, a propósito — ver
// stripe_modo_prueba.php), nunca en sesión, para que no dependa de qué
// pestaña/dispositivo esté usando quien lo activó.
function stripe_modo_prueba_permitido(): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (empty($_SESSION['usuario_perfil_id'])) {
        return $cache = false;
    }
    if (($_SESSION['rol'] ?? '') === 'admin') {
        return $cache = true;
    }
    global $conn;
    $usuarioId = (int) $_SESSION['usuario_perfil_id'];
    $stmt = $conn->prepare('SELECT es_prueba FROM usuarios_perfil WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $cache = !empty($fila['es_prueba']);
}

function stripe_modo_prueba_activo(): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (!stripe_modo_prueba_permitido()) {
        return $cache = false;
    }
    global $conn;
    $usuarioId = (int) $_SESSION['usuario_perfil_id'];
    $stmt = $conn->prepare('SELECT stripe_modo_prueba FROM usuarios_perfil WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $cache = !empty($fila['stripe_modo_prueba']);
}

// Llave activa según el modo — si quien navega ahora mismo tiene modo prueba
// encendido Y el entorno tiene configurada su llave TEST (STRIPE_*_KEY_PRUEBA),
// se usa esa; en cualquier otro caso (usuario normal, admin/cuenta de prueba
// sin activarlo, o entorno sin llaves TEST configuradas) se usa siempre la
// llave LIVE de toda la vida.
function stripe_secret_key_activa(): string
{
    if (stripe_modo_prueba_activo() && defined('STRIPE_SECRET_KEY_PRUEBA') && config_esta_lista(STRIPE_SECRET_KEY_PRUEBA)) {
        return STRIPE_SECRET_KEY_PRUEBA;
    }
    return STRIPE_SECRET_KEY;
}

function stripe_publishable_key_activa(): string
{
    if (stripe_modo_prueba_activo() && defined('STRIPE_PUBLISHABLE_KEY_PRUEBA') && config_esta_lista(STRIPE_PUBLISHABLE_KEY_PRUEBA)) {
        return STRIPE_PUBLISHABLE_KEY_PRUEBA;
    }
    return STRIPE_PUBLISHABLE_KEY;
}

// Franja fija y muy visible mientras el modo prueba está activo, para que un
// admin nunca la pierda de vista mientras navega (a diferencia de la sesión, que
// se olvida sola, este modo queda encendido hasta apagarlo a mano — ver
// stripe_modo_prueba.php). Se llama desde navbar.php (cubre casi todo el sitio)
// y explícitamente en checkout.php, que no incluye el navbar por tener su propio
// <html> minimalista.
function stripe_modo_prueba_banner_html(): string
{
    if (!stripe_modo_prueba_activo()) {
        return '';
    }
    $volver = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/panel/index.php')));
    return '<div class="alert alert-warning text-center mb-0 py-2 px-2" style="position:sticky;top:0;z-index:1055;border-radius:0;">'
        . '<strong> Modo prueba de Stripe activo</strong> — los pagos que hagas ahora no usan dinero real. '
        . '<form method="post" action="' . htmlspecialchars(BASE_URL) . '/panel/admin/stripe_modo_prueba.php" class="d-inline ms-2">'
        . csrf_field()
        . '<input type="hidden" name="activar" value="0">'
        . '<input type="hidden" name="volver" value="' . $volver . '">'
        . '<button type="submit" class="btn btn-sm btn-dark">Desactivar</button>'
        . '</form></div>';
}

// $llave: por defecto usa la llave del modo activo de quien navega ahora
// (stripe_secret_key_activa()). Pasar una llave explícita sirve para los pocos
// casos que necesitan llamar a Stripe en un modo específico sin importar el
// toggle de quien lo pide (ver membresia_form.php: crear el Price LIVE y el
// Price TEST de una membresía en la misma petición, uno con cada llave).
function stripe_api(string $metodo, string $ruta, array $campos = [], ?string $llave = null): array
{
    $ch = curl_init('https://api.stripe.com/v1/' . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_USERPWD => ($llave ?? stripe_secret_key_activa()) . ':',
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
