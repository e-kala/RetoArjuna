<?php
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in() || current_user()['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para hacer esto.']);
    exit;
}

$temaId = (int) ($_POST['tema_id'] ?? 0);
$accion = $_POST['accion'] ?? '';

$stmt = $conn->prepare('SELECT id, categoria_id FROM foro_temas WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $temaId);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tema) {
    echo json_encode(['success' => false, 'message' => 'El tema no existe.']);
    exit;
}

switch ($accion) {
    case 'fijar':
    case 'desfijar':
        $stmt = $conn->prepare('UPDATE foro_temas SET fijado = ? WHERE id = ?');
        $valor = $accion === 'fijar' ? 1 : 0;
        $stmt->bind_param('ii', $valor, $temaId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'cerrar':
    case 'reabrir':
        $stmt = $conn->prepare('UPDATE foro_temas SET cerrado = ? WHERE id = ?');
        $valor = $accion === 'cerrar' ? 1 : 0;
        $stmt->bind_param('ii', $valor, $temaId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'eliminar':
        $stmt = $conn->prepare('DELETE FROM foro_temas WHERE id = ?');
        $stmt->bind_param('i', $temaId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}
