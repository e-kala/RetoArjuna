<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$cursoId = (int) ($_POST['curso_id'] ?? 0) ?: null;
$eventoId = (int) ($_POST['evento_id'] ?? 0) ?: null;

if (($cursoId === null) === ($eventoId === null)) {
    echo json_encode(['success' => false, 'message' => 'Falta indicar el curso o el evento.']);
    exit;
}

// Nunca confiar en que el cliente diga "tengo acceso" — se revalida server-side.
$tieneAcceso = $cursoId !== null
    ? usuario_tiene_acceso_curso($usuarioPerfilId, $cursoId)
    : usuario_esta_inscrito_evento($usuarioPerfilId, $eventoId);
if (!$tieneAcceso) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes acceso a este contenido.']);
    exit;
}

$config = regalo_configuracion_obtener($cursoId, $eventoId);
if (!$config) {
    echo json_encode(['success' => false, 'message' => 'Este contenido no admite regalarse.']);
    exit;
}

$resultado = regalo_generar($config, $usuarioPerfilId);
if (!$resultado['success']) {
    echo json_encode($resultado);
    exit;
}

echo json_encode([
    'success' => true,
    'codigo' => $resultado['codigo'],
    'url' => BASE_URL . '/index.php?action=regalo&codigo=' . $resultado['codigo'],
]);
