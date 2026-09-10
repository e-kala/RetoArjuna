<?php
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$esAdmin = $usuario['rol'] === 'admin';
$accion = $_POST['accion'] ?? '';

// Enviar una respuesta a la papelera es del autor o de un admin — el resto
// de las acciones de este archivo (fijar/cerrar/eliminar TEMA) siguen siendo
// solo de admin, se validan más abajo.
if ($accion === 'eliminar_respuesta') {
    $respuestaId = (int) ($_POST['respuesta_id'] ?? 0);
    $stmt = $conn->prepare('SELECT id, usuario_id FROM foro_respuestas WHERE id = ? AND eliminado_en IS NULL LIMIT 1');
    $stmt->bind_param('i', $respuestaId);
    $stmt->execute();
    $respuesta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$respuesta) {
        echo json_encode(['success' => false, 'message' => 'La respuesta no existe.']);
        exit;
    }
    if ((int) $respuesta['usuario_id'] !== (int) $usuario['id'] && !$esAdmin) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para hacer esto.']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE foro_respuestas SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ?');
    $stmt->bind_param('ii', $usuario['id'], $respuestaId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if (!$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para hacer esto.']);
    exit;
}

$temaId = (int) ($_POST['tema_id'] ?? 0);

$stmt = $conn->prepare('SELECT id FROM foro_temas WHERE id = ? LIMIT 1');
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

    case 'ocultar':
    case 'mostrar':
        $stmt = $conn->prepare('UPDATE foro_temas SET oculto = ? WHERE id = ?');
        $valor = $accion === 'ocultar' ? 1 : 0;
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
