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

// Igual que en panel/index.php: hay que resolver el POST de eliminar compra
// antes de imprimir cualquier HTML, porque content/mis_compras.php se incluye
// después del <head>/navbar — un header() de ahí llegaría tarde.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_compra') {
    requerir_csrf_form();
    $pagoId = (int) ($_POST['pago_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM pagos WHERE id = ? AND usuario_id = ?');
    $stmt->bind_param('ii', $pagoId, $usuarioPerfilId);
    $stmt->execute();
    $stmt->close();
    header('Location: ?action=mis_compras');
    exit;
}

$action = $_GET['action'] ?? 'inicio';
$esMiembro = usuario_tiene_membresia_activa($usuarioPerfilId);

if ($action === 'inicio') {
    $stmt = $conn->prepare(
        "SELECT c.id, c.titulo, c.slug, c.imagen_portada,
                (SELECT COUNT(*) FROM lecciones WHERE curso_id = c.id) AS total_lecciones,
                (SELECT COUNT(*) FROM progreso WHERE curso_id = c.id AND usuario_id = ? AND completado = 1) AS completadas,
                cert.codigo AS codigo_certificado
         FROM cursos c
         LEFT JOIN pagos p ON p.curso_id = c.id AND p.usuario_id = ? AND p.estado = 'confirmado'
         LEFT JOIN curso_inscripciones ci ON ci.curso_id = c.id AND ci.usuario_id = ?
         LEFT JOIN certificados cert ON cert.curso_id = c.id AND cert.usuario_id = ?
         WHERE c.activo = 1 AND (p.id IS NOT NULL OR ci.id IS NOT NULL OR ?)
         GROUP BY c.id"
    );
    $stmt->bind_param('iiiii', $usuarioPerfilId, $usuarioPerfilId, $usuarioPerfilId, $usuarioPerfilId, $esMiembro);
    $stmt->execute();
    $misCursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT e.titulo, e.slug, e.tipo, e.ubicacion, e.fecha_inicio, ei.estado,
                cert.codigo AS codigo_reconocimiento
         FROM evento_inscripciones ei
         JOIN eventos e ON e.id = ei.evento_id
         LEFT JOIN certificados cert ON cert.evento_id = e.id AND cert.usuario_id = ei.usuario_id
         WHERE ei.usuario_id = ? AND ei.estado <> 'cancelado'
         ORDER BY e.fecha_inicio DESC LIMIT 3"
    );
    $stmt->bind_param('i', $usuarioPerfilId);
    $stmt->execute();
    $misEventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT cert.codigo, cert.fecha_emision, cert.tipo, COALESCE(c.titulo, e.titulo) AS titulo
         FROM certificados cert
         LEFT JOIN cursos c ON c.id = cert.curso_id
         LEFT JOIN eventos e ON e.id = cert.evento_id
         WHERE cert.usuario_id = ? ORDER BY cert.fecha_emision DESC LIMIT 3"
    );
    $stmt->bind_param('i', $usuarioPerfilId);
    $stmt->execute();
    $reconocimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // "Explora el contenido": catálogo completo (cursos/eventos/tienda/noticias)
    // combinado en una sola lista para buscar/filtrar del lado del cliente —
    // el volumen de contenido de esta plataforma es chico, no hace falta ida y
    // vuelta al servidor por cada búsqueda o filtro.
    $contenidoExplorable = [];
    $res = $conn->query("SELECT titulo, slug, imagen_portada AS imagen, created_at FROM cursos WHERE activo = 1");
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $contenidoExplorable[] = ['tipo' => 'Cursos', 'icono' => '🎓', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=curso&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
    }
    $res = $conn->query("SELECT titulo, slug, imagen_portada AS imagen, created_at FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW()");
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $contenidoExplorable[] = ['tipo' => 'Eventos', 'icono' => '📅', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=evento&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
    }
    $res = $conn->query("SELECT nombre AS titulo, slug, imagen, created_at FROM productos WHERE activo = 1");
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $contenidoExplorable[] = ['tipo' => 'Tienda', 'icono' => '🛍️', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=producto&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
    }
    $res = $conn->query("SELECT titulo, slug, imagen, publicada_at AS created_at FROM noticias WHERE activo = 1 AND publicada_at <= NOW()");
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $contenidoExplorable[] = ['tipo' => 'Noticias', 'icono' => '📰', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=noticia&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
    }
    usort($contenidoExplorable, fn($a, $b) => strtotime($b['fecha']) <=> strtotime($a['fecha']));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi panel — Reto Arjuna</title>
  <link rel="icon" href="../../digital-creative/img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../../assets/css/platform.css?v=1.2">
  <style>
    /* Los content/*.php reutilizados (mis_compras, perfil) traen algunas clases
       de sb-admin-2 (panel/index.php) que aquí no se cargan — se preservan con
       un valor equivalente para que no se vean fuera de lugar. */
    .text-gray-800 { color: #5a5c69; }
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
        <span class="badge rounded-pill px-3 py-2" style="background:#F6C500;color:#171717;font-size:13px;">👑 Miembro activo</span>
      <?php else: ?>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=membresia" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;">👑 Hazte miembro</a>
      <?php endif; ?>
    </div>

    <ul class="nav gap-2 mb-4">
      <li class="nav-item">
        <a class="btn btn-sm <?= $action === 'inicio' ? '' : 'btn-outline-secondary' ?>" style="<?= $action === 'inicio' ? 'background:#171717;color:#fff;' : '' ?>" href="?action=inicio">Mi aprendizaje</a>
      </li>
      <li class="nav-item">
        <a class="btn btn-sm <?= $action === 'mis_compras' ? '' : 'btn-outline-secondary' ?>" style="<?= $action === 'mis_compras' ? 'background:#171717;color:#fff;' : '' ?>" href="?action=mis_compras">Mis compras</a>
      </li>
      <li class="nav-item">
        <a class="btn btn-sm <?= $action === 'perfil' ? '' : 'btn-outline-secondary' ?>" style="<?= $action === 'perfil' ? 'background:#171717;color:#fff;' : '' ?>" href="?action=perfil">Perfil</a>
      </li>
      <li class="nav-item">
        <a class="btn btn-sm <?= $action === 'foro_actividad' ? '' : 'btn-outline-secondary' ?>" style="<?= $action === 'foro_actividad' ? 'background:#171717;color:#fff;' : '' ?>" href="?action=foro_actividad">Mi actividad en el foro</a>
      </li>
    </ul>

    <?php if ($action === 'mis_compras'): ?>
      <?php include 'content/mis_compras.php'; ?>
    <?php elseif ($action === 'perfil'): ?>
      <?php include 'content/perfil.php'; ?>
    <?php elseif ($action === 'foro_actividad'): ?>
      <?php include 'content/foro_actividad.php'; ?>
    <?php else: ?>

      <?php if ($misCursos): ?>
        <h2 class="h5 fw-bold mb-3">Continúa aprendiendo</h2>
        <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
          <?php foreach ($misCursos as $curso): ?>
            <?php $porcentaje = $curso['total_lecciones'] > 0 ? (int) round($curso['completadas'] / $curso['total_lecciones'] * 100) : 0; ?>
            <div class="col">
              <div class="card h-100 border-0 shadow-sm">
                <img src="<?= htmlspecialchars($curso['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:140px;object-fit:cover;" alt="">
                <div class="card-body d-flex flex-column">
                  <h3 class="h6 fw-bold"><?= htmlspecialchars($curso['titulo']) ?></h3>
                  <div class="progress mb-2" style="height:6px;">
                    <div class="progress-bar" style="width:<?= $porcentaje ?>%;background:#F6C500;"></div>
                  </div>
                  <p class="small text-muted mb-3"><?= $porcentaje ?>% completado</p>
                  <div class="mt-auto d-flex gap-2">
                    <?php if ($curso['codigo_certificado']): ?>
                      <a href="../certificado.php?codigo=<?= urlencode($curso['codigo_certificado']) ?>" class="btn btn-outline-secondary btn-sm">Certificado</a>
                    <?php endif; ?>
                    <a href="../index.php?action=curso&slug=<?= urlencode($curso['slug']) ?>" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;">Continuar</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Mis eventos</h2>
        <a href="../index.php?action=eventos" class="btn btn-outline-secondary btn-sm">Ver próximos eventos</a>
      </div>
      <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <?php foreach ($misEventos as $ev): ?>
          <div class="col">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body">
                <h3 class="h6 fw-bold"><?= htmlspecialchars($ev['titulo']) ?></h3>
                <p class="small text-muted mb-2">
                  <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
                  · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
                </p>
                <span class="badge rounded-pill mb-2" style="background:<?= $ev['estado'] === 'asistio' ? '#e6f4ea' : '#e6f0fb' ?>;color:<?= $ev['estado'] === 'asistio' ? '#1e7d3c' : '#1c5fa8' ?>;">
                  <?= $ev['estado'] === 'asistio' ? 'Asististe' : 'Inscrito' ?>
                </span>
                <div class="d-flex gap-2">
                  <?php if ($ev['codigo_reconocimiento']): ?>
                    <a href="../certificado.php?codigo=<?= urlencode($ev['codigo_reconocimiento']) ?>" class="btn btn-outline-secondary btn-sm">Reconocimiento</a>
                  <?php endif; ?>
                  <a href="../index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;">Ver evento</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$misEventos): ?>
          <p class="text-muted">Aún no te has inscrito a ningún evento. <a href="../index.php?action=eventos" style="color:#B8860B;">Explora los próximos eventos</a>.</p>
        <?php endif; ?>
      </div>

      <h2 class="h5 fw-bold mb-3">Mis reconocimientos</h2>
      <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <?php foreach ($reconocimientos as $r): ?>
          <div class="col">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body">
                <h3 class="h6 fw-bold"><?= htmlspecialchars($r['titulo']) ?></h3>
                <p class="small text-muted mb-2"><?= $r['tipo'] === 'evento' ? 'Evento' : 'Curso' ?> · <?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha_emision']))) ?></p>
                <a href="../certificado.php?codigo=<?= urlencode($r['codigo']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Ver</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$reconocimientos): ?>
          <p class="text-muted">Todavía no tienes reconocimientos. Completa un curso o asiste a un evento para obtener el primero.</p>
        <?php endif; ?>
      </div>

      <h2 class="h5 fw-bold mb-3">Explora el contenido</h2>
      <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <input type="search" id="buscarContenido" class="form-control" style="max-width:280px;" placeholder="Buscar cursos, eventos, tienda, noticias...">
        <div class="btn-group flex-wrap" role="group" id="filtrosTipoContenido">
          <button type="button" class="btn btn-sm filtro-tipo active" data-tipo="Todos" style="background:#171717;color:#fff;">Todos</button>
          <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Cursos">🎓 Cursos</button>
          <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Eventos">📅 Eventos</button>
          <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Tienda">🛍️ Tienda</button>
          <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Noticias">📰 Noticias</button>
        </div>
        <select id="ordenContenido" class="form-select form-select-sm ms-auto" style="max-width:160px;">
          <option value="fecha">Más nuevo</option>
          <option value="alfabetico">A-Z</option>
        </select>
      </div>
      <div class="row row-cols-1 row-cols-md-3 g-4" id="gridContenido">
        <?php foreach ($contenidoExplorable as $item): ?>
          <div class="col item-contenido" data-tipo="<?= htmlspecialchars($item['tipo']) ?>" data-titulo="<?= htmlspecialchars(mb_strtolower($item['titulo'])) ?>" data-fecha="<?= htmlspecialchars($item['fecha']) ?>">
            <a href="<?= htmlspecialchars($item['href']) ?>" class="card h-100 text-decoration-none border-0 shadow-sm">
              <img src="<?= htmlspecialchars($item['imagen'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:140px;object-fit:cover;" alt="">
              <div class="card-body">
                <span class="badge rounded-pill mb-2" style="background:#fff3e0;color:#c96a00;"><?= $item['icono'] ?> <?= htmlspecialchars($item['tipo']) ?></span>
                <h3 class="h6 fw-bold text-body mb-0"><?= htmlspecialchars($item['titulo']) ?></h3>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
      <p id="sinResultadosContenido" class="text-muted text-center mt-4 d-none">No encontramos contenido que coincida con tu búsqueda.</p>

      <script>
        (function () {
          const buscar = document.getElementById('buscarContenido');
          const orden = document.getElementById('ordenContenido');
          const grid = document.getElementById('gridContenido');
          const sinResultados = document.getElementById('sinResultadosContenido');
          const items = Array.from(grid.querySelectorAll('.item-contenido'));
          let tipoActivo = 'Todos';

          function aplicarFiltro() {
            const q = buscar.value.trim().toLowerCase();
            let visibles = 0;
            items.forEach(function (item) {
              const coincideTipo = tipoActivo === 'Todos' || item.dataset.tipo === tipoActivo;
              const coincideTexto = item.dataset.titulo.includes(q);
              const visible = coincideTipo && coincideTexto;
              item.classList.toggle('d-none', !visible);
              if (visible) visibles++;
            });
            sinResultados.classList.toggle('d-none', visibles > 0);
          }

          function aplicarOrden() {
            const criterio = orden.value;
            items.sort(function (a, b) {
              if (criterio === 'alfabetico') return a.dataset.titulo.localeCompare(b.dataset.titulo);
              return new Date(b.dataset.fecha) - new Date(a.dataset.fecha);
            });
            items.forEach(function (item) { grid.appendChild(item); });
          }

          buscar.addEventListener('input', aplicarFiltro);
          orden.addEventListener('change', aplicarOrden);
          document.querySelectorAll('.filtro-tipo').forEach(function (btn) {
            btn.addEventListener('click', function () {
              document.querySelectorAll('.filtro-tipo').forEach(function (b) {
                b.classList.remove('active');
                b.classList.add('btn-outline-secondary');
                b.style.background = '';
                b.style.color = '';
              });
              btn.classList.add('active');
              btn.classList.remove('btn-outline-secondary');
              btn.style.background = '#171717';
              btn.style.color = '#fff';
              tipoActivo = btn.dataset.tipo;
              aplicarFiltro();
            });
          });
        })();
      </script>

    <?php endif; ?>
  </div>

  <footer class="py-4 text-center text-muted small" style="border-top:1px solid var(--pf-line);">
    &copy; 2026 Reto Arjuna
  </footer>
</body>
</html>
