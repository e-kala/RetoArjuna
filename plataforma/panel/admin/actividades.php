<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_actividad') {
        $stmt = $conn->prepare('DELETE FROM actividades WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Actividad eliminada.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE actividades SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM actividades WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Actividad activada.' : 'Actividad oculta.',
                'boton_texto' => $activo ? 'Ocultar' : 'Mostrar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? 'Activa' : 'Oculta',
            ]);
            exit;
        }
    }
    header('Location: actividades.php');
    exit;
}

$actividades = $conn->query('SELECT * FROM actividades ORDER BY orden ASC, titulo ASC')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Actividades';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Actividades</h1>
  <a href="actividad_form.php" class="btn btn-success btn-sm">+ Nueva actividad</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Icono</th><th>Título</th><th>Orden</th><th>Creado</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($actividades as $a): ?>
      <tr>
        <td style="font-size:20px;"><?= htmlspecialchars($a['icono']) ?></td>
        <td><?= htmlspecialchars($a['titulo']) ?></td>
        <td><?= (int) $a['orden'] ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($a['created_at']))) ?></td>
        <td data-ajax-estado><?= (int) $a['activo'] === 1 ? 'Activa' : 'Oculta' ?></td>
        <td class="d-flex gap-2">
          <a href="actividad_form.php?id=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" data-ajax="toggle" class="d-flex align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" role="switch" <?= (int) $a['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $a['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?>"></div></form>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar actividad?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="accion" value="eliminar_actividad"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
