<?php
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$categoriaId = (int) ($_POST['categoria_id'] ?? 0);
$cursoId = (int) ($_POST['curso_id'] ?? 0) ?: null;
$leccionId = (int) ($_POST['leccion_id'] ?? 0) ?: null;
$titulo = trim($_POST['titulo'] ?? '');
$contenido = trim($_POST['contenido'] ?? '');

if ($categoriaId <= 0 || $titulo === '' || $contenido === '') {
    echo json_encode(['success' => false, 'message' => 'Completa la categoría, el título y el mensaje.']);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM foro_categorias WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $categoriaId);
$stmt->execute();
$categoria = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$categoria) {
    echo json_encode(['success' => false, 'message' => 'La categoría seleccionada no existe.']);
    exit;
}

if ($cursoId) {
    $stmt = $conn->prepare('SELECT id FROM cursos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $cursoId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $cursoId = null;
        $leccionId = null;
    }
    $stmt->close();
}
if ($cursoId && $leccionId) {
    $stmt = $conn->prepare('SELECT id FROM lecciones WHERE id = ? AND curso_id = ? LIMIT 1');
    $stmt->bind_param('ii', $leccionId, $cursoId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $leccionId = null;
    }
    $stmt->close();
}

$slug = foro_slug_unico(foro_slugify($titulo));
$contenidoHtml = convertir_contenido_foro($contenido);

$stmt = $conn->prepare(
    'INSERT INTO foro_temas (categoria_id, curso_id, leccion_id, usuario_id, titulo, slug, contenido, ultima_respuesta_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->bind_param('iiiisss', $categoriaId, $cursoId, $leccionId, $usuario['id'], $titulo, $slug, $contenidoHtml);
$stmt->execute();
$temaId = $stmt->insert_id;
$stmt->close();

foreach (foro_detectar_menciones($contenido, $usuario['id']) as $mencionadoId) {
    foro_crear_notificacion($mencionadoId, 'mencion', $temaId, null, $usuario['id']);
}

echo json_encode(['success' => true, 'redirect' => 'tema.php?id=' . $temaId]);
