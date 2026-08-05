<?php
require_once __DIR__ . '/../../backend/auth.php';
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

$categorias = $conn->query(
    'SELECT c.*, (SELECT COUNT(*) FROM foro_temas t WHERE t.categoria_id = c.id) AS temas_count
     FROM foro_categorias c ORDER BY orden ASC, nombre ASC'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Categorías del foro';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Categorías del foro</h1>
  <a href="foro_categoria_form.php" class="btn btn-success btn-sm">+ Nueva categoría</a>
</div>
<table class="table table-bordered bg-white">
  <thead><tr><th>Nombre</th><th>Slug</th><th>Orden</th><th>Temas</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($categorias as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['nombre']) ?></td>
        <td><?= htmlspecialchars($c['slug']) ?></td>
        <td><?= (int) $c['orden'] ?></td>
        <td><?= (int) $c['temas_count'] ?></td>
        <td class="d-flex gap-2">
          <a href="foro_categoria_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" onsubmit="return confirm('¿Eliminar categoría? Se eliminarán también sus temas.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_categoria">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
