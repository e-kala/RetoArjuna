<?php
if (is_logged_in()) {
    // Si ya hay sesión y de todos modos se llegó aquí con un volver (ej. un
    // CTA de landing que siempre enlaza a registro.php sin saber si ya está
    // logueado), hay que respetarlo — si no, este guard lo descarta antes de
    // que el resto de la página siquiera lo lea, y manda al panel en vez de
    // a la página de pago de la que venía.
    header('Location: ' . redirect_post_login_con_aviso_sesion((string) ($_GET['volver'] ?? '')));
    exit;
}
// El destino a donde regresar tras registrarse (ej. un curso/evento del que
// vino) viaja como query param desde quien enlazó aquí (navbar.php, CTAs de
// contenido) — se reenvía tal cual al backend; la validación real (anti open
// redirect) vive una sola vez, en volver_validado() dentro de auth.php.
$volverActual = (string) ($_GET['volver'] ?? '');
// Mismo origen que volver, pero el cupón (a diferencia de "a dónde
// regresar") también puede venir ya guardado en sesión de una visita
// anterior (P03: "puede conocer la oferta antes de registrarse") — un
// ?cupon= explícito en la URL actual siempre pisa el de sesión.
$cuponActual = (string) ($_GET['cupon'] ?? ($_SESSION['cupon_pendiente'] ?? ''));
?>
<style>
  #registrationForm .form-control:focus { border-color: #F6C500; box-shadow: 0 0 0 .25rem rgba(246,197,0,.25); }
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
          <h1 class="h3 fw-bold text-center mb-4" style="color:var(--pf-ink);">Crea tu Cuenta Arjuna</h1>

          <form id="registrationForm">
            <input type="hidden" id="inputVolver" value="<?= htmlspecialchars($volverActual) ?>">
            <input type="hidden" id="inputCupon" value="<?= htmlspecialchars($cuponActual) ?>">
            <div id="registroError" class="alert alert-danger d-none"></div>

            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="inputUsername" placeholder="Nombre de usuario" required>
              <label for="inputUsername">Nombre de usuario</label>
            </div>
            <div class="form-floating mb-3">
              <input type="email" class="form-control" id="inputEmail" placeholder="Correo electrónico" required>
              <label for="inputEmail">Correo electrónico</label>
            </div>
            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="inputTelefono" placeholder="WhatsApp (opcional)">
              <label for="inputTelefono">WhatsApp (opcional)</label>
            </div>
            <div class="form-floating mb-3">
              <input type="password" id="inputPassword" class="form-control" placeholder="Contraseña" aria-describedby="passwordHelpBlock" required>
              <label for="inputPassword">Crea una contraseña</label>
            </div>
            <div class="form-floating mb-2">
              <input type="password" id="confirmPassword" class="form-control" placeholder="Confirma tu contraseña" required>
              <label for="confirmPassword">Confirma tu contraseña</label>
            </div>
            <div id="passwordHelpBlock" class="form-text mb-4">
              Tu contraseña debe tener al menos 8 caracteres y contener letras y números.
            </div>

            <button type="submit" class="btn btn-lg w-100 fw-bold" style="background:#F6C500;color:#171717;">Crear mi cuenta</button>
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

          <p class="text-center text-muted mt-4 mb-0">¿Ya tienes cuenta?
            <a href="?action=ingreso<?= $volverActual !== '' ? '&volver=' . urlencode($volverActual) : '' ?><?= $cuponActual !== '' ? '&cupon=' . urlencode($cuponActual) : '' ?>" class="fw-bold text-decoration-none" style="color:#B8860B;">Inicia sesión</a>
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
          $('#registroError').text(data.message || 'No se pudo iniciar sesión con Google.').removeClass('d-none');
        }
      },
      error: function () {
        $('#registroError').text('Error de conexión con Google.').removeClass('d-none');
      }
    });
  }
</script>
<?php endif; ?>

<script>
  $(function () {
    $('#registrationForm').on('submit', function (event) {
      event.preventDefault();

      const username = $('#inputUsername').val().trim();
      const email = $('#inputEmail').val().trim();
      const telefono = $('#inputTelefono').val().trim();
      const password = $('#inputPassword').val();
      const confirmPassword = $('#confirmPassword').val();
      const $error = $('#registroError').addClass('d-none');

      if (password.length < 8 || !/[A-Za-z]/.test(password) || !/\d/.test(password)) {
        $error.text('La contraseña debe tener al menos 8 caracteres, con letras y números.').removeClass('d-none');
        return;
      }
      if (password !== confirmPassword) {
        $error.text('Las contraseñas no coinciden.').removeClass('d-none');
        return;
      }

      $.ajax({
        url: 'backend/registro_back.php',
        method: 'POST',
        data: {
          username: username,
          email: email,
          telefono: telefono,
          password: password,
          password_confirm: confirmPassword,
          csrf_token: <?= json_encode(csrf_token()) ?>,
          volver: $('#inputVolver').val(),
          cupon: $('#inputCupon').val()
        },
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            window.location.href = response.redirect || 'panel/index.php';
          } else {
            $error.text(response.message || 'No se pudo completar el registro.').removeClass('d-none');
          }
        },
        error: function () {
          $error.text('Error de conexión. Intenta de nuevo.').removeClass('d-none');
        }
      });
    });
  });
</script>
