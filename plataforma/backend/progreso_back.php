<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/certificados.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$leccionId = (int) ($_POST['leccion_id'] ?? 0);
$tiempoVisto = (int) ($_POST['tiempo_visto_seg'] ?? 0);
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare('SELECT curso_id, evento_id FROM lecciones WHERE id = ?');
$stmt->bind_param('i', $leccionId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leccion) {
    echo json_encode(['success' => false, 'message' => 'Lección no encontrada.']);
    exit;
}

// El progreso por lección existe tanto para cursos como para eventos (ver
// ALTER TABLE progreso en exportar_produccion.sql) — exactamente uno de los
// dos IDs viene no nulo, igual que en `lecciones` misma.
$cursoId = $leccion['curso_id'] !== null ? (int) $leccion['curso_id'] : null;
$eventoId = $leccion['evento_id'] !== null ? (int) $leccion['evento_id'] : null;

$tieneAcceso = $cursoId !== null
    ? usuario_tiene_acceso_curso($usuarioPerfilId, $cursoId)
    : usuario_esta_inscrito_evento($usuarioPerfilId, $eventoId);
if (!$tieneAcceso) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes acceso a este contenido.']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO progreso (usuario_id, curso_id, evento_id, leccion_id, completado, tiempo_visto_seg)
     VALUES (?, ?, ?, ?, 1, ?)
     ON DUPLICATE KEY UPDATE completado = 1, tiempo_visto_seg = VALUES(tiempo_visto_seg)"
);
$stmt->bind_param('iiiii', $usuarioPerfilId, $cursoId, $eventoId, $leccionId, $tiempoVisto);
$stmt->execute();
$stmt->close();

// El certificado de curso depende de terminar todas sus lecciones — los
// eventos ya tienen su propio reconocimiento por asistencia
// (evento_inscripciones + certificados), independiente de esto.
if ($cursoId !== null) {
    verificar_y_emitir_certificado($usuarioPerfilId, $cursoId);
}

echo json_encode(['success' => true]);
