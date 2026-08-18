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
    } elseif (($_POST['accion'] ?? '') === 'convertir_a_evento') {
        $stmt = $conn->prepare('SELECT * FROM cursos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($c) {
            // Se copian los campos en común; el resto queda con valores por
            // defecto razonables — fecha_inicio se pone en el pasado (la
            // fecha de creación del curso) para que aparezca de una vez en
            // "eventos pasados/grabados", y el admin la ajusta si hace falta.
            $slugEvento = $c['slug'];
            $stmt = $conn->prepare(
                "INSERT INTO eventos (titulo, slug, descripcion, tipo, fecha_inicio, precio, imagen_portada, gratuito, activo)
                 VALUES (?, ?, ?, 'online', ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssdsii', $c['titulo'], $slugEvento, $c['descripcion'], $c['created_at'], $c['precio'], $c['imagen_portada'], $c['gratuito'], $c['activo']);
            if ($stmt->execute()) {
                $nuevoEventoId = $stmt->insert_id;
                $stmt->close();
                // El curso original se oculta, NO se borra — conserva su
                // historial (progreso/pagos/certificados). Las lecciones sí se
                // REASIGNAN al evento nuevo para que el contenido siga siendo
                // accesible (ver schema_lecciones_compartidas.sql).
                $stmtLec = $conn->prepare('UPDATE lecciones SET curso_id = NULL, evento_id = ? WHERE curso_id = ?');
                $stmtLec->bind_param('ii', $nuevoEventoId, $id);
                $stmtLec->execute();
                $stmtLec->close();

                $stmtOff = $conn->prepare('UPDATE cursos SET activo = 0 WHERE id = ?');
                $stmtOff->bind_param('i', $id);
                $stmtOff->execute();
                $stmtOff->close();
                header('Location: contenido_form.php?tipo=evento&id=' . $nuevoEventoId);
                exit;
            }
            $stmt->close();
        }
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
  <a href="contenido_form.php?tipo=curso" class="btn btn-success btn-sm">+ Nuevo curso</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>Precio</th><th>Lecciones</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($cursos as $c): ?>
      <tr>
        <td><a href="../../index.php?action=curso&slug=<?= urlencode($c['slug']) ?>" target="_blank"><?= htmlspecialchars($c['titulo']) ?></a></td>
        <td><?= (int) $c['gratuito'] === 1 ? 'Gratis' : '$' . number_format((float) $c['precio'], 2) ?></td>
        <td><a href="lecciones.php?curso_id=<?= (int) $c['id'] ?>"><?= (int) $c['total_lecciones'] ?> gestionar</a></td>
        <td><?= (int) $c['activo'] === 1 ? 'Publicado' : 'Oculto' ?></td>
        <td class="d-flex gap-2 flex-wrap">
          <a href="contenido_form.php?tipo=curso&id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="toggle_activo">
            <button class="btn btn-sm btn-outline-secondary"><?= (int) $c['activo'] === 1 ? 'Ocultar' : 'Publicar' ?></button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Convertir esto a evento? El curso se ocultará (no se borra) y se creará un evento nuevo con estos datos, que podrás terminar de ajustar.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="convertir_a_evento">
            <button class="btn btn-sm btn-outline-dark">📅 Convertir a evento</button>
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
</div>
<?php include __DIR__ . '/_footer.php'; ?>
