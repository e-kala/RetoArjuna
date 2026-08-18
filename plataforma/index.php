<?php
require_once __DIR__ . '/backend/auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'content/head.php'; ?>
</head>

<body class="pf-body">
  <div>
    <?php
    $action = $_GET['action'] ?? 'inicio';
    $navPrefijo = '../';
    $navActivoContiene = 'action=' . $action;
    include 'content/navbar.php';
    ?>

    <?php
    switch ($action) {
      case 'inicio':
        include 'content/inicio.php';
        break;
      case 'ingreso':
        include 'content/ingreso.php';
        break;
      case 'registro':
        include 'content/registro.php';
        break;
      case 'cursos':
        include 'content/cursos_catalogo.php';
        break;
      case 'membresia':
        include 'content/membresia.php';
        break;
      case 'actividades':
        include 'content/actividades.php';
        break;
      case 'noticias':
        include 'content/noticias_catalogo.php';
        break;
      case 'noticia':
        include 'content/noticia_detalle.php';
        break;
      case 'curso':
        include 'content/curso_detalle.php';
        break;
      case 'leccion':
        include 'content/leccion.php';
        break;
      case 'eventos':
        include 'content/eventos_catalogo.php';
        break;
      case 'evento':
        include 'content/evento_detalle.php';
        break;
      case 'tienda':
        include 'content/tienda_catalogo.php';
        break;
      case 'producto':
        include 'content/producto_detalle.php';
        break;
      case '7a':
        include 'content/7aEdicion.php';
        break;
      default:
    }

    include 'content/footer.php';
    ?>
  </div>

  <?php include 'content/scripts.php'; ?>
</body>

</html>
