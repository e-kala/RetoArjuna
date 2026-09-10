<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar_noticia') {
        $stmt = $conn->prepare('DELETE FROM noticias WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Noticia eliminada.']);
            exit;
        }
    } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
        $stmt = $conn->prepare('UPDATE noticias SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            $stmt = $conn->prepare('SELECT activo FROM noticias WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $activo = (int) ($stmt->get_result()->fetch_assoc()['activo'] ?? 0);
            $stmt->close();
            echo json_encode([
                'success' => true,
                'mensaje' => $activo ? 'Noticia publicada.' : 'Noticia oculta.',
                'boton_texto' => $activo ? 'Ocultar' : 'Publicar',
                'boton_accion' => 'toggle_activo',
                'estado_html' => $activo ? 'Publicada' : 'Oculta',
            ]);
            exit;
        }
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
        <td><a href="../../index.php?action=noticia&slug=<?= urlencode($n['slug']) ?>" target="_blank"><?= htmlspecialchars($n['titulo']) ?></a></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($n['publicada_at']))) ?></td>
        <td data-ajax-estado><?= (int) $n['activo'] === 1 ? 'Publicada' : 'Oculta' ?></td>
        <td class="d-flex gap-2">
          <a href="noticia_form.php?id=<?= (int) $n['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" data-ajax="toggle" class="d-flex align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="accion" value="toggle_activo"><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" role="switch" <?= (int) $n['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $n['activo'] === 1 ? 'Ocultar' : 'Publicar' ?>"></div></form>
          <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar noticia?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="accion" value="eliminar_noticia"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
