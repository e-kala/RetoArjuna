<?php
// Dashboard para usuarios normales (no-admin) — a propósito NO usa el shell
// sb-admin-2 de panel/index.php (ese sigue siendo el panel de administración).
// Esto es lo que ve un estudiante justo después de iniciar sesión: se apoya en
// el mismo navbar/paleta de la plataforma pública (crema/naranja/dorado) para
// que se sienta como una plataforma educativa, no una consola administrativa.
require_once __DIR__ . '/../backend/auth.php';
require_login('../index.php?action=ingreso');

$usuario = current_user();
$usuarioPerfilId = (int) $usuario['id'];
$notificacionesNoLeidas = notificaciones_no_leidas($usuarioPerfilId);

// Igual que en panel/index.php: hay que resolver el POST de eliminar compra
// antes de imprimir cualquier HTML, porque content/mis_compras.php se incluye
// después del <head>/navbar — un header() de ahí llegaría tarde.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_compra') {
    requerir_csrf_form();
    $esAjax = es_peticion_ajax();
    $pagoId = (int) ($_POST['pago_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM pagos WHERE id = ? AND usuario_id = ?');
    $stmt->bind_param('ii', $pagoId, $usuarioPerfilId);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) {
        echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Registro eliminado.']);
        exit;
    }
    header('Location: ?action=mis_compras');
    exit;
}

// "mis_cursos" es la pestaña de entrada por default — antes había una vista
// "Mi aprendizaje" que combinaba cursos+eventos+reconocimientos+explorar en
// una sola pantalla; se separó en pestañas propias, cada una resuelve sus
// propias consultas en su content/*.php (mismo patrón que ya usaban
// mis_compras.php/perfil.php). El mapeo se centraliza aquí para reusarlo
// tanto en la carga completa como en el corte temprano de Ajax de abajo.
$mapaContenidoPestanas = [
    'mis_cursos' => 'content/mis_cursos.php',
    'mis_eventos' => 'content/mis_eventos.php',
    'mis_compras' => 'content/mis_compras.php',
    'reconocimientos' => 'content/reconocimientos.php',
    'mi_membresia' => 'content/mi_membresia.php',
    'perfil' => 'content/perfil.php',
    'foro_actividad' => 'content/foro_actividad.php',
    'notificaciones' => 'content/notificaciones.php',
];
$action = $_GET['action'] ?? 'mis_cursos';
$archivoContenido = $mapaContenidoPestanas[$action] ?? $mapaContenidoPestanas['mis_cursos'];
$esMiembro = usuario_tiene_membresia_activa($usuarioPerfilId);

