<?php
// Cabecera compartida de todo el panel de administración. Cada archivo de
// panel/admin/*.php debe hacer require_once '../../backend/auth.php'; require_role('admin');
// ANTES de incluir este archivo (así el guard de acceso corre primero).
// Ya NO usa SB Admin 2 (plantilla de terceros retirada por completo) — usa el
// mismo Bootstrap + platform.css (dorado/crema) que el resto del sitio, y el
// navbar compartido de siempre, para que un admin nunca sienta que salió de
// "la plataforma" al entrar a las herramientas de administración.
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · Reto Arjuna</title>
  <link rel="icon" href="../../digital-creative/img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../../assets/css/platform.css?v=1.2">
</head>
<body class="pf-body">
  <?php $navPrefijo = '../../../'; $navActivoContiene = ''; include __DIR__ . '/../../content/navbar.php'; ?>

  <div class="container-fluid" style="max-width:1200px;margin-top:120px;margin-bottom:60px;padding-left:24px;padding-right:24px;">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <a href="../index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Panel admin</a>
        <h1 class="h4 fw-bold mb-0 ms-2"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
      </div>
    </div>
    <?php include __DIR__ . '/_nav.php'; ?>
