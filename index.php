<?php
// Home de la plataforma. Usa la misma sesión de auth.php (cookie compartida
// en todo el sitio) para saber si ya hay alguien logueado — Flarum sigue
// siendo la única fuente de credenciales, esto solo lee el estado ya resuelto.
require_once __DIR__ . '/plataforma/backend/auth.php';
require_once __DIR__ . '/plataforma/backend/seo.php';

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
  <?php seo_render_meta([
    'titulo' => 'Reto Arjuna — claridad para avanzar',
    'descripcion' => 'Aprendizaje, práctica y acompañamiento para comprender lo que estás viviendo y dar un siguiente paso posible.',
    'imagen' => seo_url_absoluta('/plataforma/digital-creative/img/banner.jpg'),
    'url' => seo_url_absoluta('/'),
    'tipo' => 'website',
  ]); ?>
  <link rel="icon" href="plataforma/digital-creative/img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="plataforma/assets/css/platform.css?v=3.4">
</head>
<body class="pf-body pf-home-body">

  <?php $navPrefijo = ''; include 'plataforma/content/navbar.php'; ?>

  <section class="pf-home-hero">
    <div class="container">
      <div class="pf-home-hero-shell">
        <div class="pf-home-hero-copy">
          <span class="pf-home-kicker"><i class="bi bi-compass"></i> Tu camino empieza con una pregunta</span>
          <h1>
            <?php if ($usuario): ?>
              Hola, <?= htmlspecialchars((string) $usuario['username']) ?>. ¿Qué necesitas fortalecer hoy?
            <?php else: ?>
              Encuentra claridad para avanzar, incluso cuando no tienes todas las respuestas
            <?php endif; ?>
          </h1>
          <p>Arjuna reúne aprendizaje, práctica y acompañamiento para ayudarte a comprender lo que estás viviendo y convertir esa claridad en un siguiente paso posible.</p>
          <div class="pf-home-hero-actions">
            <?php if ($usuario): ?>
              <a class="pf-btn pf-btn-primary pf-btn-lg" href="<?= htmlspecialchars($plataformaUrl) ?>/panel/<?= $usuario['rol'] === 'admin' ? 'index.php' : 'dashboard.php' ?>">Continuar mi camino <i class="bi bi-arrow-right"></i></a>
              <a class="pf-btn pf-btn-outline pf-btn-lg" href="#elige-tu-camino">Elegir otra ruta</a>
            <?php else: ?>
              <a class="pf-btn pf-btn-primary pf-btn-lg" href="#elige-tu-camino">Quiero saber por dónde empezar <i class="bi bi-arrow-down"></i></a>
              <a class="pf-btn pf-btn-outline pf-btn-lg" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=registro">Crear mi cuenta gratis</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="pf-home-hero-visual">
          <img src="plataforma/digital-creative/img/banner.jpg" alt="Una brújula sobre un mapa, símbolo de orientación en el camino">
          <div class="pf-home-guide-card">
            <span>Tu recorrido en Arjuna</span>
            <ol>
              <li><b>Reconoce</b> dónde estás</li>
              <li><b>Elige</b> una práctica</li>
              <li><b>Avanza</b> con acompañamiento</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="pf-home-intro">
    <div class="container">
      <p><strong>No tienes que recorrer todo a la vez.</strong> Elige la opción que más se parece a lo que buscas hoy; podrás cambiar de camino cuando lo necesites.</p>
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

  <section id="elige-tu-camino" class="pf-path-section">
    <div class="container py-4">
      <div class="pf-section-title mb-5">
        <span class="pf-home-kicker">Elige según tu momento</span>
        <h2>¿Qué te gustaría encontrar hoy?</h2>
        <p>Hay distintas puertas de entrada. Estas tres rutas te ayudan a comenzar sin perderte entre opciones.</p>
      </div>
      <div class="pf-path-grid">
        <a class="pf-path-card pf-path-card-featured" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=proximo_evento">
          <span class="pf-path-number">01</span><i class="bi bi-compass pf-path-icon"></i>
          <p class="pf-path-label">Necesito claridad y un punto de partida</p>
          <h3>Vivir el próximo Reto Arjuna</h3>
          <p>Una experiencia guiada para reconocer tus bloqueos, ordenar lo importante y dar pasos concretos acompañado.</p>
          <span class="pf-path-link">Conocer el próximo reto <i class="bi bi-arrow-right"></i></span>
        </a>
        <a class="pf-path-card" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=cursos">
          <span class="pf-path-number">02</span><i class="bi bi-journal-bookmark pf-path-icon"></i>
          <p class="pf-path-label">Quiero profundizar a mi propio ritmo</p>
          <h3>Aprender con un curso</h3>
          <p>Elige un tema, avanza por lecciones y vuelve a la práctica cuando lo necesites. Tu progreso queda guardado.</p>
          <span class="pf-path-link">Ver cursos disponibles <i class="bi bi-arrow-right"></i></span>
        </a>
        <a class="pf-path-card" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=membresia">
          <span class="pf-path-number">03</span><i class="bi bi-people pf-path-icon"></i>
          <p class="pf-path-label">Busco constancia y acompañamiento</p>
          <h3>Caminar en comunidad</h3>
          <p>Encuentros, materiales y una comunidad que te ayudan a sostener lo aprendido más allá de un impulso inicial.</p>
          <span class="pf-path-link">Conocer Camino Arjuna <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <p class="pf-path-footnote">¿Ya sabes lo que buscas? También puedes ir directo a <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=eventos">eventos</a>, <a href="plataforma/foro/">foro</a> o <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=tienda">tienda</a>.</p>
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
          $hrefEv = $plataformaUrl . '/index.php?action=evento&slug=' . urlencode($ev['slug']) . ($tieneAccesoEv ? '&ver=1' : '');
          ?>
          <div class="col" style="max-width:360px;">
            <div class="card h-100 border-0 shadow-sm pf-card-clicable" data-href="<?= htmlspecialchars($hrefEv) ?>" style="cursor:pointer;">
              <img src="<?= htmlspecialchars($ev['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
              <div class="card-body d-flex flex-column">
                <h3 class="h5 fw-bold"><?= htmlspecialchars($ev['titulo']) ?></h3>
                <p class="small text-muted mb-1">
                  <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
                  · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
                </p>
                <p class="mb-3 text-muted"><?= $tieneAccesoEv ? 'Ya tienes acceso' : ((int) $ev['gratuito'] === 1 ? 'Participación gratuita' : 'Conoce todos los detalles') ?></p>
                <a class="btn mt-auto" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($hrefEv) ?>"><?= $tieneAccesoEv ? 'Ver evento →' : 'Saber más del evento →' ?></a>
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
          <?php $hrefProducto = $plataformaUrl . '/index.php?action=producto&slug=' . urlencode($p['slug']); ?>
          <div class="col" style="max-width:360px;">
            <div class="card h-100 border-0 shadow-sm pf-card-clicable" data-href="<?= htmlspecialchars($hrefProducto) ?>" style="cursor:pointer;">
              <img src="<?= htmlspecialchars($p['imagen'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
              <div class="card-body d-flex flex-column">
                <h3 class="h5 fw-bold"><?= htmlspecialchars($p['nombre']) ?></h3>
                <p class="mb-3">$<?= number_format((float) $p['precio'], 2) ?> MXN</p>
                <a class="btn btn-outline-secondary mt-auto" href="<?= htmlspecialchars($hrefProducto) ?>">Ver producto</a>
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
        <a class="btn btn-lg" style="background:#f7931e;color:#fff;" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=proximo_evento">Ver el programa</a>
      </div>
    </div>
  </section>

  <?php $navPrefijo = ''; include 'plataforma/content/footer.php'; ?>

  <script>
    document.addEventListener('click', function (event) {
      const tarjeta = event.target.closest('.pf-card-clicable');
      if (!tarjeta || event.target.closest('a, button')) return;
      window.location.href = tarjeta.dataset.href;
    });
  </script>

</body>
</html>
