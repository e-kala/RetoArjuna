<?php $ra_pagina_actual = basename($_SERVER['SCRIPT_NAME']); ?>
<div class="ra-admin-nav">
  <?php
  $ra_links = [
      ['index.php', 'bi-speedometer2', 'Dashboard'],
      ['cursos.php', 'bi-book', 'Cursos'],
      ['eventos.php', 'bi-calendar-event', 'Eventos'],
      ['lista_espera_eventos.php', 'bi-envelope-paper', 'Lista de espera'],
      ['productos.php', 'bi-shop', 'Productos'],
      ['usuarios.php', 'bi-people', 'Usuarios'],
      ['inactividad.php', 'bi-hourglass-split', 'Inactividad'],
      ['pagos.php', 'bi-cash-coin', 'Pagos'],
      ['certificados.php', 'bi-patch-check', 'Certificados'],
      ['foro.php', 'bi-chat-square-text', 'Foro'],
      ['actividades.php', 'bi-hands', 'Actividades'],
      ['noticias.php', 'bi-newspaper', 'Noticias'],
      ['membresias.php', 'bi-award', 'Membresías'],
      ['promociones.php', 'bi-percent', 'Promociones'],
      ['cupones.php', 'bi-tag', 'Cupones'],
      ['regalos.php', 'bi-gift', 'Regalos'],
      ['navbar_links.php', 'bi-list', 'Navbar'],
      ['landing_pages.php', 'bi-file-earmark-code', 'Landing pages'],
      ['notificaciones_config.php', 'bi-bell', 'Notificaciones'],
      ['email_campanas.php', 'bi-envelope-paper-fill', 'Campañas de correo'],
      ['email_plantillas.php', 'bi-file-earmark-text', 'Plantillas de correo'],
      ['reportes.php', 'bi-bar-chart', 'Reportes'],
  ];
  foreach ($ra_links as [$href, $icono, $texto]):
  ?>
    <a class="<?= $ra_pagina_actual === $href ? 'activo' : '' ?>" href="<?= $href ?>">
      <i class="bi <?= $icono ?>"></i> <?= $texto ?>
    </a>
  <?php endforeach; ?>
</div>
