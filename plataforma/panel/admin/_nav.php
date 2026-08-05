<?php $ra_pagina_actual = basename($_SERVER['SCRIPT_NAME']); ?>
<div class="ra-admin-nav">
  <?php
  $ra_links = [
      ['index.php', 'fa-tachometer-alt', 'Dashboard'],
      ['cursos.php', 'fa-book', 'Cursos'],
      ['eventos.php', 'fa-calendar-alt', 'Eventos'],
      ['productos.php', 'fa-store', 'Productos'],
      ['usuarios.php', 'fa-users', 'Usuarios'],
      ['pagos.php', 'fa-money-bill', 'Pagos'],
      ['cupones.php', 'fa-tags', 'Cupones'],
      ['foro_categorias.php', 'fa-comments', 'Foro'],
      ['actividades.php', 'fa-praying-hands', 'Actividades'],
      ['noticias.php', 'fa-newspaper', 'Noticias'],
      ['reportes.php', 'fa-chart-bar', 'Reportes'],
  ];
  foreach ($ra_links as [$href, $icono, $texto]):
  ?>
    <a class="<?= $ra_pagina_actual === $href ? 'activo' : '' ?>" href="<?= $href ?>">
      <i class="fas <?= $icono ?>"></i> <?= $texto ?>
    </a>
  <?php endforeach; ?>
</div>
