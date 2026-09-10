<?php
// Inscripción a un evento: si es gratuito/incluido/quedó en $0 por
// promoción-cupón, inscribe directo; si es de pago, manda al checkout.
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
$eventoId = (int) ($_POST['evento_id'] ?? 0);
$codigoCupon = trim((string) ($_POST['codigo_cupon'] ?? '')) ?: null;

$stmt = $conn->prepare('SELECT precio, gratuito, activo, cupo_maximo, solo_miembros, incluido_membresia, descuento_miembro_pct, fecha_inicio FROM eventos WHERE id = ?');
$stmt->bind_param('i', $eventoId);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$evento || !(int) $evento['activo']) {
    echo json_encode(['success' => false, 'message' => 'Evento no disponible.']);
    exit;
}

$soloMiembros = (int) $evento['solo_miembros'] === 1;
$esMiembro = usuario_tiene_membresia_activa($usuarioPerfilId);

if ($soloMiembros && !$esMiembro) {
    echo json_encode(['success' => false, 'message' => 'Este evento es exclusivo para miembros de Camino Arjuna.']);
    exit;
}

$usuario = current_user();
$ofertaItem = [
    'id' => $eventoId,
    'precio' => (float) $evento['precio'],
    'gratuito' => (bool) $evento['gratuito'],
    'incluido_membresia' => (bool) $evento['incluido_membresia'],
    'solo_miembros' => $soloMiembros,
    'descuento_miembro_pct' => $evento['descuento_miembro_pct'] !== null ? (float) $evento['descuento_miembro_pct'] : null,
    'ya_tiene_acceso' => usuario_esta_inscrito_evento($usuarioPerfilId, $eventoId),
];
$oferta = resolver_oferta($conn, 'evento', $ofertaItem, $usuario, $codigoCupon);

if ($oferta['estado'] === 'acceso') {
    echo json_encode(['success' => true]);
    exit;
}
if ($oferta['estado'] !== 'gratuito' && $oferta['estado'] !== 'incluido_membresia' && !$oferta['acceso_gratis_automatico']) {
    echo json_encode(['success' => false, 'message' => 'Este evento requiere pago.', 'checkout' => true]);
    exit;
}

// El cupo limita lugares para el evento en vivo — una vez pasado, ya no
// aplica (registrarse ahí es solo para acceder a la grabación).
$esPasado = strtotime($evento['fecha_inicio']) < time();
if (!$esPasado && $evento['cupo_maximo'] !== null) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM evento_inscripciones WHERE evento_id = ? AND estado <> 'cancelado'");
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $inscritos = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    if ($inscritos >= (int) $evento['cupo_maximo']) {
        echo json_encode(['success' => false, 'message' => 'Ya no hay cupo disponible para este evento.']);
        exit;
    }
}

$stmt = $conn->prepare(
    "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado) VALUES (?, ?, 'inscrito')
     ON DUPLICATE KEY UPDATE estado = IF(estado = 'cancelado', 'inscrito', estado)"
);
$stmt->bind_param('ii', $usuarioPerfilId, $eventoId);
$stmt->execute();
$stmt->close();

// Registro para que la promoción/cupón que dejó esto en $0 cuente contra su
// límite de usos (ver curso_inscribir.php, mismo criterio).
if ($oferta['acceso_gratis_automatico'] && $oferta['oferta_tipo'] !== null) {
    $cuponId = $oferta['oferta_tipo'] === 'cupon' ? $oferta['oferta_id'] : null;
    $promocionId = $oferta['oferta_tipo'] === 'promocion' ? $oferta['oferta_id'] : null;
    $modo = stripe_modo_prueba_activo() ? 'prueba' : 'live';
    $stmt = $conn->prepare(
        "INSERT INTO pagos (usuario_id, evento_id, cupon_id, promocion_id, monto, metodo_pago, modo, estado)
         VALUES (?, ?, ?, ?, 0, 'transferencia', ?, 'confirmado')"
    );
    $stmt->bind_param('iiiis', $usuarioPerfilId, $eventoId, $cuponId, $promocionId, $modo);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]);
