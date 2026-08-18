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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/platform.css?v=1.2">
  <link rel="stylesheet" href="assets/foro.css?v=2.0">
  <?php if (!empty($usaEditorEnriquecido)): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css">
    <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
  <?php endif; ?>
</head>
<body class="pf-body">

  <?php
  ob_start();
  ?>
  <form action="buscar.php" method="get" class="pf-forum-search-nav">
    <i class="bi bi-search"></i>
    <input type="search" name="q" placeholder="Buscar en el foro…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
  </form>
  <?php
  $navExtraTrasLinks = ob_get_clean();

  ob_start();
  if ($usuarioForo):
  ?>
  <a class="pf-forum-bell" href="notificaciones.php" title="Notificaciones">
    <i class="bi bi-bell-fill"></i><?php if ($noLeidas > 0): ?><span class="pf-forum-bell-badge"><?= $noLeidas > 9 ? '9+' : $noLeidas ?></span><?php endif; ?>
  </a>
  <?php
  endif;
  $navExtraEnSesion = ob_get_clean();

  $navPrefijo = '../';
  $navActivoContiene = 'foro/';
  require __DIR__ . '/../../plataforma/content/navbar.php';
  ?>

  <main class="pf-forum-main">
    <div class="pf-container">
