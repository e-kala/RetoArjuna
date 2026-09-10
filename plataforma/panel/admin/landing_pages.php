<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_landing') {
        $stmt = $conn->prepare('DELETE FROM landing_pages WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Landing page eliminada.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE landing_pages SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM landing_pages WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Landing page publicada.' : 'Landing page oculta.',
                'boton_texto' => $activo ? 'Ocultar' : 'Publicar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? '<span class="badge bg-success">Publicada</span>' : '<span class="badge bg-secondary">Oculta</span>',
            ]);
            exit;
        }
    }
    header('Location: landing_pages.php');
    exit;
}

$landings = $conn->query(
    "SELECT l.*, u.username_cache AS creado_por_username
     FROM landing_pages l LEFT JOIN usuarios_perfil u ON u.id = l.creado_por
     ORDER BY l.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Landing pages';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Landing pages</h1>
  <a href="landing_form.php" class="btn btn-success btn-sm">+ Subir landing page</a>
</div>
<p class="text-muted small">
  Sube un archivo .html con el diseño y copy de una landing de ventas — se muestra envuelta en el navbar y
  footer de siempre, con solo el contenido subido en medio. No hay editor visual: para cambiar el diseño,
  edita el archivo original y vuelve a subirlo.
</p>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Título</th><th>URL</th><th>Subida por</th><th>Creada</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($landings as $l): ?>
      <tr>
        <td><a href="../../index.php?action=landing&slug=<?= urlencode($l['slug']) ?>" target="_blank"><?= htmlspecialchars($l['titulo']) ?></a></td>
        <td><a href="../../index.php?action=landing&slug=<?= urlencode($l['slug']) ?>" target="_blank"><code>?action=landing&slug=<?= htmlspecialchars($l['slug']) ?></code></a></td>
        <td><?= htmlspecialchars((string) ($l['creado_por_username'] ?? '—')) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($l['created_at']))) ?></td>
        <td data-ajax-estado><?= (int) $l['activo'] === 1 ? '<span class="badge bg-success">Publicada</span>' : '<span class="badge bg-secondary">Oculta</span>' ?></td>
        <td class="d-flex gap-2">
          <a href="landing_form.php?id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" data-ajax="toggle" class="d-flex align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" role="switch" <?= (int) $l['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $l['activo'] === 1 ? 'Ocultar' : 'Publicar' ?>"></div></form>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar esta landing page?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="accion" value="eliminar_landing"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$landings): ?><tr><td colspan="6" class="text-muted">No hay landing pages todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
