<?php
// Home de la plataforma. Usa la misma sesión de auth.php (cookie compartida
// en todo el sitio) para saber si ya hay alguien logueado — Flarum sigue
// siendo la única fuente de credenciales, esto solo lee el estado ya resuelto.
require_once __DIR__ . '/plataforma/backend/auth.php';

$usuario = current_user();
$plataformaUrl = BASE_URL; // .../plataforma

$proximosEventos = $conn->query(
    "SELECT titulo, slug, tipo, ubicacion, fecha_inicio, precio, gratuito, imagen_portada
     FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW() ORDER BY fecha_inicio ASC LIMIT 3"
)->fetch_all(MYSQLI_ASSOC);

$productosDestacados = $conn->query(
    'SELECT nombre, slug, tipo, precio, imagen FROM productos WHERE activo = 1 ORDER BY created_at DESC LIMIT 3'
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reto Arjuna — Plataforma</title>
  <meta name="description" content="Cursos, eventos, foro y tienda del Reto Arjuna, todo en un mismo lugar.">
  <link rel="icon" href="digital-creative/img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/platform.css?v=1.2">
</head>
<body class="pf-body">

  <?php $navPrefijo = ''; include 'plataforma/content/navbar.php'; ?>

  <section class="py-5">
    <div class="container py-4">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <span class="badge rounded-pill text-uppercase mb-3" style="background:#fff3e0;color:#c96a00;font-size:12px;letter-spacing:.06em;padding:8px 16px;">Un espacio guiado, no solo contenido</span>
          <h1 class="display-5 fw-bold mb-3">
            <?php if ($usuario): ?>
              Hola, <?= htmlspecialchars((string) $usuario['username']) ?> — qué bueno tenerte de vuelta 👋
            <?php else: ?>
              Bienvenidos al ecosistema Arjuna
            <?php endif; ?>
          </h1>
          <p class="fs-5 text-muted mb-4">No es solo contenido — es un espacio guiado, con personas reales acompañando cada paso: cursos, comunidad, encuentros y práctica continua, todo en un mismo lugar.</p>
          <div class="d-flex gap-3 flex-wrap">
            <?php if ($usuario): ?>
              <a class="btn btn-lg" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($plataformaUrl) ?>/panel/<?= $usuario['rol'] === 'admin' ? 'index.php' : 'dashboard.php' ?>">Ir a mi panel</a>
              <a class="btn btn-outline-secondary btn-lg" href="foro/">Ir al foro</a>
            <?php else: ?>
              <a class="btn btn-lg" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=registro">Regístrate gratis</a>
              <a class="btn btn-outline-secondary btn-lg" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=cursos">Explorar cursos</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-lg-6">
          <img src="digital-creative/img/banner.jpg" alt="Una brújula sobre un mapa — orientación en el camino" class="img-fluid rounded-4 shadow">
        </div>
      </div>
    </div>
  </section>

  <section class="py-5" style="background:var(--pf-surface);border-top:1px solid var(--pf-line);border-bottom:1px solid var(--pf-line);">
    <div class="container py-4">
      <div class="text-center mb-5">
        <h2 class="fw-bold">Explora el ecosistema</h2>
        <p class="text-muted">Un mapa rápido de todo lo que tienes disponible, en un solo vistazo.</p>
      </div>
      <div class="row row-cols-2 row-cols-md-4 g-3">
        <?php
        $mapaEcosistema = [
          ['icono' => '🎓', 'bg' => '#fff3e0', 'color' => '#c96a00', 'titulo' => 'Cursos', 'texto' => 'Aprende a tu ritmo con lecciones, quizzes y certificado al completar.', 'href' => $plataformaUrl . '/index.php?action=cursos'],
          ['icono' => '📅', 'bg' => '#e6f4ea', 'color' => '#1e7d3c', 'titulo' => 'Eventos', 'texto' => 'Encuentros en línea y presenciales — inscríbete y recibe tu reconocimiento.', 'href' => $plataformaUrl . '/index.php?action=eventos'],
          ['icono' => '💬', 'bg' => '#e6f0fb', 'color' => '#1c5fa8', 'titulo' => 'Foro', 'texto' => 'Conversa, pregunta y comparte con la comunidad del Reto Arjuna.', 'href' => 'foro/'],
          ['icono' => '🛍️', 'bg' => '#fdeaea', 'color' => '#c0392b', 'titulo' => 'Tienda', 'texto' => 'Merchandise e infoproductos físicos y digitales para tu práctica.', 'href' => $plataformaUrl . '/index.php?action=tienda'],
          ['icono' => '🙏', 'bg' => '#fff3e0', 'color' => '#c96a00', 'titulo' => 'Actividades', 'texto' => 'Prácticas guiadas para sostener el método en tu día a día.', 'href' => $plataformaUrl . '/index.php?action=actividades'],
          ['icono' => '📰', 'bg' => '#e6f0fb', 'color' => '#1c5fa8', 'titulo' => 'Noticias', 'texto' => 'Avisos y novedades de la comunidad, siempre al día.', 'href' => $plataformaUrl . '/index.php?action=noticias'],
          ['icono' => '🧭', 'bg' => '#eeeeee', 'color' => '#444444', 'titulo' => 'Mi panel', 'texto' => 'Tu progreso, tus compras, tu membresía — todo en un solo lugar.', 'href' => $plataformaUrl . '/panel/index.php' . ($usuario ? '' : '?action=ingreso')],
        ];
        ?>
        <?php foreach ($mapaEcosistema as $item): ?>
          <div class="col">
            <a href="<?= htmlspecialchars($item['href']) ?>" class="card h-100 text-decoration-none shadow-sm border-0" style="transition:transform .15s ease;">
              <div class="card-body">
                <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width:48px;height:48px;font-size:22px;background:<?= $item['bg'] ?>;color:<?= $item['color'] ?>;"><?= $item['icono'] ?></div>
                <h3 class="h6 fw-bold text-body"><?= htmlspecialchars($item['titulo']) ?></h3>
                <p class="small text-muted mb-0"><?= htmlspecialchars($item['texto']) ?></p>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="py-5">
    <div class="container py-4">
      <div class="text-center mb-5">
        <h2 class="fw-bold">No caminas solo</h2>
        <p class="text-muted">Personas reales sosteniendo el proceso contigo, no solo un curso grabado.</p>
      </div>
      <div class="row row-cols-1 row-cols-md-3 g-4">
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <img src="digital-creative/img/team-1.jpg" alt="Srivas" class="rounded-circle object-fit-cover mb-3" style="width:96px;height:96px;border:3px solid var(--pf-bg);">
              <h3 class="h5 fw-bold mb-0">Srivas</h3>
              <p class="small fw-bold mb-2" style="color:#c96a00;">Facilitador principal</p>
              <p class="small text-muted mb-0">Traduce enseñanzas clásicas en herramientas prácticas para sostener decisiones reales cuando hay presión en la vida diaria.</p>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <img src="digital-creative/img/team-2.jpg" alt="Nimai" class="rounded-circle object-fit-cover mb-3" style="width:96px;height:96px;border:3px solid var(--pf-bg);">
              <h3 class="h5 fw-bold mb-0">Nimai</h3>
              <p class="small fw-bold mb-2" style="color:#c96a00;">Encuentros en vivo y soporte</p>
              <p class="small text-muted mb-0">Sostiene el proceso con claridad y ejecución: resuelve dudas, modera el foro y acompaña la práctica aplicada al día.</p>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <img src="digital-creative/img/team-3.jpg" alt="Krishna" class="rounded-circle object-fit-cover mb-3" style="width:96px;height:96px;border:3px solid var(--pf-bg);">
              <h3 class="h5 fw-bold mb-0">Krishna</h3>
              <p class="small fw-bold mb-2" style="color:#c96a00;">Facilitador de encuentros en vivo</p>
              <p class="small text-muted mb-0">Co-guía los encuentros y ayuda a aterrizar la práctica diaria para convertir claridad en acciones posibles y sostenibles.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($proximosEventos): ?>
  <section class="py-5">
    <div class="container py-4">
      <div class="text-center mb-5">
        <h2 class="fw-bold">Próximos eventos</h2>
        <p class="text-muted">Inscríbete antes de que se agote el cupo.</p>
      </div>
      <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
        <?php foreach ($proximosEventos as $ev): ?>
          <div class="col" style="max-width:360px;">
            <div class="card h-100 border-0 shadow-sm">
              <img src="<?= htmlspecialchars($ev['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
              <div class="card-body d-flex flex-column">
                <h3 class="h5 fw-bold"><?= htmlspecialchars($ev['titulo']) ?></h3>
                <p class="small text-muted mb-1">
                  <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
                  · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
                </p>
                <p class="mb-3"><?= (int) $ev['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $ev['precio'], 2) . ' MXN' ?></p>
                <a class="btn mt-auto" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>">Inscribirme</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($productosDestacados): ?>
  <section class="py-5" style="background:var(--pf-surface);border-top:1px solid var(--pf-line);border-bottom:1px solid var(--pf-line);">
    <div class="container py-4">
      <div class="text-center mb-5">
        <h2 class="fw-bold">De la tienda</h2>
        <p class="text-muted">Merchandise e infoproductos disponibles ahora.</p>
      </div>
      <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
        <?php foreach ($productosDestacados as $p): ?>
          <div class="col" style="max-width:360px;">
            <div class="card h-100 border-0 shadow-sm">
              <img src="<?= htmlspecialchars($p['imagen'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
              <div class="card-body d-flex flex-column">
                <h3 class="h5 fw-bold"><?= htmlspecialchars($p['nombre']) ?></h3>
                <p class="mb-3">$<?= number_format((float) $p['precio'], 2) ?> MXN</p>
                <a class="btn btn-outline-secondary mt-auto" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=producto&slug=<?= urlencode($p['slug']) ?>">Ver producto</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="py-5">
    <div class="container py-4">
      <div class="p-5 rounded-4 text-center text-white" style="background:#1a1a1a;">
        <h2 class="fw-bold">¿Buscas el próximo Reto Arjuna?</h2>
        <p class="mb-4" style="opacity:.75;">Entrenamiento en vivo de 10 días para sostener lo más importante enmedio del caos.</p>
        <a class="btn btn-lg" style="background:#f7931e;color:#fff;" href="reto-arjuna.html">Ver el programa</a>
      </div>
    </div>
  </section>

  <footer class="pf-footer">
    <div class="pf-container">
      <nav class="pf-footer-links">
        <?php foreach (obtener_navbar_links('footer') as $footerLink): ?>
          <a href="<?= htmlspecialchars(navbar_href($footerLink['url'], '')) ?>" <?= (int) $footerLink['abre_nueva_pestana'] === 1 ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($footerLink['texto']) ?></a>
        <?php endforeach; ?>
        <?php if (!$usuario): ?>
          <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=ingreso">Iniciar sesión</a>
          <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=registro">Registrarse</a>
        <?php endif; ?>
      </nav>
      <p class="pf-footer-copy">&copy; 2026 Reto Arjuna</p>
    </div>
  </footer>

</body>
</html>
