<?php
session_start();
//header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('conexion.php');


// Obtener datos del formulario
$telefono = $_POST['whatsapp'];
$pass = $_POST['inputPassword'];


//hacer consulta para verificar si ya existe algún registro con los mismos datos
$sql = "SELECT * FROM usuarios_ra WHERE usuario_telefono = '$telefono' AND usuario_pass = '$pass'";
$result = $conn->query($sql); //consulta db


$row = $result->fetch_assoc();
if ($row !== null) {
/*
echo "id: " . $row["usuario_id"] . 
" - whats: " . $row["usuario_telefono"] . " " 
. $row["usuario_pass"];
*/
$_SESSION['login'] = true;
$_SESSION['id'] =$row["usuario_id"];
$_SESSION['whatsapp'] =$row["usuario_telefono"]; 

echo $_SESSION['login'];


echo $_SESSION['id'];


echo $_SESSION['whatsapp'];
//$_SESSION[''] =$row["usuario_pass"];



}else {
    echo "No se encontraron registros.";
}

echo json_encode(['success' => false]);

$conn->close();

?>