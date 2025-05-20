<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'content/head.php'; ?>
</head>

<body>
  <div class="container-fluid">
    <!-- NAVBAR IN-->
    <?php
    if (!$_GET['action']) {
      $action = 'inicio';
      //echo 'inicio1';
    } else {
      $action = $_GET['action'];
      //echo $action;
    }

    // if ($action != 'login') {
    include 'content/navbar.php';
    // }
    ?>
    <!-- NAVBAR OUT-->


    <?php
    //include 'content/pruebas.php';



    switch ($action) {
      case 'inicio':
        //echo 'inicio 2';
        include 'content/inicio.php';

        
        break;
      case 'quienes_somos':
        ;
        break;
      case 'contacto':
        
        break;
      case 'ingreso':
        include 'content/ingreso.php';

        break;

      case 'registro':
        include 'content/registro.php';

        break;

      case '7a':
        include 'content/7aEdicion.php';

        break;
      default:
        
    }

    //if ($action != 'login') {
    include 'content/footer.php';
    //}
    ?>
  </div>

  <?php



  include 'content/scripts.php'; ?>
</body>

</html>