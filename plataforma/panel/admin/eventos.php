<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_evento') {
        $stmt = $conn->prepare('DELETE FROM eventos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE eventos SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: eventos.php');
    exit;
}

$eventos = $conn->query(
    "SELECT ev.*, (SELECT COUNT(*) FROM evento_inscripciones WHERE evento_id = ev.id AND estado <> 'cancelado') AS total_inscritos
     FROM eventos ev ORDER BY fecha_inicio DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Eventos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Eventos</h1>
  <a href="evento_form.php" class="btn btn-success btn-sm">+ Nuevo evento</a>
</div>
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>Tipo</th><th>Fecha</th><th>Precio</th><th>Inscritos</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($eventos as $ev): ?>
      <tr>
        <td><?= htmlspecialchars($ev['titulo']) ?></td>
        <td><?= $ev['tipo'] === 'online' ? 'En línea' : 'Presencial' ?></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ev['fecha_inicio']))) ?></td>
        <td><?= (int) $ev['gratuito'] === 1 ? 'Gratis' : '$' . number_format((float) $ev['precio'], 2) ?></td>
        <td><a href="evento_inscritos.php?evento_id=<?= (int) $ev['id'] ?>"><?= (int) $ev['total_inscritos'] ?> ver</a></td>
        <td><?= (int) $ev['activo'] === 1 ? 'Publicado' : 'Oculto' ?></td>
        <td class="d-flex gap-2">
          <a href="evento_form.php?id=<?= (int) $ev['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
            <input type="hidden" name="accion" value="toggle_activo">
            <button class="btn btn-sm btn-outline-secondary"><?= (int) $ev['activo'] === 1 ? 'Ocultar' : 'Publicar' ?></button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este evento?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_evento">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
