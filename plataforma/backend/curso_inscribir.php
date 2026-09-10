<?php
// Inscripción a un curso gratuito, incluido en membresía, o que quedó en $0
// por una promoción/cupón (resolver_oferta(): acceso_gratis_automatico) —
// acceso explícito, no automático (ver curso_inscripciones), así queda
// persistido y se conserva aunque la membresía venza o el cupón se agote
// después. Los de pago siguen yendo a checkout.php, igual que siempre.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ofertas.php';
require_once __DIR__ . '/pagos/stripe_helper.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$cursoId = (int) ($_POST['curso_id'] ?? 0);
$codigoCupon = trim((string) ($_POST['codigo_cupon'] ?? '')) ?: null;

$stmt = $conn->prepare('SELECT precio, activo, gratuito, incluido_membresia, descuento_miembro_pct FROM cursos WHERE id = ?');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso || !(int) $curso['activo']) {
    echo json_encode(['success' => false, 'message' => 'Curso no disponible.']);
    exit;
}

$usuario = current_user();
$ofertaItem = [
    'id' => $cursoId,
    'precio' => (float) $curso['precio'],
    'gratuito' => (bool) $curso['gratuito'],
    'incluido_membresia' => (bool) $curso['incluido_membresia'],
    'solo_miembros' => false,
    'descuento_miembro_pct' => $curso['descuento_miembro_pct'] !== null ? (float) $curso['descuento_miembro_pct'] : null,
    'ya_tiene_acceso' => usuario_tiene_acceso_curso($usuarioPerfilId, $cursoId),
];
// Nunca confiar en que el cliente diga "esto es gratis" — se recalcula
// server-side con el mismo código de cupón recibido.
$oferta = resolver_oferta($conn, 'curso', $ofertaItem, $usuario, $codigoCupon);

if ($oferta['estado'] === 'acceso') {
    echo json_encode(['success' => true]);
    exit;
}
if ($oferta['estado'] !== 'gratuito' && $oferta['estado'] !== 'incluido_membresia' && !$oferta['acceso_gratis_automatico']) {
    echo json_encode(['success' => false, 'message' => 'Este curso requiere pago.', 'checkout' => true]);
    exit;
}

$stmt = $conn->prepare('INSERT IGNORE INTO curso_inscripciones (usuario_id, curso_id) VALUES (?, ?)');
$stmt->bind_param('ii', $usuarioPerfilId, $cursoId);
$stmt->execute();
$stmt->close();

// Una promoción/cupón que dejó el curso en $0 sí se registra en `pagos`
// (monto=0, confirmado) para que cuente contra "límite total de usos" y "un
// solo uso por cuenta" — gratuito=1 e incluido_membresia no necesitan esto,
// no vienen de un cupón/promoción limitado.
if ($oferta['acceso_gratis_automatico'] && $oferta['oferta_tipo'] !== null) {
    $cuponId = $oferta['oferta_tipo'] === 'cupon' ? $oferta['oferta_id'] : null;
    $promocionId = $oferta['oferta_tipo'] === 'promocion' ? $oferta['oferta_id'] : null;
    $modo = stripe_modo_prueba_activo() ? 'prueba' : 'live';
    $stmt = $conn->prepare(
        "INSERT INTO pagos (usuario_id, curso_id, cupon_id, promocion_id, monto, metodo_pago, modo, estado)
         VALUES (?, ?, ?, ?, 0, 'transferencia', ?, 'confirmado')"
    );
    $stmt->bind_param('iiiis', $usuarioPerfilId, $cursoId, $cuponId, $promocionId, $modo);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]);
