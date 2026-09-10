<?php
if (is_logged_in()) {
    header('Location: ' . redirect_post_login_con_aviso_sesion());
    exit;
}
?>
<div class="container" style="margin-top: 143px !important; margin-bottom: 60px;">
  <div class="row justify-content-center">
    <div class="col-sm-8 col-md-6 col-lg-5">
      <div class="text-center mb-4">
        <img src="digital-creative/img/logo.png" alt="Reto Arjuna" style="height:44px;">
      </div>
      <div class="card border-0 shadow" style="border-radius:18px;overflow:hidden;">
        <div style="height:6px;background:linear-gradient(90deg,#F6C500,#B8860B);"></div>
        <div class="card-body p-4 p-md-5">
          <h1 class="h3 fw-bold text-center mb-1" style="color:var(--pf-ink);">¿Olvidaste tu contraseña?</h1>
          <p class="text-muted text-center mb-4">Escribe tu correo y te mandamos un enlace para restablecerla.</p>

          <form id="formularioOlvide">
            <div id="olvideAlerta" class="alert d-none"></div>

            <div class="form-floating mb-4">
              <input type="email" id="inputEmailOlvide" class="form-control" placeholder="Correo" required>
              <label for="inputEmailOlvide">Correo</label>
            </div>

            <button type="submit" class="btn btn-lg w-100 fw-bold" style="background:#F6C500;color:#171717;">Enviar enlace</button>
          </form>

          <p class="text-center text-muted mt-4 mb-0">
            <a href="?action=ingreso" class="fw-bold text-decoration-none" style="color:#B8860B;">Volver a iniciar sesión</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  $(function () {
    $('#formularioOlvide').on('submit', function (event) {
      event.preventDefault();
      const $alerta = $('#olvideAlerta').addClass('d-none');
      const $boton = $(this).find('button[type=submit]').prop('disabled', true);

      $.ajax({
        url: 'backend/olvide_contrasena_solicitar.php',
        type: 'POST',
        data: {
          email: $('#inputEmailOlvide').val(),
          csrf_token: <?= json_encode(csrf_token()) ?>
        },
        dataType: 'json',
        success: function (response) {
          $alerta.removeClass('d-none alert-danger alert-success')
            .addClass(response.success ? 'alert-success' : 'alert-danger')
            .text(response.message || 'Listo.');
          if (response.success) {
            $('#formularioOlvide')[0].reset();
          }
        },
        error: function () {
          $alerta.removeClass('d-none alert-success').addClass('alert-danger').text('Error de conexión. Intenta de nuevo.');
        },
        complete: function () {
          $boton.prop('disabled', false);
        }
      });
    });
  });
</script>
