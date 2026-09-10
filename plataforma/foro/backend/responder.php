<?php
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$temaId = (int) ($_POST['tema_id'] ?? 0);
$contenido = trim($_POST['contenido'] ?? '');

if ($temaId <= 0 || $contenido === '') {
    echo json_encode(['success' => false, 'message' => 'Escribe una respuesta.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, usuario_id, cerrado, oculto, visibilidad, compartido_con_usuario_id FROM foro_temas WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $temaId);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tema || !foro_tema_es_visible($tema, $usuario)) {
    echo json_encode(['success' => false, 'message' => 'El tema no existe.']);
    exit;
}
if ((int) $tema['cerrado'] === 1 && $usuario['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Este tema está cerrado.']);
    exit;
}

$contenidoHtml = foro_sanitizar_html_editor($contenido);
if (foro_contenido_html_vacio($contenidoHtml)) {
    echo json_encode(['success' => false, 'message' => 'Escribe una respuesta.']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO foro_respuestas (tema_id, usuario_id, contenido) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $temaId, $usuario['id'], $contenidoHtml);
$stmt->execute();
$respuestaId = $stmt->insert_id;
$stmt->close();

$stmt = $conn->prepare(
    'UPDATE foro_temas SET respuestas_count = respuestas_count + 1, ultima_respuesta_at = NOW() WHERE id = ?'
);
$stmt->bind_param('i', $temaId);
$stmt->execute();
$stmt->close();

foro_crear_notificacion((int) $tema['usuario_id'], 'respuesta', $temaId, $respuestaId, $usuario['id']);
foreach (foro_detectar_menciones($contenido, $usuario['id']) as $mencionadoId) {
    if ($mencionadoId !== (int) $tema['usuario_id']) {
        foro_crear_notificacion($mencionadoId, 'mencion', $temaId, $respuestaId, $usuario['id']);
    }
}

echo json_encode(['success' => true, 'redirect' => 'tema.php?id=' . $temaId . '#respuesta-' . $respuestaId]);
