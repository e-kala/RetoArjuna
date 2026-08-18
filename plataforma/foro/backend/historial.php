<?php
// Historial de ediciones de un tema o respuesta (público, igual que ver el
// tema mismo — foro/tema.php no exige sesión para leer).
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');

$tipo = $_GET['tipo'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($tipo === 'tema') {
    $stmt = $conn->prepare(
        'SELECT h.titulo_anterior, h.contenido_anterior, h.editado_en, u.username_cache
         FROM foro_temas_historial h
         JOIN usuarios_perfil u ON u.id = h.editado_por
         WHERE h.tema_id = ? ORDER BY h.editado_en DESC'
    );
} elseif ($tipo === 'respuesta') {
    $stmt = $conn->prepare(
        "SELECT NULL AS titulo_anterior, h.contenido_anterior, h.editado_en, u.username_cache
         FROM foro_respuestas_historial h
         JOIN usuarios_perfil u ON u.id = h.editado_por
         WHERE h.respuesta_id = ? ORDER BY h.editado_en DESC"
    );
} else {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido.']);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'historial' => $historial]);
