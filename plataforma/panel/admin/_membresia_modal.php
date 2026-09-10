<?php
// Editor único de suscripción de membresía (alta manual, confirmar
// transferencia, o editar una ya activa) — en modal, compartido entre
// usuarios.php y membresias.php para no tener el mismo formulario duplicado
// en dos pantallas. Espera $listaMembresiasModal (array de ['id','nombre'])
// y, opcionalmente, $listaUsuariosModal (array de ['id','username_cache',
// 'email_cache']) cuando la pantalla permite elegir cualquier usuario
// (membresias.php); en usuarios.php el usuario siempre viene fijo por fila,
// así que no hace falta.
$listaUsuariosModal = $listaUsuariosModal ?? [];
?>
<div class="modal fade" id="membresiaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="membresia_suscripcion_guardar.php" class="modal-content" id="membresiaModalForm" data-ajax-form>
      <?= csrf_field() ?>
      <input type="hidden" name="suscripcion_id" id="mmSuscripcionId" value="0">
      <input type="hidden" name="usuario_id" id="mmUsuarioId" value="">
      <input type="hidden" name="volver" id="mmVolver" value="membresias.php">
      <input type="hidden" name="volver_query" id="mmVolverQuery" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="mmTitulo">Membresía</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3" id="mmUsuarioWrap">
          <label class="form-label">Usuario</label>
          <input type="text" class="form-control" id="mmUsuarioBusca" list="mmListaUsuarios" placeholder="Busca por usuario o correo" autocomplete="off">
          <datalist id="mmListaUsuarios">
            <?php foreach ($listaUsuariosModal as $u): ?>
              <option value="<?= htmlspecialchars($u['username_cache'] . ' — ' . $u['email_cache']) ?>" data-id="<?= (int) $u['id'] ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="mb-3">
          <label class="form-label">Membresía</label>
          <select class="form-select" name="membresia_id" id="mmMembresiaId" required>
            <?php foreach ($listaMembresiasModal as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Método</label>
            <select class="form-select" name="metodo" id="mmMetodo">
              <option value="manual">Manual (cortesía, efectivo, etc.)</option>
              <option value="transferencia">Transferencia</option>
              <option value="stripe">Stripe (vincular suscripción existente)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Fecha de inicio</label>
            <input type="date" class="form-control" name="fecha_inicio" id="mmFechaInicio" required>
          </div>
        </div>
        <div class="row g-3 mt-0" id="mmStripeWrap" style="display:none;">
          <div class="col-md-6">
            <label class="form-label">Stripe Customer ID</label>
            <input type="text" class="form-control" name="stripe_customer_id" id="mmStripeCustomerId" placeholder="cus_...">
          </div>
          <div class="col-md-6">
            <label class="form-label">Stripe Subscription ID</label>
            <input type="text" class="form-control" name="stripe_subscription_id" id="mmStripeSubscriptionId" placeholder="sub_...">
          </div>
        </div>
        <div class="form-check form-switch mt-3">
          <input type="checkbox" class="form-check-input" role="switch" name="caduca" id="mmCaduca" checked>
          <label class="form-check-label" for="mmCaduca">Caduca (si se desmarca, la membresía no vence)</label>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" role="switch" name="renovacion_automatica" id="mmRenovacion">
          <label class="form-check-label" for="mmRenovacion">Se renueva automáticamente</label>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" role="switch" name="notificar" id="mmNotificar" checked>
          <label class="form-check-label" for="mmNotificar">Notificar por correo</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script>
function abrirMembresiaModal(opts) {
  opts = opts || {};
  document.getElementById('mmSuscripcionId').value = opts.suscripcionId || 0;
  document.getElementById('mmUsuarioId').value = opts.usuarioId || '';
  document.getElementById('mmVolver').value = opts.volver || 'membresias.php';
  document.getElementById('mmVolverQuery').value = opts.volverQuery || '';

  var buscaUsuario = document.getElementById('mmUsuarioBusca');
  var wrapUsuario = document.getElementById('mmUsuarioWrap');
  if (opts.usuarioId) {
    buscaUsuario.value = opts.usuarioLabel || '';
    buscaUsuario.readOnly = true;
    wrapUsuario.style.display = '';
  } else {
    buscaUsuario.value = '';
    buscaUsuario.readOnly = false;
    wrapUsuario.style.display = document.getElementById('mmListaUsuarios').children.length ? '' : 'none';
  }

  if (opts.membresiaId) document.getElementById('mmMembresiaId').value = opts.membresiaId;
  document.getElementById('mmMetodo').value = opts.metodo || 'manual';
  document.getElementById('mmFechaInicio').value = opts.fechaInicio || new Date().toISOString().slice(0, 10);
  document.getElementById('mmCaduca').checked = opts.caduca !== false;
  document.getElementById('mmRenovacion').checked = !!opts.renovacionAutomatica;
  document.getElementById('mmNotificar').checked = opts.notificar !== false;
  document.getElementById('mmTitulo').textContent = opts.titulo || 'Membresía';
  document.getElementById('mmStripeCustomerId').value = opts.stripeCustomerId || '';
  document.getElementById('mmStripeSubscriptionId').value = opts.stripeSubscriptionId || '';
  document.getElementById('mmStripeWrap').style.display = document.getElementById('mmMetodo').value === 'stripe' ? '' : 'none';

  new bootstrap.Modal(document.getElementById('membresiaModal')).show();
}

document.getElementById('mmMetodo').addEventListener('change', function () {
  document.getElementById('mmStripeWrap').style.display = this.value === 'stripe' ? '' : 'none';
});

document.getElementById('mmUsuarioBusca').addEventListener('input', function () {
  if (this.readOnly) return;
  var opcion = this.list.querySelector('option[value="' + CSS.escape(this.value) + '"]');
  document.getElementById('mmUsuarioId').value = opcion ? opcion.dataset.id : '';
});

document.getElementById('membresiaModalForm').addEventListener('submit', function (e) {
  if (!document.getElementById('mmUsuarioId').value) {
    e.preventDefault();
    alert('Elige un usuario de la lista.');
  }
});
</script>
