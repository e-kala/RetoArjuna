<?php
// $page_title (string, opcional) puede definirse antes de incluir este archivo.
$usuarioForo = current_user();
$noLeidas = $usuarioForo ? foro_notificaciones_no_leidas($usuarioForo['id']) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title ?? 'Foro') ?> — Reto Arjuna</title>
  <link rel="icon" href="../digital-creative/img/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/platform.css?v=1.0">
  <link rel="stylesheet" href="assets/foro.css?v=1.0">
</head>
<body class="pf-body">

  <nav class="pf-nav">
    <div class="pf-container">
      <a href="../index.php" class="pf-logo pf-logo-img">
        <img src="../digital-creative/img/logo.png" alt="Reto Arjuna">
        <span>Reto Arjuna</span>
      </a>

      <ul class="pf-nav-links">
        <li><a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=cursos">Cursos</a></li>
        <li><a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=eventos">Eventos</a></li>
        <li><a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=actividades">Actividades</a></li>
        <li><a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=noticias">Noticias</a></li>
        <li><a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=tienda">Tienda</a></li>
        <li><a href="index.php" class="active">Foro</a></li>
      </ul>

      <form action="buscar.php" method="get" class="pf-forum-search-nav">
        <i class="bi bi-search"></i>
        <input type="search" name="q" placeholder="Buscar en el foro…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      </form>

      <div class="pf-nav-session">
        <?php if ($usuarioForo): ?>
          <a class="pf-forum-bell" href="notificaciones.php" title="Notificaciones">
            <i class="bi bi-bell-fill"></i><?php if ($noLeidas > 0): ?><span class="pf-forum-bell-badge"><?= $noLeidas > 9 ? '9+' : $noLeidas ?></span><?php endif; ?>
          </a>
          <div class="pf-user-menu">
            <button class="pf-user-chip">
              <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $usuarioForo['username'], 0, 1))) ?></span>
              <?= htmlspecialchars((string) $usuarioForo['username']) ?>
            </button>
            <div class="pf-user-dropdown">
              <a href="<?= htmlspecialchars(BASE_URL) ?>/panel/index.php">Mi panel</a>
              <a href="<?= htmlspecialchars(BASE_URL) ?>/backend/logout.php">Salir</a>
            </div>
          </div>
        <?php else: ?>
          <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=ingreso">Iniciar sesión</a>
          <a class="pf-btn pf-btn-primary" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=registro">Registrarse</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <main class="pf-forum-main">
    <div class="pf-container">
