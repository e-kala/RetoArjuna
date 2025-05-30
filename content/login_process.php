<?php
	include './backend/conexion.php';
	if ($_POST) {
		session_start();
		$usuario = $_POST['usuario'];
		$email = $_POST['email'];
		$pass = $_POST['pass'];
		$fecha = $_POST['fecha'];
		
		$conectar = new conexion();
		$conexion = $conectar->conectar();

		$consulta = "SELECT * FROM usuarios WHERE nombre_usuario ='$usuario' AND pass_usuario='$pass'"; //Para acceso por nombre de usuario

		$consulta2 = "SELECT * FROM usuarios WHERE correo_usuario = '$usuario' AND pass_usuario='$pass'"; //Para acceso por email


		$resultado = $conexion->query($consulta);
		$resultado2 = $conexion->query($consulta2);

		if ($resultado->num_rows>0 || $resultado2->num_rows>0) { //validacion por usuario o correo + password
			$_SESSION['login'] = TRUE;
			$_SESSION['user'] = $usuario;
			$_SESSION['tiempo'] = time();

			//---------------------------- Registrar sesion-------
			$fechaActual = date('y-m-d');
			$ampm = date("a");
			if ($ampm === "am"){
				$horaActual = date('h') . ":" . date("i") .":00";
			} else {
				$horaActual = date('G') . ":" . date("i") .":00";
			}

			//Registrar cada inicio de sesion con la hora en la que se inicia en el dia
			$sesion_de_hoy = "INSERT INTO sesiones(sesion_usuario, inicio_sesion_fecha, inicio_sesion_hora) VALUES('$usuario', '$fechaActual', '$horaActual')";
			$save_session = $conexion->query($sesion_de_hoy);
			//------------------------------------------------------

			// header('location: ../../index.php');
			echo "<welcome>";
		}else{
			// header('location: ../../index.php?pass=error');
			echo '<script type="text/javascript"> document.body.innerHTML = ""</script>';
		}
	}

?>

