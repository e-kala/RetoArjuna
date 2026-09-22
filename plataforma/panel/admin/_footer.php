  </div>

  <!-- Modal de zoom para comprobantes de pago (transferencia/ventanilla/membresía).
       Un solo modal compartido por toda página de panel/admin/ — cualquier
       <a data-comprobante-zoom href="ruta/a/la/imagen"> lo abre en vez de
       navegar (ver listener al final del script de abajo). El href normal se
       conserva como respaldo (abrir en pestaña nueva, copiar enlace, etc.) —
       el modal es solo progressive enhancement sobre el link que ya existía. -->
  <div class="modal fade" id="comprobanteZoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content bg-dark">
        <div class="modal-header border-0">
          <h5 class="modal-title text-white">Comprobante</h5>
          <div class="d-flex gap-2 ms-auto me-2">
            <button type="button" class="btn btn-sm btn-outline-light" id="comprobanteZoomOut" title="Alejar"><i class="bi bi-zoom-out"></i></button>
            <button type="button" class="btn btn-sm btn-outline-light" id="comprobanteZoomReset" title="Restablecer">100%</button>
            <button type="button" class="btn btn-sm btn-outline-light" id="comprobanteZoomIn" title="Acercar"><i class="bi bi-zoom-in"></i></button>
            <a href="#" target="_blank" class="btn btn-sm btn-outline-light" id="comprobanteZoomAbrir" title="Abrir en pestaña nueva"><i class="bi bi-box-arrow-up-right"></i></a>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body p-0 pf-comprobante-zoom-viewport" id="comprobanteZoomViewport">
          <img id="comprobanteZoomImg" src="" alt="Comprobante de pago" draggable="false">
        </div>
      </div>
    </div>
  </div>
  <style>
    .pf-comprobante-zoom-viewport {
      overflow: hidden;
      height: 78vh;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: grab;
      touch-action: none;
    }
    .pf-comprobante-zoom-viewport.pf-arrastrando { cursor: grabbing; }
    #comprobanteZoomImg {
      max-width: 100%;
      max-height: 100%;
      user-select: none;
      transition: transform 0.08s ease-out;
      will-change: transform;
    }
  </style>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
  <script>
  // --- Zoom de comprobante (ver modal arriba) ---
  (function () {
    var modalEl = document.getElementById('comprobanteZoomModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    var viewport = document.getElementById('comprobanteZoomViewport');
    var img = document.getElementById('comprobanteZoomImg');
    var btnIn = document.getElementById('comprobanteZoomIn');
    var btnOut = document.getElementById('comprobanteZoomOut');
    var btnReset = document.getElementById('comprobanteZoomReset');
    var linkAbrir = document.getElementById('comprobanteZoomAbrir');

    var escala = 1, offsetX = 0, offsetY = 0;
    var arrastrando = false, ultimoX = 0, ultimoY = 0;

    function aplicarTransform() {
      img.style.transform = 'translate(' + offsetX + 'px, ' + offsetY + 'px) scale(' + escala + ')';
      btnReset.textContent = Math.round(escala * 100) + '%';
    }

    function fijarEscala(nueva) {
      escala = Math.min(6, Math.max(1, nueva));
      if (escala === 1) { offsetX = 0; offsetY = 0; } // vuelve a centrar al llegar al mínimo
      aplicarTransform();
    }

    // Solo las imágenes con [data-comprobante-zoom] abren el modal — los
    // demás enlaces de la fila (usuario, artículo, etc.) siguen navegando
    // normal. Delegado en document porque las filas se repintan por Ajax
    // (ver data-ajax="accion" en _footer.php) y unos <a> nuevos no tendrían
    // el listener si se atara directo al elemento.
    document.addEventListener('click', function (e) {
      var link = e.target.closest('[data-comprobante-zoom]');
      if (!link) return;
      var href = link.getAttribute('href');
      // Un comprobante puede ser PDF (procesar_subida_comprobante() acepta
      // JPG/PNG/WEBP/PDF) — el visor de zoom es solo para imágenes, un PDF
      // sigue su comportamiento normal (abrir en pestaña nueva).
      if (/\.pdf($|\?)/i.test(href)) return;
      e.preventDefault();
      escala = 1; offsetX = 0; offsetY = 0;
      img.src = href;
      linkAbrir.setAttribute('href', href);
      aplicarTransform();
      modal.show();
    });

    btnIn.addEventListener('click', function () { fijarEscala(escala + 0.5); });
    btnOut.addEventListener('click', function () { fijarEscala(escala - 0.5); });
    btnReset.addEventListener('click', function () { fijarEscala(1); });

    viewport.addEventListener('wheel', function (e) {
      e.preventDefault();
      fijarEscala(escala + (e.deltaY < 0 ? 0.25 : -0.25));
    }, { passive: false });

    // Doble click/tap: alterna entre 100% y 2.5x, como cualquier visor de imágenes.
    viewport.addEventListener('dblclick', function () {
      fijarEscala(escala > 1 ? 1 : 2.5);
    });

    viewport.addEventListener('mousedown', function (e) {
      if (escala <= 1) return;
      arrastrando = true;
      ultimoX = e.clientX; ultimoY = e.clientY;
      viewport.classList.add('pf-arrastrando');
    });
    window.addEventListener('mousemove', function (e) {
      if (!arrastrando) return;
      offsetX += e.clientX - ultimoX;
      offsetY += e.clientY - ultimoY;
      ultimoX = e.clientX; ultimoY = e.clientY;
      aplicarTransform();
    });
    window.addEventListener('mouseup', function () {
      arrastrando = false;
      viewport.classList.remove('pf-arrastrando');
    });

    // Pellizcar para zoom / arrastrar con un dedo en móvil.
    var distanciaPellizco = null;
    viewport.addEventListener('touchstart', function (e) {
      if (e.touches.length === 2) {
        distanciaPellizco = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
      } else if (e.touches.length === 1 && escala > 1) {
        arrastrando = true;
        ultimoX = e.touches[0].clientX; ultimoY = e.touches[0].clientY;
      }
    }, { passive: true });
    viewport.addEventListener('touchmove', function (e) {
      if (e.touches.length === 2 && distanciaPellizco !== null) {
        e.preventDefault();
        var actual = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        fijarEscala(escala * (actual / distanciaPellizco));
        distanciaPellizco = actual;
      } else if (arrastrando && e.touches.length === 1) {
        e.preventDefault();
        offsetX += e.touches[0].clientX - ultimoX;
        offsetY += e.touches[0].clientY - ultimoY;
        ultimoX = e.touches[0].clientX; ultimoY = e.touches[0].clientY;
        aplicarTransform();
      }
    }, { passive: false });
    viewport.addEventListener('touchend', function (e) {
      if (e.touches.length < 2) distanciaPellizco = null;
      if (e.touches.length === 0) arrastrando = false;
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      img.src = '';
    });
  })();
  </script>
  <script>
  // "Enlace directo" de cursos.php/eventos.php (ver _cursos_filas.php/
  // _eventos_filas.php) — copia al portapapeles la URL con ?ver=1, que salta
  // la landing de venta y va directo al detalle. Delegado en document
  // (mismo motivo que el zoom de comprobantes arriba): las filas se
  // repintan por Ajax al buscar, un listener atado directo al botón no
  // sobreviviría ese repintado.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-copiar-enlace-directo');
    if (!btn) return;
    var enlace = btn.dataset.enlace;
    navigator.clipboard.writeText(enlace).then(function () {
      if (window.jQuery && jQuery.notify) {
        jQuery.notify('Enlace copiado.', { className: 'success', position: 'top right', autoHideDelay: 2000 });
      }
    }).catch(function () {
      window.prompt('Copia el enlace manualmente:', enlace);
    });
  });

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
