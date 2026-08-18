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
    } elseif (($_POST['accion'] ?? '') === 'convertir_a_curso') {
        $stmt = $conn->prepare('SELECT * FROM eventos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ev = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ev) {
            $slugCurso = $ev['slug'];
            $stmt = $conn->prepare(
                "INSERT INTO cursos (titulo, slug, descripcion, nivel, precio, imagen_portada, gratuito, activo)
                 VALUES (?, ?, ?, 'principiante', ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssdsii', $ev['titulo'], $slugCurso, $ev['descripcion'], $ev['precio'], $ev['imagen_portada'], $ev['gratuito'], $ev['activo']);
            if ($stmt->execute()) {
                $nuevoCursoId = $stmt->insert_id;
                $stmt->close();
                // El evento original se oculta, NO se borra — conserva su
                // historial (inscripciones/certificados/pagos). Las lecciones
                // sí se REASIGNAN al curso nuevo para que el contenido siga
                // siendo accesible (ver schema_lecciones_compartidas.sql).
                $stmtLec = $conn->prepare('UPDATE lecciones SET evento_id = NULL, curso_id = ? WHERE evento_id = ?');
                $stmtLec->bind_param('ii', $nuevoCursoId, $id);
                $stmtLec->execute();
                $stmtLec->close();

                $stmtOff = $conn->prepare('UPDATE eventos SET activo = 0 WHERE id = ?');
                $stmtOff->bind_param('i', $id);
                $stmtOff->execute();
                $stmtOff->close();
                header('Location: contenido_form.php?tipo=curso&id=' . $nuevoCursoId);
                exit;
            }
            $stmt->close();
        }
    }
    header('Location: eventos.php');
    exit;
}

$eventos = $conn->query(
    "SELECT ev.*, (SELECT COUNT(*) FROM evento_inscripciones WHERE evento_id = ev.id AND estado <> 'cancelado') AS total_inscritos,
            (SELECT COUNT(*) FROM lecciones WHERE evento_id = ev.id) AS total_lecciones
     FROM eventos ev ORDER BY fecha_inicio DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Eventos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Eventos</h1>
  <a href="contenido_form.php?tipo=evento" class="btn btn-success btn-sm">+ Nuevo evento</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>Tipo</th><th>Fecha</th><th>Precio</th><th>Inscritos</th><th>Lecciones</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($eventos as $ev): ?>
      <tr>
        <td><a href="../../index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>" target="_blank"><?= htmlspecialchars($ev['titulo']) ?></a></td>
        <td><?= $ev['tipo'] === 'online' ? 'En línea' : 'Presencial' ?></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ev['fecha_inicio']))) ?></td>
        <td><?= (int) $ev['gratuito'] === 1 ? 'Gratis' : '$' . number_format((float) $ev['precio'], 2) ?></td>
        <td><a href="evento_inscritos.php?evento_id=<?= (int) $ev['id'] ?>"><?= (int) $ev['total_inscritos'] ?> ver</a></td>
        <td><a href="lecciones.php?evento_id=<?= (int) $ev['id'] ?>"><?= (int) $ev['total_lecciones'] ?> gestionar</a></td>
        <td><?= (int) $ev['activo'] === 1 ? 'Publicado' : 'Oculto' ?></td>
        <td class="d-flex gap-2 flex-wrap">
          <a href="contenido_form.php?tipo=evento&id=<?= (int) $ev['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
            <input type="hidden" name="accion" value="toggle_activo">
            <button class="btn btn-sm btn-outline-secondary"><?= (int) $ev['activo'] === 1 ? 'Ocultar' : 'Publicar' ?></button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Convertir esto a curso? El evento se ocultará (no se borra) y se creará un curso nuevo con estos datos, que podrás terminar de ajustar.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
            <input type="hidden" name="accion" value="convertir_a_curso">
            <button class="btn btn-sm btn-outline-dark">🎓 Convertir a curso</button>
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
</div>
<?php include __DIR__ . '/_footer.php'; ?>
