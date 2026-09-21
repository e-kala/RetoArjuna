<?php
// Manejo POST compartido entre pagos.php y pagos_usuario.php — ambas
// páginas necesitan las mismas 3 acciones (confirmar/rechazar un pago
// pendiente, quitar el acceso a un producto ya confirmado, eliminar un
// pago) sobre la misma tabla `pagos`, así que viven en un solo lugar en vez
// de duplicarse. Requiere que el archivo que incluya esto ya haya llamado
// require_role('admin') y requerir_csrf_form() antes.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'validar_pago') {
    $esAjax = es_peticion_ajax();
    $pagoId = (int) $_POST['pago_id'];
    $nuevoEstado = $_POST['estado'] ?? '';
    if (in_array($nuevoEstado, ['confirmado', 'rechazado'], true)) {
        $stmt = $conn->prepare(
            'UPDATE pagos SET estado = ?, fecha_validacion = NOW(), validado_por = ?,
             fecha_pago = IF(? = "confirmado", NOW(), fecha_pago) WHERE id = ? AND estado = "pendiente"'
        );
        $miId = (int) $_SESSION['usuario_perfil_id'];
        $stmt->bind_param('sisi', $nuevoEstado, $miId, $nuevoEstado, $pagoId);
        $stmt->execute();
        $afectados = $stmt->affected_rows;
        $stmt->close();

        if ($afectados > 0 && $nuevoEstado === 'confirmado') {
            $stmt = $conn->prepare(
                "SELECT p.usuario_id, p.evento_id, p.whatsapp_telefono, u.email_cache AS email,
                        COALESCE(c.titulo, e.titulo, pr.nombre) AS titulo,
                        CASE WHEN p.curso_id IS NOT NULL THEN 'curso' WHEN p.evento_id IS NOT NULL THEN 'evento' ELSE 'producto' END AS tipo,
                        COALESCE(c.slug, e.slug, pr.slug) AS slug
                 FROM pagos p
                 JOIN usuarios_perfil u ON u.id = p.usuario_id
                 LEFT JOIN cursos c ON c.id = p.curso_id
                 LEFT JOIN eventos e ON e.id = p.evento_id
                 LEFT JOIN productos pr ON pr.id = p.producto_id
                 WHERE p.id = ?"
            );
            $stmt->bind_param('i', $pagoId);
            $stmt->execute();
            $info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($info && $info['email']) {
                $enlace = SITE_URL . '/index.php?action=' . $info['tipo'] . '&slug=' . urlencode($info['slug']);
                if ($info['tipo'] === 'curso') {
                    $enlace .= '&bienvenida=1';
                }
                enviar_email_inscripcion((int) $info['usuario_id'], $info['email'], $info['titulo'], $enlace);
                // Aviso dentro de la plataforma (campanita) — mismo momento que
                // el correo de arriba, para quien no revise su bandeja de
                // entrada pronto. Enlace relativo (BASE_URL, no SITE_URL) para
                // seguir el mismo patrón que el resto de notificacion_crear().
                // whatsapp_telefono (capturado junto al comprobante, ver
                // checkout.php) tiene prioridad sobre el del perfil — es el
                // número que el usuario dijo explícitamente para ESTE pago.
                notificacion_crear(
                    (int) $info['usuario_id'],
                    'pago_confirmado',
                    'Tu comprobante fue confirmado',
                    'Tu pago de "' . $info['titulo'] . '" ya fue validado — tu acceso está activo.',
                    BASE_URL . '/index.php?action=' . $info['tipo'] . '&slug=' . urlencode($info['slug']),
                    null,
                    $info['whatsapp_telefono'] ?: null
                );
            }
            // Si es un evento de pago, la confirmación también cuenta como inscripción.
            if ($info && $info['tipo'] === 'evento' && $info['evento_id']) {
                $stmt = $conn->prepare(
                    "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado) VALUES (?, ?, 'inscrito')
                     ON DUPLICATE KEY UPDATE estado = IF(estado = 'cancelado', 'inscrito', estado)"
                );
                $stmt->bind_param('ii', $info['usuario_id'], $info['evento_id']);
                $stmt->execute();
                $stmt->close();
            }
        }
        if ($esAjax) {
            if ($afectados > 0) {
                $badge = $estadoBadge[$nuevoEstado] ?? 'bg-secondary';
                echo json_encode([
                    'success' => true,
                    'mensaje' => $nuevoEstado === 'confirmado' ? 'Pago confirmado.' : 'Pago rechazado.',
                    'estado_html' => '<span class="badge ' . $badge . '">' . htmlspecialchars($nuevoEstado) . '</span>',
                    'quitar_grupo' => true,
                ]);
            } else {
                echo json_encode(['success' => false, 'mensaje' => 'Este pago ya fue procesado por alguien más.']);
            }
            exit;
        }
    }
    header('Location: ' . basename($_SERVER['PHP_SELF']) . (isset($_POST['volver_qs']) ? '?' . $_POST['volver_qs'] : ''));
    exit;
}

// Quitar acceso a un producto ya comprado — a diferencia de "Rechazar"
// (solo aplica a un pago pendiente), esto revierte uno YA confirmado.
// usuario_compro_producto() (auth.php) solo mira si existe un pago
// 'confirmado' — pasarlo a 'rechazado' es suficiente para que el usuario
// deje de verlo como adquirido, sin borrar el rastro histórico del pago
// (a diferencia de "Eliminar"). Solo para producto: curso/evento/membresía
// tienen su propia tabla de inscripción/suscripción y su propia pantalla
// de gestión (lecciones.php, membresias.php) — no se tocan aquí.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'revocar_acceso_producto') {
    $esAjax = es_peticion_ajax();
    $pagoId = (int) ($_POST['pago_id'] ?? 0);
    $stmt = $conn->prepare(
        "UPDATE pagos SET estado = 'rechazado', fecha_validacion = NOW(), validado_por = ?
         WHERE id = ? AND estado = 'confirmado' AND producto_id IS NOT NULL"
    );
    $miId = (int) $_SESSION['usuario_perfil_id'];
    $stmt->bind_param('ii', $miId, $pagoId);
    $stmt->execute();
    $afectados = $stmt->affected_rows;
    $stmt->close();

    if ($esAjax) {
        if ($afectados > 0) {
            $badge = $estadoBadge['rechazado'] ?? 'bg-secondary';
            echo json_encode([
                'success' => true,
                'mensaje' => 'Acceso al producto revocado.',
                'estado_html' => '<span class="badge ' . $badge . '">rechazado</span>',
                'quitar_grupo' => true,
            ]);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Este pago ya no está confirmado, o no es de un producto.']);
        }
        exit;
    }
    header('Location: ' . basename($_SERVER['PHP_SELF']) . (isset($_POST['volver_qs']) ? '?' . $_POST['volver_qs'] : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_pago') {
    $esAjax = es_peticion_ajax();
    $pagoId = (int) ($_POST['pago_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM pagos WHERE id = ?');
    $stmt->bind_param('i', $pagoId);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) {
        echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Pago eliminado.']);
        exit;
    }
    header('Location: ' . basename($_SERVER['PHP_SELF']) . (isset($_POST['volver_qs']) ? '?' . $_POST['volver_qs'] : ''));
    exit;
}
