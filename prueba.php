<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://apis.google.com/js/platform.js" async defer></script>
    <meta name="google-signin-client_id"
        content="121887533073-18o653lrha5rc3tdm6t533067vjv6bgi.apps.googleusercontent.com">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
</head>

<body>
    <div class="g-signin2" data-onsuccess="onSignIn"></div>
    <script>
        function onSignIn(googleUser) {
            var profile = googleUser.getBasicProfile();
            console.log('ID: ' + profile.getId()); // No envíes esto a tu backend. Usa el ID Token.
            console.log('Name: ' + profile.getName());
            console.log('Image URL: ' + profile.getImageUrl());
            console.log('Email: ' + profile.getEmail()); // Este es el correo electrónico del usuario.

            var id_token = googleUser.getAuthResponse().id_token;
            // Envía el id_token a tu servidor para verificar la autenticidad del usuario.

            $.ajax({
                url: '/api/auth/google', // La URL de tu endpoint en el backend.
                type: 'POST',           // El método de la petición.
                contentType: 'application/json', // Le decimos al servidor que estamos enviando JSON.
                data: JSON.stringify({ token: id_token }), // Convertimos nuestro objeto JS a una cadena JSON.

                // 3. Función que se ejecuta si la petición es exitosa (el servidor responde con un código 2xx).
                success: function (response) {
                    console.log('Verificación exitosa en el backend:', response);
                    // Aquí puedes redirigir al usuario, actualizar la página, etc.
                    // Por ejemplo, si el servidor responde con una URL a la que redirigir:
                    // window.location.href = response.redirectTo;
                    alert('¡Inicio de sesión exitoso!');
                    window.location.href = '/dashboard'; // Redirige a la página principal del usuario.
                },

                // 4. Función que se ejecuta si hay un error (problema de red o el servidor responde con un error 4xx o 5xx).
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Error al verificar el token:', textStatus, errorThrown);
                    alert('Hubo un problema al iniciar sesión. Por favor, intenta de nuevo.');
                }
            });
        }
    </script>
</body>

</html>