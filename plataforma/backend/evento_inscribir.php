<?php
// Inscripción a un evento: si es gratuito, inscribe directo; si es de pago,
// manda al checkout (igual que un curso de pago).
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$eventoId = (int) ($_POST['evento_id'] ?? 0);

$stmt = $conn->prepare('SELECT gratuito, activo, cupo_maximo, solo_miembros, incluido_membresia, fecha_inicio FROM eventos WHERE id = ?');
$stmt->bind_param('i', $eventoId);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$evento || !(int) $evento['activo']) {
    echo json_encode(['success' => false, 'message' => 'Evento no disponible.']);
    exit;
}

$soloMiembros = (int) $evento['solo_miembros'] === 1;
$incluidoMembresia = (int) $evento['incluido_membresia'] === 1;
$esMiembro = usuario_tiene_membresia_activa($usuarioPerfilId);

if ($soloMiembros && !$esMiembro) {
    echo json_encode(['success' => false, 'message' => 'Este evento es exclusivo para miembros de Camino Arjuna.']);
    exit;
}
// solo_miembros ya implica "incluido" para quien es miembro (y a los que no
// lo son ya se les bloqueó arriba); incluido_membresia extiende lo mismo a
// un evento que NO es exclusivo (los demás lo siguen pudiendo comprar).
$accesoGratisPorMembresia = ($soloMiembros || $incluidoMembresia) && $esMiembro;
if ((int) $evento['gratuito'] !== 1 && !$accesoGratisPorMembresia) {
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

echo json_encode(['success' => true]);
