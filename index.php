<?php
// Home de la plataforma. Usa la misma sesión de auth.php (cookie compartida
// en todo el sitio) para saber si ya hay alguien logueado — Flarum sigue
// siendo la única fuente de credenciales, esto solo lee el estado ya resuelto.
require_once __DIR__ . '/plataforma/backend/auth.php';

$usuario = current_user();
$plataformaUrl = BASE_URL; // .../plataforma
$esMiembro = $usuario ? usuario_tiene_membresia_activa($usuario['id']) : false;

// Un evento exclusivo para miembros (solo_miembros=1) no debe aparecer aquí
// para quien no es miembro — ni el título ni la imagen, nada, para que no
// se filtre información de un evento que de todos modos no podría ver.
$proximosEventos = $conn->query(
    "SELECT id, titulo, slug, tipo, ubicacion, fecha_inicio, precio, gratuito, imagen_portada
     FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW()"
     . ($esMiembro ? '' : ' AND solo_miembros = 0')
     . " ORDER BY fecha_inicio ASC LIMIT 3"
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
  <link rel="icon" href="plataforma/digital-creative/img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="plataforma/assets/css/platform.css?v=2.2">
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
              <a class="btn btn-outline-secondary btn-lg" href="plataforma/foro/">Ir al foro</a>
            <?php else: ?>
              <a class="btn btn-lg" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=registro">Crea tu Cuenta Arjuna gratis</a>
              <a class="btn btn-outline-secondary btn-lg" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=cursos">Explorar cursos</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-lg-6">
          <img src="plataforma/digital-creative/img/banner.jpg" alt="Una brújula sobre un mapa — orientación en el camino" class="img-fluid rounded-4 shadow">
        </div>
      </div>
    </div>
  </section>

  <?php if (!$usuario): ?>
  <section class="py-5">
    <div class="container py-4">
      <div class="text-center mb-5">
        <h2 class="fw-bold">¿Qué puedes hacer con tu Cuenta Arjuna?</h2>
        <p class="text-muted">Gratis, y es la puerta de entrada a todo el ecosistema.</p>
      </div>
      <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width:48px;height:48px;font-size:22px;background:#fff3e0;color:#c96a00;">🎓</div>
              <h3 class="h6 fw-bold">Tomar cursos a tu ritmo</h3>
              <p class="small text-muted mb-0">Avanza cuando puedas, guarda tu progreso y obtén tu certificado al completar cada curso.</p>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width:48px;height:48px;font-size:22px;background:#e6f4ea;color:#1e7d3c;">📅</div>
              <h3 class="h6 fw-bold">Inscribirte a eventos</h3>
              <p class="small text-muted mb-0">Reserva tu lugar en encuentros en línea y presenciales de la comunidad.</p>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width:48px;height:48px;font-size:22px;background:#e6f0fb;color:#1c5fa8;">💬</div>
              <h3 class="h6 fw-bold">Participar en el foro</h3>
              <p class="small text-muted mb-0">Conversa, pregunta y comparte tu proceso con otras personas del Reto Arjuna.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="text-center">
        <a class="btn btn-lg" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=registro">Crea tu Cuenta Arjuna gratis</a>
      </div>
    </div>
  </section>
  <?php endif; ?>

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
          ['icono' => '💬', 'bg' => '#e6f0fb', 'color' => '#1c5fa8', 'titulo' => 'Foro', 'texto' => 'Conversa, pregunta y comparte con la comunidad del Reto Arjuna.', 'href' => 'plataforma/foro/'],
          ['icono' => '🛍️', 'bg' => '#fdeaea', 'color' => '#c0392b', 'titulo' => 'Tienda', 'texto' => 'Merchandise e infoproductos físicos y digitales para tu práctica.', 'href' => $plataformaUrl . '/index.php?action=tienda'],
          ['icono' => '🙏', 'bg' => '#fff3e0', 'color' => '#c96a00', 'titulo' => 'Actividades', 'texto' => 'Prácticas guiadas para sostener el método en tu día a día.', 'href' => $plataformaUrl . '/index.php?action=actividades'],
          ['icono' => '📰', 'bg' => '#e6f0fb', 'color' => '#1c5fa8', 'titulo' => 'Noticias', 'texto' => 'Avisos y novedades de la comunidad, siempre al día.', 'href' => $plataformaUrl . '/index.php?action=noticias'],
          ['icono' => '🧭', 'bg' => '#eeeeee', 'color' => '#444444', 'titulo' => 'Mi panel', 'texto' => 'Tu progreso, tus compras y tu Camino Arjuna — todo en un solo lugar.', 'href' => $usuario ? $plataformaUrl . '/panel/' . ($usuario['rol'] === 'admin' ? 'index.php' : 'dashboard.php') : $plataformaUrl . '/index.php?action=ingreso&volver=' . urlencode($plataformaUrl . '/panel/dashboard.php')],
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
              <img src="plataforma/digital-creative/img/team-1.jpg" alt="Srivas" class="rounded-circle object-fit-cover mb-3" style="width:96px;height:96px;border:3px solid var(--pf-bg);">
              <h3 class="h5 fw-bold mb-0">Srivas</h3>
              <p class="small fw-bold mb-2" style="color:#c96a00;">Facilitador principal</p>
              <p class="small text-muted mb-0">Traduce enseñanzas clásicas en herramientas prácticas para sostener decisiones reales cuando hay presión en la vida diaria.</p>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <img src="plataforma/digital-creative/img/team-2.jpg" alt="Nimai" class="rounded-circle object-fit-cover mb-3" style="width:96px;height:96px;border:3px solid var(--pf-bg);">
              <h3 class="h5 fw-bold mb-0">Nimai</h3>
              <p class="small fw-bold mb-2" style="color:#c96a00;">Encuentros en vivo y soporte</p>
              <p class="small text-muted mb-0">Sostiene el proceso con claridad y ejecución: resuelve dudas, modera el foro y acompaña la práctica aplicada al día.</p>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm text-center p-3">
            <div class="card-body">
              <img src="plataforma/digital-creative/img/team-3.jpg" alt="Krishna" class="rounded-circle object-fit-cover mb-3" style="width:96px;height:96px;border:3px solid var(--pf-bg);">
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
          <?php
          // IN03 (checklist.txt) — quien ya tiene acceso ve un estado breve
          // de disponibilidad en vez de precio/promoción, aquí mismo en la
          // tarjeta de Inicio (misma lógica que eventos_catalogo.php).
          $tieneAccesoEv = $usuario && usuario_esta_inscrito_evento((int) $usuario['id'], (int) $ev['id']);
          ?>
          <div class="col" style="max-width:360px;">
            <div class="card h-100 border-0 shadow-sm">
              <img src="<?= htmlspecialchars($ev['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
              <div class="card-body d-flex flex-column">
                <h3 class="h5 fw-bold"><?= htmlspecialchars($ev['titulo']) ?></h3>
                <p class="small text-muted mb-1">
                  <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
                  · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
                </p>
                <p class="mb-3"><?= $tieneAccesoEv ? 'Evento disponible' : ((int) $ev['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $ev['precio'], 2) . ' MXN') ?></p>
                <a class="btn mt-auto" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>"><?= $tieneAccesoEv ? 'Ver evento' : 'Inscribirme' ?></a>
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

  <?php $navPrefijo = ''; include 'plataforma/content/footer.php'; ?>

</body>
</html>
