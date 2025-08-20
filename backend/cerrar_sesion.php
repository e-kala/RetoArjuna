<?php
// Iniciar la sesión
session_start();

// Eliminar todas las variables de sesión
$_SESSION = [];

// Finalmente, destruir la sesión
session_destroy();

// Redirigir al usuario a la página de inicio o a la página de inicio de sesión
header("Location: ../");
exit();
?>