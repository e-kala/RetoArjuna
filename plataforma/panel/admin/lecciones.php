<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

// Una lección pertenece a un curso O a un evento, nunca ambos (ver
// schema_lecciones_compartidas.sql) — esta pantalla gestiona lecciones para
// cualquiera de los dos, según qué parámetro venga en la URL.
$cursoId = (int) ($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
$eventoId = (int) ($_GET['evento_id'] ?? $_POST['evento_id'] ?? 0);
$esEvento = $eventoId > 0;

if ($esEvento) {
    $stmt = $conn->prepare('SELECT id, titulo FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $padre = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $padreId = $eventoId;
    $volverUrl = 'eventos.php';
    $nuevaLeccionUrl = 'leccion_form.php?evento_id=' . $padreId;
} else {
    $stmt = $conn->prepare('SELECT id, titulo FROM cursos WHERE id = ?');
    $stmt->bind_param('i', $cursoId);
    $stmt->execute();
    $padre = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $padreId = $cursoId;
    $volverUrl = 'cursos.php';
    $nuevaLeccionUrl = 'leccion_form.php?curso_id=' . $padreId;
}

if (!$padre) {
    header('Location: ' . $volverUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_leccion') {
    $esAjax = es_peticion_ajax();
    $id = (int) $_POST['id'];
    if ($esEvento) {
        $stmt = $conn->prepare('DELETE FROM lecciones WHERE id = ? AND evento_id = ?');
    } else {
        $stmt = $conn->prepare('DELETE FROM lecciones WHERE id = ? AND curso_id = ?');
    }
    $stmt->bind_param('ii', $id, $padreId);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) {
        echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Lección eliminada.']);
        exit;
    }
    header('Location: lecciones.php?' . ($esEvento ? 'evento_id=' : 'curso_id=') . $padreId);
    exit;
}

$stmt = $esEvento
    ? $conn->prepare('SELECT * FROM lecciones WHERE evento_id = ? ORDER BY orden')
    : $conn->prepare('SELECT * FROM lecciones WHERE curso_id = ? ORDER BY orden');
$stmt->bind_param('i', $padreId);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Lecciones · ' . $padre['titulo'];
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Lecciones de "<?= htmlspecialchars($padre['titulo']) ?>"</h1>
  <a href="<?= htmlspecialchars($nuevaLeccionUrl) ?>" class="btn btn-success btn-sm">+ Nueva lección</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Orden</th><th>Título</th><th>Tipo</th><th>Estado</th><th>Demo</th><th>Creado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($lecciones as $l): ?>
      <tr>
        <td><?= (int) $l['orden'] ?></td>
        <td><a href="../../index.php?action=leccion&id=<?= (int) $l['id'] ?>&preview=1" target="_blank"><?= htmlspecialchars($l['titulo']) ?></a></td>
        <td><?= $l['tipo_contenido'] === 'quiz' ? 'Quiz' : 'Contenido' ?></td>
        <td><?= $l['estado_publicacion'] === 'publicado' ? '<span class="badge bg-success">Publicado</span>' : '<span class="badge bg-secondary">Borrador</span>' ?></td>
        <td><?= (int) $l['vista_previa'] === 1 ? 'Sí' : 'No' ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($l['created_at']))) ?></td>
        <td class="d-flex gap-2">
          <a href="leccion_form.php?<?= $esEvento ? 'evento_id=' : 'curso_id=' ?><?= $padreId ?>&id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <?php if ($l['tipo_contenido'] === 'quiz'): ?>
            <a href="quiz_form.php?leccion_id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-info">Preguntas</a>
          <?php endif; ?>
          <a href="../../index.php?action=leccion&id=<?= (int) $l['id'] ?>&preview=1" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar esta lección?">
            <?= csrf_field() ?>
            <input type="hidden" name="<?= $esEvento ? 'evento_id' : 'curso_id' ?>" value="<?= $padreId ?>">
            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_leccion">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
