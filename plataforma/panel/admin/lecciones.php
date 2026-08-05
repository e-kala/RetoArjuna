<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$cursoId = (int) ($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM cursos WHERE id = ?');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso) {
    header('Location: cursos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_leccion') {
    $id = (int) $_POST['id'];
    $stmt = $conn->prepare('DELETE FROM lecciones WHERE id = ? AND curso_id = ?');
    $stmt->bind_param('ii', $id, $cursoId);
    $stmt->execute();
    $stmt->close();
    header('Location: lecciones.php?curso_id=' . $cursoId);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM lecciones WHERE curso_id = ? ORDER BY orden');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Lecciones · ' . $curso['titulo'];
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Lecciones de "<?= htmlspecialchars($curso['titulo']) ?>"</h1>
  <a href="leccion_form.php?curso_id=<?= $cursoId ?>" class="btn btn-success btn-sm">+ Nueva lección</a>
</div>
<table class="table table-bordered bg-white">
  <thead><tr><th>Orden</th><th>Título</th><th>Tipo</th><th>Demo</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($lecciones as $l): ?>
      <tr>
        <td><?= (int) $l['orden'] ?></td>
        <td><?= htmlspecialchars($l['titulo']) ?></td>
        <td><?= htmlspecialchars($l['tipo_contenido']) ?></td>
        <td><?= (int) $l['vista_previa'] === 1 ? 'Sí' : 'No' ?></td>
        <td class="d-flex gap-2">
          <a href="leccion_form.php?curso_id=<?= $cursoId ?>&id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <?php if ($l['tipo_contenido'] === 'quiz'): ?>
            <a href="quiz_form.php?leccion_id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-info">Preguntas</a>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('¿Eliminar esta lección?');">
            <?= csrf_field() ?>
            <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_leccion">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
