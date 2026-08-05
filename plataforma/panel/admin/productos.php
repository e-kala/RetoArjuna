<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_producto') {
        $stmt = $conn->prepare('DELETE FROM productos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE productos SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: productos.php');
    exit;
}

$productos = $conn->query('SELECT * FROM productos ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Productos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Productos</h1>
  <a href="producto_form.php" class="btn btn-success btn-sm">+ Nuevo producto</a>
</div>
<table class="table table-bordered bg-white">
  <thead><tr><th>Nombre</th><th>Tipo</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($productos as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['nombre']) ?></td>
        <td><?= $p['tipo'] === 'fisico' ? 'Físico' : 'Digital' ?></td>
        <td>$<?= number_format((float) $p['precio'], 2) ?></td>
        <td><?= $p['stock'] !== null ? (int) $p['stock'] : '—' ?></td>
        <td><?= (int) $p['activo'] === 1 ? 'Publicado' : 'Oculto' ?></td>
        <td class="d-flex gap-2">
          <a href="producto_form.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="accion" value="toggle_activo">
            <button class="btn btn-sm btn-outline-secondary"><?= (int) $p['activo'] === 1 ? 'Ocultar' : 'Publicar' ?></button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este producto?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_producto">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
