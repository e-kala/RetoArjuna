<?php
// Edita un tema ya publicado (autor o admin, sin límite de tiempo) — guarda la
// versión anterior en foro_temas_historial antes de sobrescribir. Fase C:
// todavía sobre el pipeline de texto plano existente (convertir_contenido_foro),
// el editor de texto enriquecido llega en una fase aparte.
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$temaId = (int) ($_POST['tema_id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$contenidoNuevo = trim($_POST['contenido'] ?? '');

if ($titulo === '' || $contenidoNuevo === '') {
    echo json_encode(['success' => false, 'message' => 'Completa el título y el contenido.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, usuario_id, titulo, contenido, cerrado FROM foro_temas WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $temaId);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tema) {
    echo json_encode(['success' => false, 'message' => 'El tema no existe.']);
    exit;
}

$esAdmin = $usuario['rol'] === 'admin';
if ((int) $tema['usuario_id'] !== (int) $usuario['id'] && !$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar este tema.']);
    exit;
}

$contenidoConvertido = foro_sanitizar_html_editor($contenidoNuevo);
if (foro_contenido_html_vacio($contenidoConvertido)) {
    echo json_encode(['success' => false, 'message' => 'Escribe un mensaje.']);
    exit;
}

// Sin cambios reales: no ensucia el historial con reenvíos idénticos.
if ($titulo === $tema['titulo'] && $contenidoConvertido === $tema['contenido']) {
    echo json_encode(['success' => true, 'titulo' => $tema['titulo'], 'contenido_html' => $tema['contenido']]);
    exit;
}

$stmt = $conn->prepare(
    'INSERT INTO foro_temas_historial (tema_id, titulo_anterior, contenido_anterior, editado_por) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('issi', $temaId, $tema['titulo'], $tema['contenido'], $usuario['id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare(
    'UPDATE foro_temas SET titulo = ?, contenido = ?, editado_en = NOW(), editado_por = ? WHERE id = ?'
);
$stmt->bind_param('ssii', $titulo, $contenidoConvertido, $usuario['id'], $temaId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'titulo' => $titulo, 'contenido_html' => $contenidoConvertido]);
