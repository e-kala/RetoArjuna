<div class="container mt-4" style="margin-bottom: 35px; margin-top: 143px !important;  ">
  <div class="row justify-content-center">
    <h1 class="text-center">Regístrate</h1>
    <div class="col-sm-6" style="background-color: #f7931e; padding: 30px; border-radius: 10px;">
      <form class="row g-3" id="registrationForm">

        <div id="registroError" class="alert alert-danger d-none"></div>

        <div class="mb-3">
          <label for="inputUsername" class="form-label">Nombre de usuario</label>
          <input type="text" class="form-control" id="inputUsername" required>
        </div>
        <div class="mb-3">
          <label for="inputEmail" class="form-label">Correo electrónico</label>
          <input type="email" class="form-control" id="inputEmail" required>
        </div>
        <div class="mb-3">
          <label for="inputTelefono" class="form-label">WhatsApp (opcional)</label>
          <input type="text" class="form-control" id="inputTelefono" placeholder="Ej: 5210000000000">
        </div>
        <div class="mb-3">
          <label for="inputPassword" class="form-label">Crea Una Contraseña</label>
          <input type="password" id="inputPassword" class="form-control" aria-describedby="passwordHelpBlock" required>
        </div>
        <div class="mb-3">
          <label for="confirmPassword" class="form-label">Confirma Tu Contraseña</label>
          <input type="password" id="confirmPassword" class="form-control" required>
        </div>
        <div id="passwordHelpBlock" class="form-text">
          Tu contraseña debe tener al menos 8 caracteres y contener letras y números.
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary mb-3">Registrarme</button>
        </div>
      </form>

      <?php if (config_esta_lista(GOOGLE_CLIENT_ID)): ?>
      <div class="text-center mt-3">
        <p class="mb-2">o:</p>
        <div id="g_id_onload"
             data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
             data-callback="handleGoogleLogin"
             data-auto_prompt="false">
        </div>
        <div class="g_id_signin d-flex justify-content-center" data-type="standard" data-width="300"></div>
      </div>
      <?php endif; ?>
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
        csrf_token: <?= json_encode(csrf_token()) ?>
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
          csrf_token: <?= json_encode(csrf_token()) ?>
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
