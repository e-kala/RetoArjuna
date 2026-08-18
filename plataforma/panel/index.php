<?php
// Dashboard de administración — ya NO usa SB Admin 2 (retirado por completo).
// Mismo Bootstrap + platform.css (dorado/crema) que el resto del sitio.
require_once __DIR__ . '/../backend/auth.php';
require_login('../index.php?action=ingreso');

$usuario = current_user();
$esAdmin = $usuario['rol'] === 'admin';

// Igual que en dashboard.php: hay que resolver el POST de eliminar compra
// antes de imprimir cualquier HTML, porque content/mis_compras.php se incluye
// después del <head>/navbar — un header() de ahí llegaría tarde.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_compra') {
    requerir_csrf_form();
    $pagoId = (int) ($_POST['pago_id'] ?? 0);
    $usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
    $stmt = $conn->prepare('DELETE FROM pagos WHERE id = ? AND usuario_id = ?');
    $stmt->bind_param('ii', $pagoId, $usuarioPerfilId);
    $stmt->execute();
    $stmt->close();
    header('Location: ?action=mis_compras');
    exit;
}

$action = $_GET['action'] ?? 'inicio';

// Este dashboard (con las tarjetas de herramientas de administración) es solo
// para admins — cualquier otra persona que llegue aquí de alguna forma (link
// viejo, favorito) se manda a su propio dashboard de estudiante en vez de
// enseñarle herramientas que no puede usar.
if (!$esAdmin && $action === 'inicio') {
    header('Location: dashboard.php');
    exit;
}

