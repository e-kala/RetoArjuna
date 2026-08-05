<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_cupon') {
        $stmt = $conn->prepare('DELETE FROM cupones WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE cupones SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: cupones.php');
    exit;
}

$cupones = $conn->query('SELECT * FROM cupones ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Cupones';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Cupones</h1>
  <a href="cupon_form.php" class="btn btn-success btn-sm">+ Nuevo cupón</a>
</div>
<table class="table table-bordered bg-white">
  <thead><tr><th>Código</th><th>Descuento</th><th>Vigencia</th><th>Usos</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($cupones as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['codigo']) ?></td>
        <td><?= $c['tipo'] === 'porcentaje' ? ((float) $c['valor']) . '%' : '$' . number_format((float) $c['valor'], 2) ?></td>
        <td><?= htmlspecialchars((string) $c['vigencia_desde']) ?> — <?= htmlspecialchars((string) $c['vigencia_hasta']) ?></td>
        <td><?= (int) $c['usos_actuales'] ?> / <?= $c['uso_maximo'] !== null ? (int) $c['uso_maximo'] : '∞' ?></td>
        <td><?= (int) $c['activo'] === 1 ? 'Activo' : 'Inactivo' ?></td>
        <td class="d-flex gap-2">
          <a href="cupon_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><button class="btn btn-sm btn-outline-secondary"><?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button></form>
          <form method="post" onsubmit="return confirm('¿Eliminar cupón?');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="accion" value="eliminar_cupon"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
