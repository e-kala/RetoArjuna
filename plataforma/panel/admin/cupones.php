<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_cupon') {
        $stmt = $conn->prepare('DELETE FROM cupones WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Cupón eliminado.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE cupones SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM cupones WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Cupón activado.' : 'Cupón desactivado.',
                'boton_texto' => $activo ? 'Desactivar' : 'Activar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Desactivado</span>',
            ]);
            exit;
        }
    }
    header('Location: cupones.php');
    exit;
}

$cupones = $conn->query(
    "SELECT c.*,
       (SELECT COUNT(*) FROM pagos WHERE cupon_id = c.id AND estado = 'confirmado') +
       (SELECT COUNT(*) FROM membresia_suscripciones WHERE cupon_id = c.id AND estado = 'activa') AS usos,
       (SELECT COUNT(*) FROM cupon_alcance WHERE cupon_id = c.id) AS n_alcance
     FROM cupones c
     ORDER BY c.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Cupones';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Cupones</h1>
  <a href="cupon_form.php" class="btn btn-success btn-sm">+ Nuevo cupón</a>
</div>
<p class="text-muted small">Cupones internos (calculados en la plataforma, no en Stripe) — código o enlace, un solo uso por Cuenta Arjuna.</p>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Código</th><th>Descuento</th><th>Alcance</th><th>Vigencia</th><th>Usos</th><th>Creado</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($cupones as $c): ?>
      <tr>
        <td><code><?= htmlspecialchars($c['codigo']) ?></code> <?= $c['origen'] === 'incentivo_cuenta_nueva' ? '<span class="badge bg-info">incentivo cuenta nueva</span>' : '' ?> <?= $c['combinable'] ? '<span class="badge bg-secondary">combinable</span>' : '' ?></td>
        <td><?= $c['tipo_descuento'] === 'monto' ? '$' . number_format((float) $c['valor'], 2) : (float) $c['valor'] . '%' ?></td>
        <td><?= (int) $c['n_alcance'] === 0 ? 'Global' : $c['n_alcance'] . ' ítem(s)' ?></td>
        <td class="small"><?= $c['fecha_inicio'] ? htmlspecialchars($c['fecha_inicio']) : 'sin inicio' ?> — <?= $c['fecha_fin'] ? htmlspecialchars($c['fecha_fin']) : 'sin fin' ?></td>
        <td><?= (int) $c['usos'] ?><?= $c['usos_totales'] !== null ? ' / ' . (int) $c['usos_totales'] : '' ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['created_at']))) ?></td>
        <td data-ajax-estado><?= (int) $c['activo'] === 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Desactivado</span>' ?></td>
        <td class="d-flex gap-2">
          <a href="cupon_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" data-ajax="toggle" class="d-flex align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" role="switch" <?= (int) $c['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?>"></div></form>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar este cupón?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="accion" value="eliminar_cupon"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$cupones): ?><tr><td colspan="8" class="text-muted">No hay cupones todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
