<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro AJAX con jQuery</title>
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <script>
        $(function() {
            $("#registroForm").on("submit", function(event) {
                event.preventDefault(); // Evitar el envío del formulario

                var nombre = $("#nombre").val();
                var email = $("#email").val();

                $.ajax({
                    url: '../backend/prueba.php',
                    method: 'POST',
                    data: {
                        nombre: nombre,
                        email: email
                    },
                        success: function(response) {
                            console.log('Respuesta del servidor:', response);
                    },
                        error: function(xhr, status, error) {
                            console.error('Error en Ajax:', error);
                            console.log(xhr.responseText);
                    }
                 });
            });
        });
    </script>
</head>
<body>
    <h1>Registro de Usuario</h1>
    <form id="registroForm">
        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" required>
        <br>
        <label for="email">Email:</label>
        <input type="email" id="email" required>
        <br>
        <button type="submit">Registrar</button>
    </form>
    <div id="resultado"></div>
</body>
</html>
