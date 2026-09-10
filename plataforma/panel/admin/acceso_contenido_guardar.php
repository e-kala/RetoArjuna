<?php
// Único punto de guardado del otorgamiento manual de acceso a un curso o
// evento (cortesía de admin) — mismo patrón PRG que
// membresia_suscripcion_guardar.php. Usado desde panel/admin/usuarios.php
// (ver _acceso_modal.php).
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_role('admin');
requerir_csrf_form();

$esAjax = es_peticion_ajax();
$miId = (int) $_SESSION['usuario_perfil_id'];
$usuarioId = (int) ($_POST['usuario_id'] ?? 0);
$tipo = $_POST['tipo'] ?? '';
$itemId = (int) ($_POST['item_id'] ?? 0);
$notificar = isset($_POST['notificar']);

$volver = $_POST['volver'] ?? 'usuarios.php';
if (!in_array($volver, ['usuarios.php'], true)) {
    $volver = 'usuarios.php';
}
$volverQuery = trim($_POST['volver_query'] ?? '');

if ($usuarioId && $itemId && in_array($tipo, ['curso', 'evento'], true)) {
    if ($tipo === 'curso') {
        $stmt = $conn->prepare('SELECT id, titulo, slug FROM cursos WHERE id = ? AND activo = 1');
    } else {
        $stmt = $conn->prepare('SELECT id, titulo, slug FROM eventos WHERE id = ? AND activo = 1');
    }
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($item) {
        if ($tipo === 'curso') {
            $stmt = $conn->prepare(
                "INSERT INTO curso_inscripciones (usuario_id, curso_id, metodo, activada_por) VALUES (?, ?, 'manual', ?)
                 ON DUPLICATE KEY UPDATE metodo = 'manual', activada_por = VALUES(activada_por)"
            );
            $stmt->bind_param('iii', $usuarioId, $itemId, $miId);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado, metodo, activada_por) VALUES (?, ?, 'inscrito', 'manual', ?)
                 ON DUPLICATE KEY UPDATE estado = IF(estado = 'cancelado', 'inscrito', estado), metodo = 'manual', activada_por = VALUES(activada_por)"
            );
            $stmt->bind_param('iii', $usuarioId, $itemId, $miId);
        }
        $stmt->execute();
        $stmt->close();

        if ($notificar) {
            $stmt = $conn->prepare('SELECT email_cache FROM usuarios_perfil WHERE id = ?');
            $stmt->bind_param('i', $usuarioId);
            $stmt->execute();
            $usuarioFila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($usuarioFila && $usuarioFila['email_cache']) {
                $enlace = SITE_URL . '/index.php?action=' . $tipo . '&slug=' . urlencode($item['slug']);
                if ($tipo === 'curso') {
                    $enlace .= '&bienvenida=1';
                }
                enviar_email_inscripcion($usuarioId, $usuarioFila['email_cache'], $item['titulo'], $enlace);
            }
        }
    }
}

$destino = $volver . ($volverQuery !== '' ? '?' . $volverQuery : '');
if ($esAjax) {
    echo json_encode(['success' => true, 'redirect' => $destino]);
    exit;
}
header('Location: ' . $destino);
exit;
