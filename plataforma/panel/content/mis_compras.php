<?php
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT ms.estado, ms.periodo_actual_fin, ms.created_at, m.nombre, m.precio, m.intervalo
     FROM membresia_suscripciones ms
     JOIN membresias m ON m.id = ms.membresia_id
     WHERE ms.usuario_id = ?
     ORDER BY ms.created_at DESC"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$suscripciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$estadoSuscripcionBadge = ['activa' => 'bg-success', 'cancelada' => 'bg-secondary', 'vencida' => 'bg-danger'];

$stmt = $conn->prepare(
    "SELECT p.*, 'curso' AS item_tipo, c.titulo AS item_titulo, NULL AS item_producto_tipo, NULL AS item_archivo_digital
     FROM pagos p JOIN cursos c ON c.id = p.curso_id
     WHERE p.usuario_id = ?
     UNION ALL
     SELECT p.*, 'evento', e.titulo, NULL, NULL
     FROM pagos p JOIN eventos e ON e.id = p.evento_id
     WHERE p.usuario_id = ?
     UNION ALL
     SELECT p.*, 'producto', pr.nombre, pr.tipo, pr.archivo_digital
     FROM pagos p JOIN productos pr ON pr.id = p.producto_id
     WHERE p.usuario_id = ?
     ORDER BY created_at DESC"
);
$stmt->bind_param('iii', $usuarioPerfilId, $usuarioPerfilId, $usuarioPerfilId);
$stmt->execute();
$compras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$estadoBadge = ['pendiente' => 'bg-warning', 'confirmado' => 'bg-success', 'rechazado' => 'bg-danger'];
$tipoLabel = ['curso' => 'Curso', 'evento' => 'Evento', 'producto' => 'Producto'];
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Mis compras y membresía</h1>
</div>
<?php if (($_GET['pago'] ?? '') === 'ok'): ?>
    <div class="alert alert-success">¡Gracias por tu compra! En cuanto se confirme el pago verás el estado actualizado aquí.</div>
<?php endif; ?>
<?php if ($suscripciones): ?>
    <h2 class="h5 mb-3">Mi membresía</h2>
    <table class="table table-bordered bg-white mb-4">
        <thead>
            <tr><th>Membresía</th><th>Precio</th><th>Estado</th><th>Vigente hasta</th><th>Desde</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($suscripciones as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['nombre']) ?></td>
                    <td>$<?= number_format((float) $s['precio'], 2) ?> MXN / <?= $s['intervalo'] === 'anual' ? 'año' : 'mes' ?></td>
                    <td><span class="badge <?= $estadoSuscripcionBadge[$s['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($s['estado']) ?></span></td>
                    <td><?= $s['periodo_actual_fin'] ? htmlspecialchars($s['periodo_actual_fin']) : '—' ?></td>
                    <td><?= htmlspecialchars($s['created_at']) ?></td>
                    <td>
                        <?php if ($s['estado'] === 'activa'): ?>
                            <a href="<?= BASE_URL ?>/backend/pagos/membresia_portal.php" class="btn btn-sm btn-outline-primary">Gestionar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<h2 class="h5 mb-3">Historial de compras</h2>
<table class="table table-bordered bg-white">
    <thead>
        <tr><th>Artículo</th><th>Tipo</th><th>Monto</th><th>Método</th><th>Estado</th><th>Fecha</th><th>Detalle</th><th>Acciones</th></tr>
    </thead>
    <tbody>
        <?php foreach ($compras as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['item_titulo']) ?></td>
                <td><?= $tipoLabel[$c['item_tipo']] ?? htmlspecialchars($c['item_tipo']) ?></td>
                <td>$<?= number_format((float) $c['monto'], 2) ?> MXN<?= (int) $c['cantidad'] > 1 ? ' (x' . (int) $c['cantidad'] . ')' : '' ?></td>
                <td><?= htmlspecialchars($c['metodo_pago']) ?></td>
                <td><span class="badge <?= $estadoBadge[$c['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($c['estado']) ?></span></td>
                <td><?= htmlspecialchars($c['created_at']) ?></td>
                <td>
                    <?php if ($c['item_tipo'] === 'producto' && $c['item_producto_tipo'] === 'digital'): ?>
                        <?php if ($c['estado'] === 'confirmado' && $c['item_archivo_digital']): ?>
                            <?php $enlaceDescarga = preg_match('~^https?://~i', $c['item_archivo_digital']) ? $c['item_archivo_digital'] : BASE_URL . '/' . $c['item_archivo_digital']; ?>
                            <a href="<?= htmlspecialchars($enlaceDescarga) ?>" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;" target="_blank">Descargar</a>
                        <?php else: ?>
                            <span class="text-muted small">Disponible al confirmar el pago</span>
                        <?php endif; ?>
                    <?php elseif ($c['item_tipo'] === 'producto' && $c['item_producto_tipo'] === 'fisico'): ?>
                        <?php if ($c['direccion_envio']): ?>
                            <span class="text-muted small">Envío a: <?= htmlspecialchars($c['direccion_envio']) ?></span>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" onsubmit="return confirm('¿Eliminar este registro de compra? Esta acción no se puede deshacer.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="pago_id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="accion" value="eliminar_compra">
                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$compras): ?>
            <tr><td colspan="8" class="text-muted">Aún no tienes compras registradas.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
