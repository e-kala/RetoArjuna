<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_curso') {
        $stmt = $conn->prepare('DELETE FROM cursos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE cursos SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: cursos.php');
    exit;
}

$cursos = $conn->query(
    "SELECT c.*, (SELECT COUNT(*) FROM lecciones WHERE curso_id = c.id) AS total_lecciones
     FROM cursos c ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Cursos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Cursos</h1>
  <a href="curso_form.php" class="btn btn-success btn-sm">+ Nuevo curso</a>
</div>
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>Precio</th><th>Lecciones</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($cursos as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['titulo']) ?></td>
        <td><?= (int) $c['gratuito'] === 1 ? 'Gratis' : '$' . number_format((float) $c['precio'], 2) ?></td>
        <td><a href="lecciones.php?curso_id=<?= (int) $c['id'] ?>"><?= (int) $c['total_lecciones'] ?> gestionar</a></td>
        <td><?= (int) $c['activo'] === 1 ? 'Publicado' : 'Oculto' ?></td>
        <td class="d-flex gap-2">
          <a href="curso_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="toggle_activo">
            <button class="btn btn-sm btn-outline-secondary"><?= (int) $c['activo'] === 1 ? 'Ocultar' : 'Publicar' ?></button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este curso y todo su contenido?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_curso">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
