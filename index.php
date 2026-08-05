<?php
// Home de la plataforma. Usa la misma sesión de auth.php (cookie compartida
// en todo el sitio) para saber si ya hay alguien logueado — Flarum sigue
// siendo la única fuente de credenciales, esto solo lee el estado ya resuelto.
require_once __DIR__ . '/plataforma/backend/auth.php';

$usuario = current_user();
$plataformaUrl = BASE_URL; // .../plataforma

$proximosEventos = $conn->query(
    "SELECT titulo, slug, tipo, ubicacion, fecha_inicio, precio, gratuito
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
  <link rel="stylesheet" href="assets/css/platform.css?v=1.0">
</head>
<body class="pf-body">

  <nav class="pf-nav">
    <div class="pf-container">
      <a href="index.php" class="pf-logo pf-logo-img">
        <img src="digital-creative/img/logo.png" alt="Reto Arjuna">
        <span>Reto Arjuna</span>
      </a>

      <ul class="pf-nav-links">
        <li><a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=cursos">Cursos</a></li>
        <li><a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=actividades">Actividades</a></li>
        <li><a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=noticias">Noticias</a></li>
        <li><a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=eventos">Eventos</a></li>
        <li><a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=tienda">Tienda</a></li>
        <li><a href="foro/">Foro</a></li>
        <li><a href="reto-arjuna.html">Reto Arjuna</a></li>
      </ul>

      <div class="pf-nav-session">
        <?php if ($usuario): ?>
          <div class="pf-user-menu">
            <button class="pf-user-chip">
              <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $usuario['username'], 0, 1))) ?></span>
              <?= htmlspecialchars((string) $usuario['username']) ?>
            </button>
            <div class="pf-user-dropdown">
              <a href="<?= htmlspecialchars($plataformaUrl) ?>/panel/index.php">Mi panel</a>
              <a href="<?= htmlspecialchars($plataformaUrl) ?>/backend/logout.php">Salir</a>
            </div>
          </div>
        <?php else: ?>
          <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=ingreso">Iniciar sesión</a>
          <a class="pf-btn pf-btn-primary" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=registro">Registrarse</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <section class="pf-hero">
    <div class="pf-container">
      <span class="pf-eyebrow">Bienvenido al ecosistema</span>
      <h1>
        <?php if ($usuario): ?>
          Hola, <?= htmlspecialchars((string) $usuario['username']) ?> 👋
        <?php else: ?>
          Un espacio para aprender, conectar y crecer
        <?php endif; ?>
      </h1>
      <p>Cursos, eventos, comunidad y tienda del Reto Arjuna, todo en un mismo lugar.</p>
      <div class="pf-hero-actions">
        <?php if ($usuario): ?>
          <a class="pf-btn pf-btn-primary pf-btn-lg" href="<?= htmlspecialchars($plataformaUrl) ?>/panel/index.php">Ir a mi panel</a>
          <a class="pf-btn pf-btn-outline pf-btn-lg" href="foro/">Ir al foro</a>
        <?php else: ?>
          <a class="pf-btn pf-btn-primary pf-btn-lg" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=registro">Únete gratis</a>
          <a class="pf-btn pf-btn-outline pf-btn-lg" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=cursos">Explorar cursos</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="pf-section">
    <div class="pf-container">
      <div class="pf-section-title">
        <h2>Todo en un mismo lugar</h2>
        <p>Cuatro espacios, una sola cuenta.</p>
      </div>
      <div class="pf-grid-3">
        <div class="pf-card">
          <div class="pf-card-icon">🎓</div>
          <h3>Cursos</h3>
          <p>Aprende a tu ritmo con lecciones, quizzes y certificados al completar cada curso.</p>
          <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=cursos">Ver cursos</a>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">📅</div>
          <h3>Eventos</h3>
          <p>Encuentros en línea y presenciales — inscríbete y recibe tu reconocimiento al asistir.</p>
          <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=eventos">Ver eventos</a>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">💬</div>
          <h3>Foro</h3>
          <p>Conversa, pregunta y comparte con la comunidad del Reto Arjuna.</p>
          <a class="pf-btn pf-btn-outline" href="foro/">Entrar al foro</a>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">🛍️</div>
          <h3>Tienda</h3>
          <p>Merchandise e infoproductos para acompañar tu práctica.</p>
          <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=tienda">Ver tienda</a>
        </div>
      </div>
    </div>
  </section>

  <?php if ($proximosEventos): ?>
  <section class="pf-section">
    <div class="pf-container">
      <div class="pf-section-title">
        <h2>Próximos eventos</h2>
        <p>Inscríbete antes de que se agote el cupo.</p>
      </div>
      <div class="pf-grid-3">
        <?php foreach ($proximosEventos as $ev): ?>
          <div class="pf-card">
            <h3><?= htmlspecialchars($ev['titulo']) ?></h3>
            <p style="color:var(--pf-muted);font-size:13px;">
              <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
              · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
            </p>
            <p><?= (int) $ev['gratuito'] === 1 ? 'Gratuito' : '$' . number_format((float) $ev['precio'], 2) . ' MXN' ?></p>
            <a class="pf-btn pf-btn-primary" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>">Inscribirme</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($productosDestacados): ?>
  <section class="pf-section">
    <div class="pf-container">
      <div class="pf-section-title">
        <h2>De la tienda</h2>
        <p>Merchandise e infoproductos disponibles ahora.</p>
      </div>
      <div class="pf-grid-3">
        <?php foreach ($productosDestacados as $p): ?>
          <div class="pf-card">
            <h3><?= htmlspecialchars($p['nombre']) ?></h3>
            <p>$<?= number_format((float) $p['precio'], 2) ?> MXN</p>
            <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=producto&slug=<?= urlencode($p['slug']) ?>">Ver producto</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="pf-section">
    <div class="pf-container">
      <div class="pf-callout">
        <h2>¿Buscas el Reto Arjuna Marzo 2026?</h2>
        <p>Entrenamiento en vivo de 10 días para sostener lo importante bajo presión.</p>
        <a class="pf-btn pf-btn-primary pf-btn-lg" href="reto-arjuna.html">Ver el programa</a>
      </div>
    </div>
  </section>

  <footer class="pf-footer">
    <div class="pf-container">
      <nav class="pf-footer-links">
        <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=cursos">Cursos</a>
        <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=actividades">Actividades</a>
        <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=noticias">Noticias</a>
        <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=eventos">Eventos</a>
        <a href="<?= htmlspecialchars($plataformaUrl) ?>/index.php?action=tienda">Tienda</a>
        <a href="foro/">Foro</a>
        <a href="reto-arjuna.html">Reto Arjuna</a>
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
