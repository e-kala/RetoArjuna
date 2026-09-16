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

// tipo_descuento_id=0/vacío => "Regalar acceso" (null); con valor => ese tipo
// de descuento específico — nunca se confía en el % que mande el cliente,
// solo en a cuál fila ya configurada por el admin se está refiriendo.
$tipoDescuentoId = (int) ($_POST['tipo_descuento_id'] ?? 0) ?: null;

$resultado = regalo_generar($config, $tipoDescuentoId, $usuarioPerfilId);
if (!$resultado['success']) {
    echo json_encode($resultado);
    exit;
}

echo json_encode([
    'success' => true,
    'codigo' => $resultado['codigo'],
    'url' => SITE_URL . '/index.php?action=regalo&codigo=' . $resultado['codigo'],
]);
