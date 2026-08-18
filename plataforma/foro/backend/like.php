<?php
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$temaId = isset($_POST['tema_id']) ? (int) $_POST['tema_id'] : null;
$respuestaId = isset($_POST['respuesta_id']) ? (int) $_POST['respuesta_id'] : null;

if (!$temaId && !$respuestaId) {
    echo json_encode(['success' => false, 'message' => 'Falta indicar qué te gustó.']);
    exit;
}

$yaLeGusta = foro_usuario_dio_like($usuario['id'], $temaId, $respuestaId);

if ($yaLeGusta) {
    if ($temaId) {
        $stmt = $conn->prepare('DELETE FROM foro_likes WHERE usuario_id = ? AND tema_id = ?');
        $stmt->bind_param('ii', $usuario['id'], $temaId);
    } else {
        $stmt = $conn->prepare('DELETE FROM foro_likes WHERE usuario_id = ? AND respuesta_id = ?');
        $stmt->bind_param('ii', $usuario['id'], $respuestaId);
    }
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare('INSERT IGNORE INTO foro_likes (usuario_id, tema_id, respuesta_id) VALUES (?, ?, ?)');
    $stmt->bind_param('iii', $usuario['id'], $temaId, $respuestaId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'activo' => !$yaLeGusta,
    'likes' => foro_contar_likes($temaId, $respuestaId),
]);
