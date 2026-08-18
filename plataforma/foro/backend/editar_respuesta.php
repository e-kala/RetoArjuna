<?php
// Igual que editar_tema.php pero para una respuesta — autor o admin, sin
// límite de tiempo, guarda la versión anterior en foro_respuestas_historial.
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$respuestaId = (int) ($_POST['respuesta_id'] ?? 0);
$contenidoNuevo = trim($_POST['contenido'] ?? '');

if ($contenidoNuevo === '') {
    echo json_encode(['success' => false, 'message' => 'Escribe un contenido.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, usuario_id, contenido FROM foro_respuestas WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $respuestaId);
$stmt->execute();
$respuesta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$respuesta) {
    echo json_encode(['success' => false, 'message' => 'La respuesta no existe.']);
    exit;
}

$esAdmin = $usuario['rol'] === 'admin';
if ((int) $respuesta['usuario_id'] !== (int) $usuario['id'] && !$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar esta respuesta.']);
    exit;
}

$contenidoConvertido = foro_sanitizar_html_editor($contenidoNuevo);
if (foro_contenido_html_vacio($contenidoConvertido)) {
    echo json_encode(['success' => false, 'message' => 'Escribe un contenido.']);
    exit;
}

if ($contenidoConvertido === $respuesta['contenido']) {
    echo json_encode(['success' => true, 'contenido_html' => $respuesta['contenido']]);
    exit;
}

$stmt = $conn->prepare(
    'INSERT INTO foro_respuestas_historial (respuesta_id, contenido_anterior, editado_por) VALUES (?, ?, ?)'
);
$stmt->bind_param('isi', $respuestaId, $respuesta['contenido'], $usuario['id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare(
    'UPDATE foro_respuestas SET contenido = ?, editado_en = NOW(), editado_por = ? WHERE id = ?'
);
$stmt->bind_param('sii', $contenidoConvertido, $usuario['id'], $respuestaId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'contenido_html' => $contenidoConvertido]);
