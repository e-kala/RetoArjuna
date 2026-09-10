<?php
if (is_logged_in()) {
    header('Location: ' . redirect_post_login_con_aviso_sesion());
    exit;
}
$token = (string) ($_GET['token'] ?? '');
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
          <?php if ($token === ''): ?>
            <h1 class="h3 fw-bold text-center mb-3" style="color:var(--pf-ink);">Enlace inválido</h1>
            <p class="text-muted text-center mb-4">Este enlace no incluye el token necesario. Solicita uno nuevo.</p>
            <a href="?action=olvide_contrasena" class="btn btn-lg w-100 fw-bold" style="background:#F6C500;color:#171717;">Solicitar enlace</a>
          <?php else: ?>
            <h1 class="h3 fw-bold text-center mb-1" style="color:var(--pf-ink);">Nueva contraseña</h1>
            <p class="text-muted text-center mb-4">Escribe tu nueva contraseña.</p>

            <form id="formularioRestablecer">
              <input type="hidden" id="inputToken" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
              <div id="restablecerAlerta" class="alert d-none"></div>

              <div class="form-floating mb-3">
                <input type="password" id="inputPasswordNueva" class="form-control" placeholder="Nueva contraseña" required>
                <label for="inputPasswordNueva">Nueva contraseña</label>
              </div>
              <div class="form-floating mb-4">
                <input type="password" id="inputPasswordNuevaConfirm" class="form-control" placeholder="Confirma la contraseña" required>
                <label for="inputPasswordNuevaConfirm">Confirma la contraseña</label>
              </div>

              <button type="submit" class="btn btn-lg w-100 fw-bold" style="background:#F6C500;color:#171717;">Restablecer contraseña</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($token !== ''): ?>
<script>
  $(function () {
    $('#formularioRestablecer').on('submit', function (event) {
      event.preventDefault();
      const $alerta = $('#restablecerAlerta').addClass('d-none');
      const $boton = $(this).find('button[type=submit]').prop('disabled', true);

      $.ajax({
        url: 'backend/restablecer_contrasena_back.php',
        type: 'POST',
        data: {
          token: $('#inputToken').val(),
          password: $('#inputPasswordNueva').val(),
          password_confirm: $('#inputPasswordNuevaConfirm').val(),
          csrf_token: <?= json_encode(csrf_token()) ?>
        },
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            $alerta.removeClass('d-none alert-danger').addClass('alert-success').text('Contraseña actualizada. Iniciando sesión…');
            setTimeout(function () { window.location.href = response.redirect || '?action=ingreso'; }, 1200);
          } else {
            $alerta.removeClass('d-none alert-success').addClass('alert-danger').text(response.message || 'No se pudo actualizar la contraseña.');
            $boton.prop('disabled', false);
          }
        },
        error: function () {
          $alerta.removeClass('d-none alert-success').addClass('alert-danger').text('Error de conexión. Intenta de nuevo.');
          $boton.prop('disabled', false);
        }
      });
    });
  });
</script>
<?php endif; ?>
