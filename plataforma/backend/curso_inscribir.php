<?php
// Inscripción a un curso gratuito: acceso explícito, no automático solo por
// tener gratuito=1 (ver curso_inscripciones). Los de pago siguen yendo a
// checkout.php, igual que siempre.
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$cursoId = (int) ($_POST['curso_id'] ?? 0);

$stmt = $conn->prepare('SELECT gratuito, activo FROM cursos WHERE id = ?');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso || !(int) $curso['activo']) {
    echo json_encode(['success' => false, 'message' => 'Curso no disponible.']);
    exit;
}

if ((int) $curso['gratuito'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Este curso requiere pago.', 'checkout' => true]);
    exit;
}

$stmt = $conn->prepare(
    'INSERT IGNORE INTO curso_inscripciones (usuario_id, curso_id) VALUES (?, ?)'
);
$stmt->bind_param('ii', $usuarioPerfilId, $cursoId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
