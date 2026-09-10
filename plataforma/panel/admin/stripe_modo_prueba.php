<?php
// Prende/apaga el modo prueba de Stripe (ver stripe_helper.php) para quien hace
// la petición — persistente en `usuarios_perfil.stripe_modo_prueba` hasta que
// se apague a mano, a propósito. Se llama desde el menú de usuario (navbar.php,
// para activarlo) y desde la franja de aviso (stripe_modo_prueba_banner_html(),
// para desactivarlo) — nunca afecta a otro usuario, cada quien tiene su propia
// fila. Vive bajo panel/admin/ por historia (nació como control solo-admin),
// pero el permiso real es admin O cuenta marcada es_prueba (ver
// stripe_modo_prueba_permitido() — un tester con una cuenta de prueba, sin ser
// admin, también puede necesitar probar el flujo de pagos como estudiante real).
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/pagos/stripe_helper.php';
require_login();
if (!stripe_modo_prueba_permitido()) {
    http_response_code(403);
    echo 'No tienes permiso para esto.';
    exit;
}
requerir_csrf_form();

$usuario = current_user();
$activar = ($_POST['activar'] ?? '') === '1' ? 1 : 0;

$stmt = $conn->prepare('UPDATE usuarios_perfil SET stripe_modo_prueba = ? WHERE id = ?');
$stmt->bind_param('ii', $activar, $usuario['id']);
$stmt->execute();
$stmt->close();

// Solo se acepta una ruta relativa al propio sitio (evita que "volver" se use
// como open redirect) — "//host/..." es protocol-relative y sí saldría del
// sitio, por eso también se rechaza junto con cualquier valor con esquema.
$volver = (string) ($_POST['volver'] ?? '');
if ($volver === '' || $volver[0] !== '/' || str_starts_with($volver, '//') || str_contains($volver, '://')) {
    $volver = BASE_URL . '/panel/index.php';
}

header('Location: ' . $volver);
exit;
