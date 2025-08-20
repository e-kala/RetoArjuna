<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('conexion.php');


// Obtener datos del formulario
$nombre = "0";
$apellido = "0";
$direccion1 = "0";
$pass = $_POST['inputPassword'];
$direccion2 = "0";
$email = "0";
$date = "0000-00-00";
$telefono = $_POST['whatsapp'];
$json = "0";


$sqlInsert = "INSERT INTO usuarios_ra 
  (usuario_nombre, 
  usuario_apellido, 
  usuario_correo,
  usuario_pass,
  usuario_fecha_nacimiento,
  usuario_direccion_1,
  usuario_direccion_2,
  usuario_telefono, 
  usuario_json)
  VALUES ('$nombre', '$apellido', '$email', '$pass', '$date', '$direccion1', '$direccion2', '$telefono', '$json')";



//hacer consulta para verificar si ya existe algún registro con los mismos datos
$sql = "SELECT * FROM usuarios_ra WHERE usuario_telefono = '$telefono'";
$result = $conn->query($sql); //consulta db

if ($result->num_rows > 0) {
  //verifica cuántos registros hay similares
  while ($row = $result->fetch_assoc()) {
    //echo "id: " . $row["usuario_id"] . " - whats: " . $row["usuario_telefono"] . " " . $row["usuario_pass"] . "<br>";
  }
  //echo "Ya hay un usuario registrado con esas características";
  //responde ajax "tu usuario ya está registrado"
  echo json_encode(['success' => false]);
} else {
  //sino hay ningún registro igual
  //Inserta el registro
  if ($conn->query($sqlInsert) === TRUE) {
    //responde al front que se registró correctamente
    $_SESSION['login'] = true;
    $_SESSION['whatsapp'] = $telefono;
    echo json_encode(['success' => true]);
    //

  } else {
    $response = [
      'success' => false,
      'message' => 'Error en la consulta SQL',
      'error_details' => [
        'sql' => $sql,
        'database_error' => $conn->error
      ]
    ];

    echo json_encode($response);
  }

}


$conn->close();

?>