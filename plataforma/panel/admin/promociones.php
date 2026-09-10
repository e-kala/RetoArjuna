<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_promocion') {
        $stmt = $conn->prepare('DELETE FROM promociones WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Promoción eliminada.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE promociones SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo, fecha_inicio, fecha_fin FROM promociones WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $activo = (int) $p['activo'];
            $vigente = $activo && $p['fecha_inicio'] <= date('Y-m-d H:i:s') && date('Y-m-d H:i:s') <= $p['fecha_fin'];
            $estadoHtml = $vigente
                ? '<span class="badge bg-success">Vigente ahora</span>'
                : ($activo ? '<span class="badge bg-warning text-dark">Activa, fuera de fecha</span>' : '<span class="badge bg-secondary">Desactivada</span>');
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Promoción activada.' : 'Promoción desactivada.',
                'boton_texto' => $activo ? 'Desactivar' : 'Activar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $estadoHtml,
            ]);
            exit;
        }
    }
    header('Location: promociones.php');
    exit;
}

$promociones = $conn->query(
    "SELECT p.*,
       COALESCE(c.titulo, e.titulo, pr.nombre, m.nombre) AS item_nombre,
       c.slug AS curso_slug, e.slug AS evento_slug, pr.slug AS producto_slug,
       CASE WHEN p.curso_id IS NOT NULL THEN 'Curso' WHEN p.evento_id IS NOT NULL THEN 'Evento'
            WHEN p.producto_id IS NOT NULL THEN 'Producto' ELSE 'Membresía' END AS item_tipo
     FROM promociones p
     LEFT JOIN cursos c ON c.id = p.curso_id
     LEFT JOIN eventos e ON e.id = p.evento_id
     LEFT JOIN productos pr ON pr.id = p.producto_id
     LEFT JOIN membresias m ON m.id = p.membresia_id
     ORDER BY p.fecha_inicio DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Promociones';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Promociones públicas</h1>
  <a href="promocion_form.php" class="btn btn-success btn-sm">+ Nueva promoción</a>
</div>
<p class="text-muted small">Descuentos temporales, visibles para cualquiera (Visitantes incluidos) — independientes de la membresía. Activan y vencen solos por fecha.</p>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Nombre</th><th>Aplica a</th><th>Descuento</th><th>Vigencia</th><th>Creada</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($promociones as $p): ?>
      <?php $vigente = $p['activo'] && $p['fecha_inicio'] <= date('Y-m-d H:i:s') && date('Y-m-d H:i:s') <= $p['fecha_fin']; ?>
      <tr>
        <td><?= htmlspecialchars($p['nombre']) ?> <?= $p['modalidad'] === 'preventa' ? '<span class="badge bg-info">preventa</span>' : '' ?> <?= $p['combinable'] ? '<span class="badge bg-secondary">combinable</span>' : '' ?></td>
        <td>
          <?= htmlspecialchars($p['item_tipo']) ?>:
          <?php if ($p['curso_id']): ?>
            <a href="../../index.php?action=curso&slug=<?= urlencode($p['curso_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['item_nombre']) ?></a>
          <?php elseif ($p['evento_id']): ?>
            <a href="../../index.php?action=evento&slug=<?= urlencode($p['evento_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['item_nombre']) ?></a>
          <?php elseif ($p['producto_id']): ?>
            <a href="../../index.php?action=producto&slug=<?= urlencode($p['producto_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['item_nombre']) ?></a>
          <?php else: ?>
            <?= htmlspecialchars((string) $p['item_nombre']) ?>
          <?php endif; ?>
        </td>
        <td><?= $p['tipo_descuento'] === 'precio_fijo' ? '$' . number_format((float) $p['valor'], 2) . ' fijo' : (float) $p['valor'] . '%' ?></td>
        <td class="small"><?= htmlspecialchars($p['fecha_inicio']) ?> — <?= htmlspecialchars($p['fecha_fin']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($p['created_at']))) ?></td>
        <td data-ajax-estado><?= $vigente ? '<span class="badge bg-success">Vigente ahora</span>' : ((int) $p['activo'] === 1 ? '<span class="badge bg-warning text-dark">Activa, fuera de fecha</span>' : '<span class="badge bg-secondary">Desactivada</span>') ?></td>
        <td class="d-flex gap-2">
          <a href="promocion_form.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" data-ajax="toggle" class="d-flex align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" role="switch" <?= (int) $p['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $p['activo'] === 1 ? 'Desactivar' : 'Activar' ?>"></div></form>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar esta promoción?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="accion" value="eliminar_promocion"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$promociones): ?><tr><td colspan="7" class="text-muted">No hay promociones todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