if ($esAdmin && $action === 'inicio') {
    $totalUsuarios = (int) $conn->query('SELECT COUNT(*) AS n FROM usuarios_perfil')->fetch_assoc()['n'];
    $usuariosActivos = (int) $conn->query('SELECT COUNT(*) AS n FROM usuarios_perfil WHERE activo = 1')->fetch_assoc()['n'];
    $totalCursos = (int) $conn->query('SELECT COUNT(*) AS n FROM cursos')->fetch_assoc()['n'];
    $cursosPublicados = (int) $conn->query('SELECT COUNT(*) AS n FROM cursos WHERE activo = 1')->fetch_assoc()['n'];
    $pagosPendientes = (int) $conn->query("SELECT COUNT(*) AS n FROM pagos WHERE estado = 'pendiente'")->fetch_assoc()['n'];
    $pagosConfirmados = (int) $conn->query("SELECT COUNT(*) AS n FROM pagos WHERE estado = 'confirmado'")->fetch_assoc()['n'];
    $miembrosActivos = (int) $conn->query("SELECT COUNT(*) AS n FROM membresia_suscripciones WHERE estado = 'activa' AND (periodo_actual_fin IS NULL OR periodo_actual_fin >= NOW())")->fetch_assoc()['n'];

    $pctUsuariosActivos = $totalUsuarios > 0 ? (int) round($usuariosActivos / $totalUsuarios * 100) : 0;
    $pctCursosPublicados = $totalCursos > 0 ? (int) round($cursosPublicados / $totalCursos * 100) : 0;
    $totalPagosResueltos = $pagosConfirmados + $pagosPendientes;
    $pctPagosConfirmados = $totalPagosResueltos > 0 ? (int) round($pagosConfirmados / $totalPagosResueltos * 100) : 0;
    $pctMiembros = $totalUsuarios > 0 ? (int) round($miembrosActivos / $totalUsuarios * 100) : 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel admin — Reto Arjuna</title>
  <link rel="icon" href="../../digital-creative/img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../../assets/css/platform.css?v=1.2">
  <style>
    /* Los content/*.php reutilizados (mis_compras, perfil) traen alguna clase
       vieja de SB Admin 2 que ya no se carga — se preserva con un valor
       equivalente para que no se vean fuera de lugar. */
    .text-gray-800 { color: #5a5c69; }
  </style>
</head>
<body class="pf-body">
  <?php $navPrefijo = '../../'; $navActivoContiene = ''; include '../content/navbar.php'; ?>

  <div class="container" style="margin-top: 143px; margin-bottom: 60px;">
    <?php if ($action === 'mis_compras'): ?>
      <?php include 'content/mis_compras.php'; ?>
    <?php elseif ($action === 'perfil'): ?>
      <?php include 'content/perfil.php'; ?>
    <?php else: ?>

      <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
          <h1 class="h3 fw-bold mb-1">Panel de administración</h1>
          <p class="text-muted mb-0">Hola, <?= htmlspecialchars((string) $usuario['username']) ?> — esto es lo que está pasando en la plataforma.</p>
        </div>
        <a href="../index.php" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Ver la plataforma</a>
      </div>

      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3 mb-5">
        <div class="col">
          <div class="ra-stat-card">
            <div class="ra-stat-label">Usuarios activos</div>
            <div class="ra-stat-value"><?= $usuariosActivos ?> <span class="text-muted fs-6 fw-normal">/ <?= $totalUsuarios ?></span></div>
            <div class="ra-stat-bar"><span style="width:<?= $pctUsuariosActivos ?>%;"></span></div>
          </div>
        </div>
        <div class="col">
          <div class="ra-stat-card">
            <div class="ra-stat-label">Cursos publicados</div>
            <div class="ra-stat-value"><?= $cursosPublicados ?> <span class="text-muted fs-6 fw-normal">/ <?= $totalCursos ?></span></div>
            <div class="ra-stat-bar"><span style="width:<?= $pctCursosPublicados ?>%;"></span></div>
          </div>
        </div>
        <div class="col">
          <div class="ra-stat-card">
            <div class="ra-stat-label">Pagos confirmados</div>
            <div class="ra-stat-value"><?= $pagosConfirmados ?> <span class="text-muted fs-6 fw-normal">/ <?= $totalPagosResueltos ?></span></div>
            <div class="ra-stat-bar"><span style="width:<?= $pctPagosConfirmados ?>%;"></span></div>
            <?php if ($pagosPendientes > 0): ?>
              <a href="admin/pagos.php" class="small d-block mt-2" style="color:#B8860B;"><?= $pagosPendientes ?> pendiente<?= $pagosPendientes === 1 ? '' : 's' ?> por revisar →</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="col">
          <div class="ra-stat-card">
            <div class="ra-stat-label">Miembros activos</div>
            <div class="ra-stat-value"><?= $miembrosActivos ?> <span class="text-muted fs-6 fw-normal">/ <?= $totalUsuarios ?></span></div>
            <div class="ra-stat-bar"><span style="width:<?= $pctMiembros ?>%;"></span></div>
          </div>
        </div>
      </div>

      <h2 class="h5 fw-bold mb-3">Herramientas</h2>
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
        <?php
        $herramientas = [
          ['href' => 'admin/cursos.php', 'icono' => 'bi-book', 'titulo' => 'Cursos', 'texto' => 'Lecciones, materiales y quizzes'],
          ['href' => 'admin/eventos.php', 'icono' => 'bi-calendar-event', 'titulo' => 'Eventos', 'texto' => 'Encuentros en línea y presenciales'],
          ['href' => 'admin/productos.php', 'icono' => 'bi-shop', 'titulo' => 'Productos', 'texto' => 'Tienda física y digital'],
          ['href' => 'admin/usuarios.php', 'icono' => 'bi-people', 'titulo' => 'Usuarios', 'texto' => 'Cuentas, roles y accesos'],
          ['href' => 'admin/pagos.php', 'icono' => 'bi-cash-coin', 'titulo' => 'Pagos', 'texto' => 'Confirmar transferencias y Stripe'],
          ['href' => 'admin/foro_categorias.php', 'icono' => 'bi-chat-square-text', 'titulo' => 'Foro', 'texto' => 'Categorías de la comunidad'],
          ['href' => 'admin/actividades.php', 'icono' => 'bi-hands', 'titulo' => 'Actividades', 'texto' => 'Prácticas guiadas'],
          ['href' => 'admin/noticias.php', 'icono' => 'bi-newspaper', 'titulo' => 'Noticias', 'texto' => 'Avisos de la comunidad'],
          ['href' => 'admin/membresias.php', 'icono' => 'bi-award', 'titulo' => 'Membresías', 'texto' => 'Camino Arjuna y suscripciones'],
          ['href' => 'admin/navbar_links.php', 'icono' => 'bi-list', 'titulo' => 'Navbar', 'texto' => 'Links del menú y pie de página'],
          ['href' => 'admin/reportes.php', 'icono' => 'bi-bar-chart', 'titulo' => 'Reportes', 'texto' => 'Panorama general de la plataforma'],
        ];
        ?>
        <?php foreach ($herramientas as $h): ?>
          <div class="col">
            <a href="<?= htmlspecialchars($h['href']) ?>" class="ra-tool-card">
              <div class="ra-tool-icon"><i class="bi <?= $h['icono'] ?>"></i></div>
              <div>
                <h3><?= htmlspecialchars($h['titulo']) ?></h3>
                <p><?= htmlspecialchars($h['texto']) ?></p>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>

  <footer class="py-4 text-center text-muted small" style="border-top:1px solid var(--pf-line);">
    &copy; 2026 Reto Arjuna
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>
