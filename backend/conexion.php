<?php
/*
nota: durante las pruebas de conexión había un error oculto con la función 
mysqli() y mysqli_connect()
la contraseña estaba mal en el administrador de usuarios de la db
pero el servidor solo retornaba un código 500

probablemente haya algún erro de configuración de permisos de los archivos o
algún tema con la configuración con php.ini
probablemente sea también la versión de php instalada en local
solucioné cuando en lugar de utilizar las funciones anteriores utilicé PDO, 
fue cuando me retornó el error
*/

//header('Content-Type: application/json');
$servername = "localhost"; 
$username = "u722626749_retoArjuna"; 
$password = "W]@XQN][TA[YC7.D"; 
$dbname = "u722626749_retoArjuna";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
//echo "Connected successfully";
//echo json_encode(['connection' => "Successfully"]). ",";

?>