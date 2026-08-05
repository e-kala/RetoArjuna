<div class="container mt-4" style="margin-bottom: 35px; margin-top: 143px !important;  ">
  <div class="row justify-content-center">
    <h1 class="text-center">Inicia Sesión</h1>
    <div class="col-sm-6" style="background-color: #f7931e; padding: 30px; border-radius: 10px;">
      <form id="loginForm">

        <div id="loginError" class="alert alert-danger d-none"></div>

        <!-- Usuario o correo -->
        <div data-mdb-input-init class="form-outline mb-4">
          <label class="form-label" for="inputIdentification">Usuario o correo</label>
          <input type="text" id="inputIdentification" class="form-control" required />
        </div>

        <!-- Password input -->
        <div data-mdb-input-init class="form-outline mb-4">
          <input type="password" id="inputPassword" class="form-control" required />
          <label class="form-label" for="inputPassword">Contraseña</label>
        </div>

        <div class="row mb-4">
          <div class="col-md-6 d-flex justify-content-center">
            <button type="submit" data-mdb-button-init data-mdb-ripple-init
              class="btn btn-primary btn-block mb-4">Inicia Sesión</button>
          </div>
        </div>

        <div class="text-center">
          <p>¿Aún no eres miembro? <a href="?action=registro">Regístrate Aquí</a></p>
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
          csrf_token: <?= json_encode(csrf_token()) ?>
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
