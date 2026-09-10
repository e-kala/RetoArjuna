<?php
// $page_title (string, opcional) puede definirse antes de incluir este archivo.
$usuarioForo = current_user();
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
  <link rel="stylesheet" href="../assets/css/platform.css?v=2.8">
  <link rel="stylesheet" href="assets/foro.css?v=2.0">
  <!-- notify.js (plataforma/content/notify.min.js) necesita jQuery cargado
       ANTES que él — arma su propio "jQuery" global al analizarse (no de
       forma perezosa dentro de una función), así que el orden de estos dos
       <script> no se puede invertir. Ver inc/footer.php: window.pfMostrarToast(). -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
    integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="../content/notify.min.js"></script>
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

  $navPrefijo = '../../';
  $navActivoContiene = 'foro/';
  require __DIR__ . '/../../content/navbar.php';
  ?>

  <main class="pf-forum-main">
    <div class="pf-container">
