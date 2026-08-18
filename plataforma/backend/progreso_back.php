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

$stmt = $conn->prepare('SELECT curso_id FROM lecciones WHERE id = ?');
$stmt->bind_param('i', $leccionId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leccion) {
    echo json_encode(['success' => false, 'message' => 'Lección no encontrada.']);
    exit;
}

// El progreso por lección solo existe para cursos — las lecciones de un
// evento (ver schema_lecciones_compartidas.sql) no tienen "completado".
if ($leccion['curso_id'] === null) {
    echo json_encode(['success' => false, 'message' => 'Esta lección no forma parte de un curso.']);
    exit;
}

$cursoId = (int) $leccion['curso_id'];
if (!usuario_tiene_acceso_curso($usuarioPerfilId, $cursoId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes acceso a este curso.']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO progreso (usuario_id, curso_id, leccion_id, completado, tiempo_visto_seg)
     VALUES (?, ?, ?, 1, ?)
     ON DUPLICATE KEY UPDATE completado = 1, tiempo_visto_seg = VALUES(tiempo_visto_seg)"
);
$stmt->bind_param('iiii', $usuarioPerfilId, $cursoId, $leccionId, $tiempoVisto);
$stmt->execute();
$stmt->close();

verificar_y_emitir_certificado($usuarioPerfilId, $cursoId);

echo json_encode(['success' => true]);
