<?php
// Lista de quién dio like a un tema o respuesta (público, igual que ver el
// tema mismo — foro/tema.php no exige sesión para leer).
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');

$tipo = $_GET['tipo'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($tipo === 'tema') {
    $likes = foro_listar_likes($id, null);
} elseif ($tipo === 'respuesta') {
    $likes = foro_listar_likes(null, $id);
} else {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido.']);
    exit;
}

echo json_encode(['success' => true, 'likes' => $likes]);
