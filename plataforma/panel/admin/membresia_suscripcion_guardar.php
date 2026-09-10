<?php
// Único punto de guardado del editor modal de membresía de usuario — cubre
// alta manual nueva, confirmar una solicitud de transferencia pendiente, y
// editar una suscripción ya activa (cambiar método, fecha, si caduca o si se
// renueva automáticamente). Usado desde panel/admin/usuarios.php y
// panel/admin/membresias.php (ver _membresia_modal.php).
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_role('admin');
requerir_csrf_form();

$esAjax = es_peticion_ajax();
$miId = (int) $_SESSION['usuario_perfil_id'];

$suscripcionId = (int) ($_POST['suscripcion_id'] ?? 0);
$usuarioId = (int) ($_POST['usuario_id'] ?? 0);
$membresiaId = (int) ($_POST['membresia_id'] ?? 0);
$metodo = in_array($_POST['metodo'] ?? '', ['manual', 'transferencia', 'stripe'], true) ? $_POST['metodo'] : 'manual';
$fechaInicio = trim($_POST['fecha_inicio'] ?? '');
$caduca = isset($_POST['caduca']);
$renovacionAutomatica = isset($_POST['renovacion_automatica']);
$notificar = isset($_POST['notificar']);
$stripeCustomerId = trim($_POST['stripe_customer_id'] ?? '') !== '' ? trim($_POST['stripe_customer_id']) : null;
$stripeSubscriptionId = trim($_POST['stripe_subscription_id'] ?? '') !== '' ? trim($_POST['stripe_subscription_id']) : null;

if ($stripeSubscriptionId !== null) {
    $stmt = $conn->prepare('SELECT id FROM membresia_suscripciones WHERE stripe_subscription_id = ? AND id <> ?');
    $stmt->bind_param('si', $stripeSubscriptionId, $suscripcionId);
    $stmt->execute();
    $duplicado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($duplicado) {
        $mensajeError = 'Ese Stripe Subscription ID ya está vinculado a otro usuario.';
        if ($esAjax) {
            echo json_encode(['success' => false, 'mensaje' => $mensajeError]);
            exit;
        }
        header('Location: ' . ($_POST['volver'] ?? 'membresias.php') . '?error=' . urlencode($mensajeError));
        exit;
    }
}

$volver = $_POST['volver'] ?? 'membresias.php';
if (!in_array($volver, ['usuarios.php', 'membresias.php'], true)) {
    $volver = 'membresias.php';
}
$volverQuery = trim($_POST['volver_query'] ?? '');

if (!$suscripcionId && $usuarioId && $membresiaId) {
    $stmt = $conn->prepare('SELECT id FROM membresia_suscripciones WHERE usuario_id = ? AND membresia_id = ? LIMIT 1');
    $stmt->bind_param('ii', $usuarioId, $membresiaId);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        $suscripcionId = (int) $existente['id'];
    } else {
        $estadoInicial = 'pendiente';
        $stmt = $conn->prepare('INSERT INTO membresia_suscripciones (usuario_id, membresia_id, metodo, estado, stripe_customer_id, stripe_subscription_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissss', $usuarioId, $membresiaId, $metodo, $estadoInicial, $stripeCustomerId, $stripeSubscriptionId);
        $stmt->execute();
        $suscripcionId = $stmt->insert_id;
        $stmt->close();
    }
}

if ($suscripcionId) {
    if ($membresiaId) {
        // Permite reasignar a otra membresía al confirmar/editar.
        $stmt = $conn->prepare('UPDATE membresia_suscripciones SET membresia_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $membresiaId, $suscripcionId);
        $stmt->execute();
        $stmt->close();
    }

    activar_suscripcion_membresia($conn, $suscripcionId, $fechaInicio, $miId, $metodo, $caduca, $renovacionAutomatica, $stripeCustomerId, $stripeSubscriptionId);

    if ($notificar) {
        $stmt = $conn->prepare(
            "SELECT u.id, u.email_cache, m.nombre FROM membresia_suscripciones s
             JOIN usuarios_perfil u ON u.id = s.usuario_id JOIN membresias m ON m.id = s.membresia_id
             WHERE s.id = ?"
        );
        $stmt->bind_param('i', $suscripcionId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fila && $fila['email_cache']) {
            enviar_email_membresia_activada((int) $fila['id'], $fila['email_cache'], $fila['nombre']);
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
