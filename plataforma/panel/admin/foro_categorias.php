<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../foro/backend/foro_helpers.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_categoria') {
        $stmt = $conn->prepare('DELETE FROM foro_categorias WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: foro_categorias.php');
    exit;
}

$categorias = foro_categorias_arbol_plano(foro_categorias_arbol());

$pageTitle = 'Categorías del foro';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Categorías del foro</h1>
  <a href="foro_categoria_form.php" class="btn btn-success btn-sm">+ Nueva categoría</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Nombre</th><th>Slug</th><th>Orden</th><th>Temas</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($categorias as $c): ?>
      <tr>
        <td><?= str_repeat('— ', (int) $c['nivel']) ?><?= htmlspecialchars($c['nombre']) ?></td>
        <td><?= htmlspecialchars($c['slug']) ?></td>
        <td><?= (int) $c['orden'] ?></td>
        <td><?= (int) $c['temas_count'] ?></td>
        <td class="d-flex gap-2">
          <a href="foro_categoria_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" onsubmit="return confirm('¿Eliminar categoría? Si tiene subcategorías, también se eliminan ellas y TODOS los temas de esta categoría y de sus subcategorías.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_categoria">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categorias): ?><tr><td colspan="5" class="text-muted">No hay categorías creadas.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
