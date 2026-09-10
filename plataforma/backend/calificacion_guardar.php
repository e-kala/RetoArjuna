<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioId = (int) $_SESSION['usuario_perfil_id'];
$cursoId = (int) ($_POST['curso_id'] ?? 0) ?: null;
$eventoId = (int) ($_POST['evento_id'] ?? 0) ?: null;
$puntuacion = (int) ($_POST['puntuacion'] ?? 0);
$comentario = trim((string) ($_POST['comentario'] ?? ''));
$comentario = $comentario !== '' ? mb_substr($comentario, 0, 500) : null;

if (($cursoId === null) === ($eventoId === null)) {
    echo json_encode(['success' => false, 'message' => 'Falta indicar el curso o el evento.']);
    exit;
}
if ($puntuacion < 1 || $puntuacion > 5) {
    echo json_encode(['success' => false, 'message' => 'La calificación debe ser de 1 a 5.']);
    exit;
}

$puedeCalificar = $cursoId !== null
    ? usuario_puede_calificar_curso($usuarioId, $cursoId)
    : usuario_puede_calificar_evento($usuarioId, $eventoId);

if (!$puedeCalificar) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo puedes calificar después de terminarlo.']);
    exit;
}

$columna = $cursoId !== null ? 'curso_id' : 'evento_id';
$itemId = $cursoId !== null ? $cursoId : $eventoId;
$stmt = $conn->prepare(
    "INSERT INTO calificaciones (usuario_id, $columna, puntuacion, comentario)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE puntuacion = VALUES(puntuacion), comentario = VALUES(comentario)"
);
$stmt->bind_param('iiis', $usuarioId, $itemId, $puntuacion, $comentario);
$stmt->execute();
$stmt->close();

$resumen = calificacion_resumen($cursoId, $eventoId);

echo json_encode(['success' => true, 'promedio' => $resumen['promedio'], 'total' => $resumen['total']]);
