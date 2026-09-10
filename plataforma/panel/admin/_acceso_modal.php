<?php
// Editor de otorgamiento manual de acceso a un curso o evento — en modal,
// compartido desde usuarios.php (mismo patrón que _membresia_modal.php).
// Espera $listaCursosModal (array de ['id','titulo']) y $listaEventosModal
// (array de ['id','titulo']) ya definidos por quien lo incluya.
$listaCursosModal = $listaCursosModal ?? [];
$listaEventosModal = $listaEventosModal ?? [];
?>
<div class="modal fade" id="accesoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="acceso_contenido_guardar.php" class="modal-content" id="accesoModalForm" data-ajax-form>
      <?= csrf_field() ?>
      <input type="hidden" name="usuario_id" id="amUsuarioId" value="">
      <input type="hidden" name="tipo" id="amTipo" value="">
      <input type="hidden" name="item_id" id="amItemId" value="">
      <input type="hidden" name="volver" id="amVolver" value="usuarios.php">
      <input type="hidden" name="volver_query" id="amVolverQuery" value="">
      <div class="modal-header">
        <h5 class="modal-title">Otorgar acceso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small" id="amUsuarioLabel"></p>
        <div class="mb-3">
          <label class="form-label">Curso o evento</label>
          <input type="text" class="form-control" id="amBusca" list="amListaContenido" placeholder="Busca por título" autocomplete="off">
          <datalist id="amListaContenido">
            <?php foreach ($listaCursosModal as $c): ?>
              <option value="Curso: <?= htmlspecialchars($c['titulo']) ?>" data-tipo="curso" data-id="<?= (int) $c['id'] ?>"></option>
            <?php endforeach; ?>
            <?php foreach ($listaEventosModal as $e): ?>
              <option value="Evento: <?= htmlspecialchars($e['titulo']) ?>" data-tipo="evento" data-id="<?= (int) $e['id'] ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" role="switch" name="notificar" id="amNotificar" checked>
          <label class="form-check-label" for="amNotificar">Notificar por correo</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Otorgar acceso</button>
      </div>
    </form>
  </div>
</div>
<script>
function abrirAccesoModal(opts) {
  opts = opts || {};
  document.getElementById('amUsuarioId').value = opts.usuarioId || '';
  document.getElementById('amUsuarioLabel').textContent = opts.usuarioLabel || '';
  document.getElementById('amVolver').value = opts.volver || 'usuarios.php';
  document.getElementById('amVolverQuery').value = opts.volverQuery || '';
  document.getElementById('amTipo').value = '';
  document.getElementById('amItemId').value = '';
  document.getElementById('amBusca').value = '';
  document.getElementById('amNotificar').checked = true;

  new bootstrap.Modal(document.getElementById('accesoModal')).show();
}

document.getElementById('amBusca').addEventListener('input', function () {
  var opcion = this.list.querySelector('option[value="' + CSS.escape(this.value) + '"]');
  document.getElementById('amTipo').value = opcion ? opcion.dataset.tipo : '';
  document.getElementById('amItemId').value = opcion ? opcion.dataset.id : '';
});

document.getElementById('accesoModalForm').addEventListener('submit', function (e) {
  if (!document.getElementById('amTipo').value || !document.getElementById('amItemId').value) {
    e.preventDefault();
    alert('Elige un curso o evento de la lista.');
  }
});
</script>
