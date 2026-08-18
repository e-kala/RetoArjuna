<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_noticia') {
        $stmt = $conn->prepare('DELETE FROM noticias WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE noticias SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: noticias.php');
    exit;
}

$noticias = $conn->query('SELECT * FROM noticias ORDER BY publicada_at DESC')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Noticias';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Noticias</h1>
  <a href="noticia_form.php" class="btn btn-success btn-sm">+ Nueva noticia</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>Publicada</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($noticias as $n): ?>
      <tr>
        <td><?= htmlspecialchars($n['titulo']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($n['publicada_at']))) ?></td>
        <td><?= (int) $n['activo'] === 1 ? 'Publicada' : 'Oculta' ?></td>
        <td class="d-flex gap-2">
          <a href="noticia_form.php?id=<?= (int) $n['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><button class="btn btn-sm btn-outline-secondary"><?= (int) $n['activo'] === 1 ? 'Ocultar' : 'Publicar' ?></button></form>
          <form method="post" onsubmit="return confirm('¿Eliminar noticia?');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="accion" value="eliminar_noticia"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
