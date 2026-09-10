<?php
// Visibilidad completa de Prestaciones y regalos — quién da, quién recibe,
// cuándo y qué, tal como lo pide el checklist. Todo el estado se lee tal
// cual de `regalos` (backend/regalos.php), nunca se recalcula aquí.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'revocar') {
    $esAjax = es_peticion_ajax();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE regalos SET estado = 'revocado' WHERE id = ? AND estado <> 'revocado'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) {
        echo json_encode(['success' => true, 'mensaje' => 'Regalo revocado.', 'estado_html' => '<span class="badge bg-dark">Revocado</span>', 'quitar_grupo' => true]);
        exit;
    }
    header('Location: regalos.php');
    exit;
}

$regalos = $conn->query(
    "SELECT r.*, c.descuento_pct, c.curso_id, c.evento_id,
            cu.titulo AS curso_titulo, cu.slug AS curso_slug, ev.titulo AS evento_titulo, ev.slug AS evento_slug,
            uda.username_cache AS da_username, urec.username_cache AS recibe_username
     FROM regalos r
     JOIN regalo_configuracion c ON c.id = r.configuracion_id
     LEFT JOIN cursos cu ON cu.id = c.curso_id
     LEFT JOIN eventos ev ON ev.id = c.evento_id
     JOIN usuarios_perfil uda ON uda.id = r.usuario_da_id
     LEFT JOIN usuarios_perfil urec ON urec.id = r.usuario_recibe_id
     ORDER BY r.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$estadoLabel = [
    'disponible' => '<span class="badge bg-secondary">Disponible</span>',
    'reclamado' => '<span class="badge" style="background:#f7931e;">Reclamado</span>',
    'aceptado' => '<span class="badge bg-success">Aceptado</span>',
    'revocado' => '<span class="badge bg-dark">Revocado</span>',
];

$pageTitle = 'Regalos';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">🎁 Prestaciones y regalos</h1>
<p class="text-muted small">
  Cada fila es un enlace de regalo generado por un usuario. Configura qué cursos/eventos se pueden regalar desde
  su propio formulario de edición (sección "Prestaciones y regalos").
</p>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Contenido</th><th>Descuento</th><th>Quién regala</th><th>Quién recibe</th><th>Estado</th><th>Generado</th><th>Aceptado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($regalos as $r): ?>
      <tr>
        <td>
          <?php if ($r['curso_titulo']): ?>
            <a href="../../index.php?action=curso&slug=<?= urlencode($r['curso_slug']) ?>" target="_blank"><?= htmlspecialchars($r['curso_titulo']) ?></a>
          <?php elseif ($r['evento_titulo']): ?>
            <a href="../../index.php?action=evento&slug=<?= urlencode($r['evento_slug']) ?>" target="_blank"><?= htmlspecialchars($r['evento_titulo']) ?></a>
          <?php endif; ?>
        </td>
        <td><?= (float) $r['descuento_pct'] >= 100 ? 'Acceso completo' : ((float) $r['descuento_pct']) . '%' ?></td>
        <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $r['usuario_da_id'] ?>" target="_blank"><?= htmlspecialchars($r['da_username']) ?></a></td>
        <td><?php if ($r['usuario_recibe_id']): ?><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $r['usuario_recibe_id'] ?>" target="_blank"><?= htmlspecialchars((string) $r['recibe_username']) ?></a><?php else: ?>—<?php endif; ?></td>
        <td data-ajax-estado><?= $estadoLabel[$r['estado']] ?? htmlspecialchars($r['estado']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
        <td><?= $r['aceptado_en'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['aceptado_en']))) : '—' ?></td>
        <td>
          <?php if ($r['estado'] !== 'revocado'): ?>
            <form method="post" data-ajax="eliminar" data-confirm="¿Revocar este regalo? Ya no se podrá reclamar ni usar.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="accion" value="revocar">
              <button class="btn btn-sm btn-outline-danger">Revocar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$regalos): ?><tr><td colspan="8" class="text-muted">Nadie ha generado un regalo todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
