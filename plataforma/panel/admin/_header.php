<?php
// Cabecera compartida de todo el panel de administración. Cada archivo de
// panel/admin/*.php debe hacer require_once '../../backend/auth.php'; require_role('admin');
// ANTES de incluir este archivo (así el guard de acceso corre primero).
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · Reto Arjuna</title>
  <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
  <link href="../css/sb-admin-2.min.css" rel="stylesheet">
  <link href="../css/admin-tema.css" rel="stylesheet">
</head>
<body id="page-top">
  <div id="wrapper">
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
          <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
              <img src="../../../digital-creative/img/logo.png" alt="" style="height:26px;width:auto;">
              &larr; Volver al panel
            </a>
            <span class="navbar-text ms-auto"><?= htmlspecialchars($pageTitle ?? '') ?></span>
          </div>
        </nav>
        <div class="container-fluid">
          <?php include __DIR__ . '/_nav.php'; ?>
