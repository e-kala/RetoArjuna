<?php
if (is_logged_in()) {
    // Si ya hay sesión y de todos modos se llegó aquí con un volver (ej. un
    // CTA de landing que siempre enlaza a ingreso.php sin saber si ya está
    // logueado), hay que respetarlo — si no, este guard lo descarta antes de
    // que el resto de la página siquiera lo lea, y manda al panel en vez de
    // a la página de pago de la que venía.
    header('Location: ' . redirect_post_login_con_aviso_sesion((string) ($_GET['volver'] ?? '')));
    exit;
}
$volverActual = (string) ($_GET['volver'] ?? '');
$cuponActual = (string) ($_GET['cupon'] ?? ($_SESSION['cupon_pendiente'] ?? ''));
?>
<style>
  #loginForm .form-control:focus { border-color: #F6C500; box-shadow: 0 0 0 .25rem rgba(246,197,0,.25); }
</style>
<div class="container" style="margin-top: 143px !important; margin-bottom: 60px;">
  <div class="row justify-content-center">
    <div class="col-sm-8 col-md-6 col-lg-5">
      <div class="text-center mb-4">
        <img src="digital-creative/img/logo.png" alt="Reto Arjuna" style="height:44px;">
      </div>
      <div class="card border-0 shadow" style="border-radius:18px;overflow:hidden;">
        <div style="height:6px;background:linear-gradient(90deg,#F6C500,#B8860B);"></div>
        <div class="card-body p-4 p-md-5">
          <h1 class="h3 fw-bold text-center mb-1" style="color:var(--pf-ink);">Inicia sesión</h1>
          <p class="text-muted text-center mb-4">Accede a tu Cuenta Arjuna</p>

          <form id="loginForm">
            <input type="hidden" id="inputVolver" value="<?= htmlspecialchars($volverActual) ?>">
            <input type="hidden" id="inputCupon" value="<?= htmlspecialchars($cuponActual) ?>">
            <div id="loginError" class="alert alert-danger d-none"></div>

            <div class="form-floating mb-3">
              <input type="text" id="inputIdentification" class="form-control" placeholder="Usuario o correo" required>
              <label for="inputIdentification">Usuario o correo</label>
            </div>

            <div class="form-floating mb-2">
              <input type="password" id="inputPassword" class="form-control" placeholder="Contraseña" required>
              <label for="inputPassword">Contraseña</label>
            </div>
            <p class="text-end mb-4">
              <a href="?action=olvide_contrasena" class="small text-decoration-none" style="color:#B8860B;">¿Olvidaste tu contraseña?</a>
            </p>

            <button type="submit" class="btn btn-lg w-100 fw-bold" style="background:#F6C500;color:#171717;">Inicia sesión</button>
          </form>

          <?php if (config_esta_lista(GOOGLE_CLIENT_ID)): ?>
          <div class="d-flex align-items-center my-4" style="gap:12px;">
            <hr class="flex-grow-1">
            <span class="text-muted small">o continúa con</span>
            <hr class="flex-grow-1">
          </div>
          <div id="g_id_onload"
               data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
               data-callback="handleGoogleLogin"
               data-auto_prompt="false">
          </div>
          <div class="d-flex justify-content-center">
            <div class="g_id_signin" data-type="standard" data-width="300"></div>
          </div>
          <?php endif; ?>

          <p class="text-center text-muted mt-4 mb-0">¿Aún no tienes cuenta?
            <a href="?action=registro<?= $volverActual !== '' ? '&volver=' . urlencode($volverActual) : '' ?><?= $cuponActual !== '' ? '&cupon=' . urlencode($cuponActual) : '' ?>" class="fw-bold text-decoration-none" style="color:#B8860B;">Crea tu Cuenta Arjuna</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (config_esta_lista(GOOGLE_CLIENT_ID)): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
  function handleGoogleLogin(response) {
    $.ajax({
      url: 'backend/google_auth.php',
      type: 'POST',
      data: {
        id_token: response.credential,
        csrf_token: <?= json_encode(csrf_token()) ?>,
        volver: $('#inputVolver').val(),
        cupon: $('#inputCupon').val()
      },
      dataType: 'json',
      success: function (data) {
        if (data.success) {
          window.location.href = data.redirect || 'panel/index.php';
        } else {
          $('#loginError').text(data.message || 'No se pudo iniciar sesión con Google.').removeClass('d-none');
        }
      },
      error: function () {
        $('#loginError').text('Error de conexión con Google.').removeClass('d-none');
      }
    });
  }
</script>
<?php endif; ?>

<script>
  $(function () {
    $('#loginForm').on('submit', function (event) {
      event.preventDefault();
      const identification = $('#inputIdentification').val();
      const password = $('#inputPassword').val();
      const $error = $('#loginError').addClass('d-none');

      $.ajax({
        url: 'backend/ingreso_back.php',
        type: 'POST',
        data: {
          identification: identification,
          password: password,
          csrf_token: <?= json_encode(csrf_token()) ?>,
          volver: $('#inputVolver').val(),
          cupon: $('#inputCupon').val()
        },
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            window.location.href = response.redirect || 'panel/index.php';
          } else {
            $error.text(response.message || 'No se pudo iniciar sesión.').removeClass('d-none');
          }
        },
        error: function () {
          $error.text('Error de conexión. Intenta de nuevo.').removeClass('d-none');
        }
      });
    });
  });
</script>
