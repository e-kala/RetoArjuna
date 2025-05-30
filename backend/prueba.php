<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('conexion.php');


// Obtener datos del formulario
$nombre = $_POST['nombre'];
$apellido = "0";
$pass = "0";
$direccion1 = "0";
$direccion2 = "0";
$email = $_POST['email'];
$date = "0000-00-00";
$telefono = "000000000";
$json = "0";


$sql = "INSERT INTO usuarios_ra 
(usuario_nombre, 
usuario_apellido, 
usuario_correo,
usuario_pass,
usuario_fecha_nacimiento,
usuario_direccion_1,
usuario_direccion_2,
usuario_telefono, 
usuario_json)
VALUES ('$nombre', '$apellido', '$email', $pass, '$date', $direccion1, $direccion2, '$telefono', '$json')";

if ($conn->query($sql) === TRUE) {
  echo "New record created successfully";
} else {
  echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();


