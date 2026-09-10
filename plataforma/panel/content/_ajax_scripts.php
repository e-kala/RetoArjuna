<script src="https://code.jquery.com/jquery-3.7.1.min.js"
  integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="../content/notify.min.js"></script>
<script>
// Igual que panel/admin/_footer.php — cualquier <form data-ajax="eliminar">
// se envía por Ajax en vez de recargar la página (usado hoy solo por "Mis
// compras": eliminar un registro de compra). El backend detecta la
// petición con es_peticion_ajax() y responde
// { success:true, eliminado:true, mensaje:'...' } para quitar la fila, o
// { success:false, mensaje:'...' } para solo avisar sin cambiar nada.
$(document).on('submit', 'form[data-ajax]', function (e) {
  e.preventDefault();
  var $form = $(this);
  var confirmMsg = $form.data('confirm');
  if (confirmMsg && !window.confirm(confirmMsg)) return;

  $form.find('button').prop('disabled', true);

  $.ajax({
    url: $form.attr('action') || window.location.href,
    method: 'POST',
    data: $form.serialize(),
    dataType: 'json'
  }).done(function (data) {
    if (!data.success) {
      $.notify(data.mensaje || 'No se pudo completar la acción.', 'error');
      $form.find('button').prop('disabled', false);
      return;
    }
    if (data.eliminado) {
      $form.closest('tr').fadeOut(200, function () { $(this).remove(); });
    } else {
      $form.find('button').prop('disabled', false);
    }
    if (data.mensaje) $.notify(data.mensaje, { className: 'success', position: 'top right', autoHideDelay: 3000 });
  }).fail(function () {
    $.notify('Error de conexión. Intenta de nuevo.', 'error');
    $form.find('button').prop('disabled', false);
  });
});
</script>
