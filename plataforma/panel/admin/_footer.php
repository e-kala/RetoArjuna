  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
  <script>
  // Envía por Ajax cualquier <form data-ajax> del admin (fijar/publicar/
  // aprobar/eliminar/confirmar pago, etc.) en vez de recargar la página
  // completa — el backend detecta la petición Ajax (es_peticion_ajax() en
  // auth.php, se activa solo con el header que jQuery ya manda) y responde
  // JSON con este contrato en vez de header('Location:'):
  //   { success:true, mensaje:'...', eliminado:true }
  //     — quita la fila completa (fade + remove). Para "eliminar".
  //   { success:true, mensaje:'...', boton_texto:'...', boton_accion:'...', estado_html:'...' }
  //     — actualiza el texto/accion del propio botón (útil para toggles de 2
  //       estados), y si la fila tiene [data-ajax-estado], también su
  //       contenido (columna "Estado").
  //   { success:true, mensaje:'...', estado_html:'...', quitar_grupo:true }
  //     — actualiza [data-ajax-estado] y quita SOLO este <form> (no toda la
  //       fila) — para acciones tipo "Confirmar/Rechazar pago" donde el
  //       grupo de botones ya no debe volver a aparecer, pero el resto de la
  //       fila (usuario, monto, etc.) sigue ahí.
  //   { success:true, mensaje:'...', fila_html:'<tr>...</tr>' }
  //     — reemplaza la <tr> completa (server-side, misma función de render
  //       que el listado) — para filas con muchas acciones/columnas que se
  //       afectan entre sí (ej. usuarios.php: cambiar rol, membresía, etc.)
  //       donde armar el parche a mano en JS sería más frágil que re-pintar.
  //   { success:false, mensaje:'...' }   — solo avisa, no cambia nada.
  // data-confirm="texto" en el <form> reemplaza el onsubmit=confirm(...) de
  // antes. Si el form tiene más de un botón submit con [name]/[value]
  // distintos (ej. Confirmar/Rechazar), se manda el que de verdad se clicó
  // — igual que haría un submit nativo del navegador.
  // toast_opciones (opcional) sobreescribe las opciones de $.notify — por
  // ejemplo autoHide:false para un mensaje que el admin debe copiar antes de
  // que desaparezca (contraseña temporal).
  //
  // Switches (Bootstrap "form-switch", ver getbootstrap.com/docs/5.3/forms/
  // checks-radios/#switches): un <input type=checkbox role=switch> dentro de
  // un form[data-ajax] envía el form solo con cambiar de estado (sin botón
  // aparte) — reemplazan los toggles de 2 estados que antes eran un <button>
  // con texto "Publicar"/"Ocultar". El checkbox no lleva [name] a propósito
  // (no debe ir en $form.serialize(); el estado real lo maneja el backend
  // vía el campo accion=toggle_activo ya existente). Si la petición falla,
  // se revierte visualmente al estado anterior — si no, el switch quedaría
  // mostrando un cambio que en realidad no se guardó.
  $(document).on('change', 'form[data-ajax] input[type="checkbox"][role="switch"]', function () {
    $(this).closest('form').trigger('submit');
  });

  $(document).on('click', 'form[data-ajax] button', function () {
    $(this).closest('form').data('ajaxBotonClic', $(this));
  });

  function pfRevertirSwitch($form) {
    $form.find('input[type="checkbox"][role="switch"]').prop('checked', function (i, val) { return !val; });
  }

  $(document).on('submit', 'form[data-ajax]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var confirmMsg = $form.data('confirm');
    if (confirmMsg && !window.confirm(confirmMsg)) return;

    var $botonClic = $form.data('ajaxBotonClic') || $form.find('button').first();
    var datos = $form.serialize();
    var nombreBoton = $botonClic.attr('name');
    if (nombreBoton) {
      datos += '&' + encodeURIComponent(nombreBoton) + '=' + encodeURIComponent($botonClic.val());
    }
    $form.find('button, input[type="checkbox"][role="switch"]').prop('disabled', true);

    $.ajax({
      url: $form.attr('action') || window.location.href,
      method: 'POST',
      data: datos,
      dataType: 'json'
    }).done(function (data) {
      if (!data.success) {
        $.notify(data.mensaje || 'No se pudo completar la acción.', 'error');
        pfRevertirSwitch($form);
        $form.find('button, input[type="checkbox"][role="switch"]').prop('disabled', false);
        return;
      }
      if (data.eliminado) {
        $form.closest('tr').fadeOut(200, function () { $(this).remove(); });
      } else if (typeof data.fila_html !== 'undefined') {
        $form.closest('tr').replaceWith(data.fila_html);
      } else {
        if (typeof data.estado_html !== 'undefined') {
          $form.closest('tr').find('[data-ajax-estado]').html(data.estado_html);
        }
        if (data.quitar_grupo) {
          $form.fadeOut(150, function () { $(this).remove(); });
        } else {
          if (typeof data.boton_texto !== 'undefined') {
            $botonClic.text(data.boton_texto);
            $form.find('input[name="accion"]').val(data.boton_accion);
          }
          $form.find('button, input[type="checkbox"][role="switch"]').prop('disabled', false);
        }
      }
      if (data.mensaje) {
        $.notify(data.mensaje, $.extend({ className: 'success', position: 'top right', autoHideDelay: 3000 }, data.toast_opciones || {}));
      }
    }).fail(function () {
      $.notify('Error de conexión. Intenta de nuevo.', 'error');
      pfRevertirSwitch($form);
      $form.find('button, input[type="checkbox"][role="switch"]').prop('disabled', false);
    });
  });

  // Formularios multi-campo de crear/editar (contenido, producto, usuario,
  // etc.), algunos con <input type=file> — se envían por Ajax vía FormData
  // (soporta archivos) para que el POST no navegue a otra página; al
  // guardar con éxito SÍ se navega (data.redirect), igual que hacía el
  // header('Location:') de antes — nada más queda "sin recarga" del todo
  // porque estos forms son de un solo uso (crear/editar y volver al
  // listado), no una acción repetible en una fila. En error se muestra el
  // mensaje sin recargar, así que lo ya escrito no se pierde.
  $(document).on('submit', 'form[data-ajax-form]', function (e) {
    // Si el propio <form> ya tiene su validación inline (ej. _acceso_modal.php
    // exige elegir algo de una lista antes de dejar enviar), esa corre primero
    // (está pegada al form, no a document) y hace preventDefault — hay que
    // respetarla y no mandar la petición igual.
    if (e.isDefaultPrevented()) return;
    e.preventDefault();
    var $form = $(this);
    var confirmMsg = $form.data('confirm');
    if (confirmMsg && !window.confirm(confirmMsg)) return;
    var $botones = $form.find('button');
    var textoOriginal = $botones.first().text();
    $botones.prop('disabled', true);

    $.ajax({
      url: $form.attr('action') || window.location.href,
      method: 'POST',
      data: new FormData(this),
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function (data) {
      if (data.success) {
        if (data.redirect) {
          window.location.href = data.redirect;
        } else {
          window.location.reload();
        }
        return;
      }
      $.notify(data.mensaje || 'No se pudo guardar.', 'error');
      $botones.prop('disabled', false).first().text(textoOriginal);
    }).fail(function () {
      $.notify('Error de conexión. Intenta de nuevo.', 'error');
      $botones.prop('disabled', false).first().text(textoOriginal);
    });
  });
  </script>
  <script src="../../content/session_watch.js" data-check-url="../../backend/session_check.php"></script>
</body>
</html>
