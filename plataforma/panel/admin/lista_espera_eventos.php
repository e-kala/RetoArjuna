<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/_filtro_tipo_usuario.php';
require_role('admin');
requerir_csrf_form();

// Quitar de la lista: para cuando alguien pide que lo den de baja a mano,
// o para limpiar tras notificar manualmente sin pasar por el flag
// "notificado" (ver evento_lista_espera.notificado, hoy sin lector propio
// — se deja preparado para cuando exista el aviso automático de "ya hay
// fecha").
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'quitar') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM evento_lista_espera WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    if (es_peticion_ajax()) {
        echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Quitado de la lista de espera.']);
        exit;
    }
    header('Location: lista_espera_eventos.php');
    exit;
}

$tipoUsuario = pf_tipo_usuario_actual();
$filtroTipoUsuarioSql = pf_filtro_tipo_usuario_sql($tipoUsuario, 'u');
$whereTipoUsuario = $filtroTipoUsuarioSql ? "WHERE {$filtroTipoUsuarioSql}" : '';

$inscritos = $conn->query(
    "SELECT le.id, le.notificado, le.created_at, u.username_cache, u.email_cache
     FROM evento_lista_espera le
     JOIN usuarios_perfil u ON u.id = le.usuario_id
     {$whereTipoUsuario}
     ORDER BY le.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Lista de espera de eventos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Lista de espera de eventos <span class="text-muted small">(<?= count($inscritos) ?> suscritos)</span></h1>
</div>
<p class="text-muted small">
  Personas que se suscribieron desde "Próximo evento" (<?= htmlspecialchars(BASE_URL) ?>/index.php?action=proximo_evento)
  para recibir aviso por correo en cuanto se agende el siguiente Reto Arjuna.
</p>
<?= pf_filtro_tipo_usuario_botones($tipoUsuario, 'lista_espera_eventos.php') ?>

<?php if (!$inscritos): ?>
  <button type="button" class="btn btn-sm btn-outline-secondary mb-3" disabled><i class="bi bi-envelope"></i> Copiar todos los correos</button>
<?php else: ?>
  <button type="button" id="btnCopiarCorreos" class="btn btn-sm btn-outline-secondary mb-3" data-correos="<?= htmlspecialchars(implode(', ', array_column($inscritos, 'email_cache')), ENT_QUOTES) ?>">
    <i class="bi bi-envelope"></i> Copiar todos los correos
  </button>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Correo</th><th>Suscrito el</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($inscritos as $i): ?>
      <tr>
        <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $i['id'] ?>" target="_blank"><?= htmlspecialchars((string) $i['username_cache']) ?></a></td>
        <td><?= htmlspecialchars((string) $i['email_cache']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($i['created_at']))) ?></td>
        <td>
          <form method="post" data-ajax="eliminar" data-confirm="¿Quitar a <?= htmlspecialchars((string) $i['username_cache'], ENT_QUOTES) ?> de la lista de espera?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
            <input type="hidden" name="accion" value="quitar">
            <button class="btn btn-sm btn-outline-danger">Quitar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$inscritos): ?><tr><td colspan="4" class="text-muted">Nadie se ha suscrito todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<script>
  var btnCopiar = document.getElementById('btnCopiarCorreos');
  if (btnCopiar) {
    btnCopiar.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(this.dataset.correos);
        $.notify('Correos copiados al portapapeles.', { className: 'success', position: 'top right', autoHideDelay: 2500 });
      } catch (e) {
        $.notify('No se pudo copiar.', { className: 'error', position: 'top right', autoHideDelay: 3000 });
      }
    });
  }
</script>
<?php include __DIR__ . '/_footer.php'; ?>
