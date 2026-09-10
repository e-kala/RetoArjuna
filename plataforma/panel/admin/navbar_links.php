<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_link') {
        $stmt = $conn->prepare('DELETE FROM navbar_links WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Link eliminado.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE navbar_links SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM navbar_links WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Link visible.' : 'Link oculto.',
                'boton_texto' => $activo ? 'Ocultar' : 'Mostrar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? 'Visible' : 'Oculto',
            ]);
            exit;
        }
    }
    header('Location: navbar_links.php');
    exit;
}

$links = $conn->query('SELECT * FROM navbar_links ORDER BY orden ASC, texto ASC')->fetch_all(MYSQLI_ASSOC);
$areaTexto = ['nav' => 'Solo menú', 'footer' => 'Solo pie de página', 'ambos' => 'Menú y pie'];

$pageTitle = 'Navbar';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h1 class="h4">Navbar y pie de página</h1>
  <a href="navbar_link_form.php" class="btn btn-success btn-sm">+ Nuevo link</a>
</div>
<p class="text-muted small">Estos links controlan el menú y el pie de página en todo el sitio (inicio, plataforma y foro) — un solo lugar para los tres.</p>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Orden</th><th>Texto</th><th>URL</th><th>Dónde aparece</th><th>Creado</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($links as $l): ?>
      <tr>
        <td><?= (int) $l['orden'] ?></td>
        <td><?= htmlspecialchars($l['texto']) ?></td>
        <td><code><?= htmlspecialchars($l['url']) ?></code><?= (int) $l['abre_nueva_pestana'] === 1 ? ' <span class="badge bg-info">nueva pestaña</span>' : '' ?><?= (int) $l['requiere_sesion'] === 1 ? ' <span class="badge bg-secondary">🔒 solo con sesión</span>' : '' ?></td>
        <td><?= $areaTexto[$l['area']] ?? $l['area'] ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($l['created_at']))) ?></td>
        <td data-ajax-estado><?= (int) $l['activo'] === 1 ? 'Visible' : 'Oculto' ?></td>
        <td class="d-flex gap-2">
          <a href="navbar_link_form.php?id=<?= (int) $l['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" data-ajax="toggle" class="d-flex align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" role="switch" <?= (int) $l['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $l['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?>"></div></form>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar este link?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="accion" value="eliminar_link"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$links): ?><tr><td colspan="7" class="text-muted">No hay links todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
