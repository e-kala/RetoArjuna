<?php $ra_pagina_actual = basename($_SERVER['SCRIPT_NAME']); ?>
<div class="ra-admin-nav">
  <?php
  $ra_links = [
      ['index.php', 'bi-speedometer2', 'Dashboard'],
      ['cursos.php', 'bi-book', 'Cursos'],
      ['eventos.php', 'bi-calendar-event', 'Eventos'],
      ['productos.php', 'bi-shop', 'Productos'],
      ['usuarios.php', 'bi-people', 'Usuarios'],
      ['pagos.php', 'bi-cash-coin', 'Pagos'],
      ['foro_categorias.php', 'bi-chat-square-text', 'Categorías Foro'],
      ['foro_moderacion.php', 'bi-shield-check', 'Moderación Foro'],
      ['actividades.php', 'bi-hands', 'Actividades'],
      ['noticias.php', 'bi-newspaper', 'Noticias'],
      ['membresias.php', 'bi-award', 'Membresías'],
      ['navbar_links.php', 'bi-list', 'Navbar'],
      ['reportes.php', 'bi-bar-chart', 'Reportes'],
  ];
  foreach ($ra_links as [$href, $icono, $texto]):
  ?>
    <a class="<?= $ra_pagina_actual === $href ? 'activo' : '' ?>" href="<?= $href ?>">
      <i class="bi <?= $icono ?>"></i> <?= $texto ?>
    </a>
  <?php endforeach; ?>
</div>
