<div class="container mt-4" style="margin-bottom: 35px; margin-top: 143px !important;  ">
  <div class="row justify-content-center">
    <!--título-->
    <h1 class="text-center">Regístrate</h1>
    <div class="col-sm-6" style="background-color: #f7931e; padding: 30px; border-radius: 10px;">
      <!-- Contenido del formulario  IN-->
      <form class="row g-3" id="registrationForm">
        <div class="mb-3">
          <label for="inputWhatsapp" class="form-label">Regístrate Con Tu WhatsApp</label>
          <input type="text" class="form-control" id="inputWhatsapp" placeholder="Ej: 1234567890" required>
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
          Su contraseña debe tener entre 8 y 20 caracteres, contener letras y números y no debe contener espacios
          especiales
          personajes o emoji.
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary mb-3">Registrarme</button>
        </div>
      </form>
      <!-- Contenido del formulario END -->
    </div>
  </div>
</div>


<!--Registro usuario IN-->
<script>
  $(function () {
    // Expresión regular para validar el número telefónico
    const phoneRegex = /^\+?[0-9]{10,15}$/; // Ejemplo: +521234567890 o 1234567890


    $("#registrationForm").on("submit", function (event) {
      event.preventDefault(); // Evitar el envío del formulario
      const whatsapp = $("#inputWhatsapp").val();
      const inputPassword = $("#inputPassword").val();
      const confirmPassword = $('#confirmPassword').val();
      //<!--Validar número In-->
      if (!phoneRegex.test(whatsapp, inputPassword, confirmPassword)) {
        console.log('Por favor, ingresa un número de teléfono válido.');
      $("#inputWhatsapp").notify("Ingresa un número válido");

      } else {
        validaPass(whatsapp, inputPassword, confirmPassword);
        console.log('Numero válido'); // Ejemplo de acción
      }
      //<!--Validar número END-->
    });
  });

  //<!--Validar contraseña In-->
  function validaPass(whatsapp, inputPassword, confirmPassword) {

    if (inputPassword.length < 8) {
      console.log('La contraseña debe tener al menos 8 caracteres.');
      $("#inputPassword").notify("La contraseña debe tener al menos 8 caracteres.");
      return;
    }

    // Validar que la contraseña contenga al menos un número y una letra
    const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
    if (!passwordRegex.test(inputPassword)) {
      console.log('La contraseña debe contener al menos una letra y un número.');
      $("#inputPassword").notify("La contraseña debe contener al menos una letra y un número.");

      return;
    }

    // Validar coincidencia de contraseñas
    if (inputPassword !== confirmPassword) {
      console.log('Las contraseñas no coinciden. Por favor, inténtalo de nuevo.');
      $("#confirmPassword").notify("Las contraseñas deben coincidir.");
      return;
    }

    // Si todas las validaciones pasan
    console.log('Contraseña validada. Puedes enviar el formulario.');
    // Aquí puedes agregar la lógica para enviar el formulario o realizar otra acción


    if (inputPassword !== confirmPassword) {
      console.log('Las contraseñas no coinciden. Por favor, inténtalo de nuevo.');
      console.log(inputPassword);
      console.log(confirmPassword);

    } else {
      console.log('Contraseña validada'); // Ejemplo de acción
      registro(whatsapp, inputPassword, confirmPassword);
    }
  }
  //<!--Validar contraseña END-->

  //Envia formulario (hace el registro) IN 
  function registro(whatsapp, inputPassword) {

    console.log(whatsapp);
    console.log(inputPassword);

    $.ajax({
      url: './backend/registro_back.php',
      method: 'POST',
      data: {
        whatsapp: whatsapp,
        inputPassword: inputPassword
      },
      success: function (response) {
        console.log('Respuesta del servidor:', response);
        //window.location.href = "content/registrado.php";
        $.notify("registrado", "success");
        window.location.href = 'panel/?action=perfil';
        if (response.success === true) {

        } else {
          //alert('La operación falló');
          $.notify("Tu usuario ya está registrado", "error");
        }
        autologin();
      },
      error: function (xhr, status, error) {
        console.error('Error en Ajax:', error);
        console.log(xhr.responseText);
        window.location.href = "registrado.php";

      }
    });
  }
  //envia formulario (hace el registro) END

  //autologin IN
  function autologin() {
    console.log("AUTOLOGIN");
    //consulta de numero y pass

    //redirigir al dashboard con la sesión php
  }
</script>