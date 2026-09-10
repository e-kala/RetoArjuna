<?php
require_once __DIR__ . '/backend/auth.php';
// Buffer de salida para todo el router: navbar.php ya imprime HTML antes de
// llegar al content/*.php de turno, y algunos de esos (curso_detalle.php,
// evento_detalle.php, producto_detalle.php, leccion.php) necesitan poder
// hacer header('Location: ...') más abajo (auto-inscripción al volver de
// crear cuenta, accesos bloqueados, etc.). Sin este buffer, esos redirects
// solo funcionaban por casualidad mientras el HTML de navbar.php cupiera
// bajo el output_buffering de php.ini (4096 bytes) — dejó de alcanzar en
// cuanto el navbar creció con el dropdown de notificaciones.
ob_start();
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
      case 'olvide_contrasena':
        include 'content/olvide_contrasena.php';
        break;
      case 'restablecer_contrasena':
        include 'content/restablecer_contrasena.php';
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
      case 'landing':
        include 'content/landing.php';
        break;
      case 'perfil_publico':
        include 'content/perfil_publico.php';
        break;
      case 'regalo':
        include 'content/regalo.php';
        break;
      default:
    }

    include 'content/footer.php';
    ?>
  </div>

  <?php include 'content/scripts.php'; ?>
</body>

</html>
<?php ob_end_flush(); ?>