// Cambio de pestaña asíncrono (JS al fondo de este archivo): si la petición
// viene por $.ajax() (misma detección que ya usaba "eliminar_compra" arriba),
// responde SOLO el fragmento HTML de la pestaña — sin head/navbar/barra de
// pestañas — para que el cliente lo inyecte directo en #dashboardTabContent
// sin recargar la página. Una visita directa a la URL (o F5) sigue trayendo
// la página completa, vía el mismo $archivoContenido más abajo.
if (es_peticion_ajax()) {
    include $archivoContenido;
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi panel — Reto Arjuna</title>
  <link rel="icon" href="../digital-creative/img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/platform.css?v=2.8">
  <style>
    /* Los content/*.php reutilizados (mis_compras, perfil) traen algunas clases
       de sb-admin-2 (panel/index.php) que aquí no se cargan — se preservan con
       un valor equivalente para que no se vean fuera de lugar. */
    .text-gray-800 { color: #5a5c69; }
    .pf-tab-activa { background:#171717; color:#fff; }
    #dashboardTabContent.pf-cargando { opacity: .45; pointer-events: none; transition: opacity .15s; }
  </style>
</head>
<body class="pf-body">
  <?php $navPrefijo = '../../'; $navActivoContiene = ''; include '../content/navbar.php'; ?>

  <div class="container" style="margin-top: 143px; margin-bottom: 60px;">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
      <div>
        <h1 class="h3 fw-bold mb-1">Hola, <?= htmlspecialchars((string) $usuario['username']) ?> 👋</h1>
        <p class="text-muted mb-0">Este es tu espacio de aprendizaje en el Camino Arjuna.</p>
      </div>
      <?php if ($esMiembro): ?>
        <span class="badge rounded-pill px-3 py-2" style="background:#F6C500;color:#171717;font-size:13px;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Miembro activo</span>
      <?php else: ?>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=membresia" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;">Hazte miembro</a>
      <?php endif; ?>
    </div>

    <ul class="nav gap-2 mb-4 flex-wrap" id="pestanasDashboard">
      <?php foreach ($mapaContenidoPestanas as $accionPestana => $archivoPestana):
        if (in_array($accionPestana, ['foro_actividad', 'notificaciones'], true)) continue; // se imprimen aparte, abajo (llevan iconos/badge propios)
      ?>
        <li class="nav-item">
          <a class="btn btn-sm pf-tab-link <?= $action === $accionPestana ? 'pf-tab-activa' : 'btn-outline-secondary' ?>" data-accion="<?= $accionPestana ?>" href="?action=<?= $accionPestana ?>">
            <?= ['mis_cursos' => 'Mis cursos', 'mis_eventos' => 'Mis eventos', 'mis_compras' => 'Mis compras', 'reconocimientos' => 'Mis reconocimientos', 'mi_membresia' => 'Mi membresía', 'perfil' => 'Mi perfil'][$accionPestana] ?>
          </a>
        </li>
      <?php endforeach; ?>
      <li class="nav-item">
        <a class="btn btn-sm pf-tab-link <?= $action === 'foro_actividad' ? 'pf-tab-activa' : 'btn-outline-secondary' ?>" data-accion="foro_actividad" href="?action=foro_actividad">Mi actividad en el foro</a>
      </li>
      <li class="nav-item">
        <a class="btn btn-sm pf-tab-link <?= $action === 'notificaciones' ? 'pf-tab-activa' : 'btn-outline-secondary' ?>" data-accion="notificaciones" href="?action=notificaciones">
          Notificaciones
          <?php if ($notificacionesNoLeidas > 0): ?><span class="badge rounded-pill bg-danger ms-1"><?= $notificacionesNoLeidas > 9 ? '9+' : $notificacionesNoLeidas ?></span><?php endif; ?>
        </a>
      </li>
    </ul>

    <div id="dashboardTabContent">
      <?php include $archivoContenido; ?>
    </div>
  </div>

  <?php $navPrefijo = '../../'; include '../content/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
  <?php include 'content/_ajax_scripts.php'; ?>
  <script src="../content/session_watch.js" data-check-url="../backend/session_check.php"></script>
  <script>
    // Pestañas asíncronas: el clic ya no navega — pide el fragmento HTML de
    // la pestaña por $.ajax() (dashboard.php lo detecta con es_peticion_ajax()
    // y responde solo el contenido, sin layout) y lo inyecta en
    // #dashboardTabContent con $.html() (a diferencia de innerHTML nativo,
    // jQuery SÍ re-ejecuta los <script> que traiga el fragmento — cada
    // content/*.php trae los suyos, ej. el buscador de mis_cursos.php).
    (function () {
      var $contenido = $('#dashboardTabContent');

      function marcarPestanaActiva(accion) {
        $('.pf-tab-link').each(function () {
          var $a = $(this);
          var esActiva = $a.data('accion') === accion;
          $a.toggleClass('pf-tab-activa', esActiva).toggleClass('btn-outline-secondary', !esActiva);
        });
      }

      function cargarPestana(accion, agregarHistorial) {
        var url = '?action=' + encodeURIComponent(accion);
        $contenido.addClass('pf-cargando');
        $.ajax({ url: url, method: 'GET', dataType: 'html' })
          .done(function (html) {
            $contenido.html(html);
            marcarPestanaActiva(accion);
            if (agregarHistorial) {
              window.history.pushState({ accion: accion }, '', url);
            }
          })
          .fail(function () {
            $.notify('No se pudo cargar esta sección, intenta de nuevo.', { className: 'error', position: 'top right', autoHideDelay: 4000 });
          })
          .always(function () {
            $contenido.removeClass('pf-cargando');
          });
      }

      $(document).on('click', '.pf-tab-link', function (e) {
        e.preventDefault();
        cargarPestana($(this).data('accion'), true);
      });

      window.addEventListener('popstate', function (e) {
        var accion = (e.state && e.state.accion) || 'mis_cursos';
        cargarPestana(accion, false);
      });

      window.history.replaceState({ accion: <?= json_encode($action) ?> }, '', window.location.href);
    })();
  </script>
</body>
</html>
