<?php
// Navbar único para todo el sitio (raíz, plataforma/ y foro/) — un cambio en los
// links solo se hace en un lugar. Quien incluye este archivo puede definir, ANTES
// del include, estas variables opcionales:
//   $navPrefijo         ruta relativa hasta la raíz del sitio: '' en index.php (raíz),
//                       '../' desde plataforma/ o foro/ (ambas un nivel abajo).
//   $navActivoContiene  substring a buscar en la url guardada para resaltar el link activo.
//   $navExtraTrasLinks  HTML crudo a insertar justo después de la lista de links
//                       (el foro lo usa para su buscador).
//   $navExtraEnSesion   HTML crudo a insertar al inicio del bloque de sesión
//                       (el foro lo usa para la campana de notificaciones).
$navPrefijo = $navPrefijo ?? '';
$navActivoContiene = $navActivoContiene ?? '';
$usuarioNav = current_user();
?>
<nav class="pf-nav">
  <div class="pf-container">
    <a href="<?= htmlspecialchars($navPrefijo) ?>index.php" class="pf-logo pf-logo-img">
      <img src="<?= htmlspecialchars($navPrefijo) ?>digital-creative/img/logo.png" alt="">
      <!--<span>Reto Arjuna</span>-->
    </a>

    <ul class="pf-nav-links">
      <?php foreach (obtener_navbar_links('nav') as $navLink): ?>
        <?php $esActivo = $navActivoContiene !== '' && strpos($navLink['url'], $navActivoContiene) !== false; ?>
        <li><a href="<?= htmlspecialchars(navbar_href($navLink['url'], $navPrefijo)) ?>" class="<?= $esActivo ? 'active' : '' ?>" <?= (int) $navLink['abre_nueva_pestana'] === 1 ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($navLink['texto']) ?></a></li>
      <?php endforeach; ?>
    </ul>

    <?php if (!empty($navExtraTrasLinks)) echo $navExtraTrasLinks; ?>

    <div class="pf-nav-session">
      <?php if (!empty($navExtraEnSesion)) echo $navExtraEnSesion; ?>
      <?php if ($usuarioNav): ?>
        <div class="pf-user-menu">
          <button class="pf-user-chip">
            <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $usuarioNav['username'], 0, 1))) ?></span>
            <?= htmlspecialchars((string) $usuarioNav['username']) ?>
          </button>
          <div class="pf-user-dropdown">
            <?php if ($usuarioNav['rol'] === 'admin'): ?>
              <a href="<?= htmlspecialchars(BASE_URL) ?>/panel/index.php"><i class="bi bi-speedometer2"></i> Panel admin</a>
              <a href="<?= htmlspecialchars(BASE_URL) ?>/panel/dashboard.php"><i class="bi bi-mortarboard"></i> Mi aprendizaje</a>
            <?php else: ?>
              <a href="<?= htmlspecialchars(BASE_URL) ?>/panel/dashboard.php"><i class="bi bi-mortarboard"></i> Mi panel</a>
            <?php endif; ?>
            <div class="pf-dropdown-divider"></div>
            <a href="<?= htmlspecialchars(BASE_URL) ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>
          </div>
        </div>
      <?php else: ?>
        <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=ingreso">Iniciar sesión</a>
        <a class="pf-btn pf-btn-primary" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=registro">Registrarse</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div id="pfToast" class="pf-toast" role="status" aria-live="polite">
  <i class="bi bi-check-circle-fill"></i>
  <div>
    <strong>Sesión cerrada correctamente</strong>
    <div class="pf-toast-sub">¡Vuelve pronto!</div>
  </div>
</div>

<script>
  (function () {
    // Toast de "sesión cerrada" — logout.php redirige aquí con ?sesion=cerrada.
    // Se limpia el parámetro de la URL de inmediato para que no vuelva a
    // mostrarse con un refresh o al navegar hacia atrás/adelante.
    var params = new URLSearchParams(window.location.search);
    if (params.get('sesion') === 'cerrada') {
      var toast = document.getElementById('pfToast');
      params.delete('sesion');
      var resto = params.toString();
      window.history.replaceState({}, '', window.location.pathname + (resto ? '?' + resto : ''));
      requestAnimationFrame(function () { toast.classList.add('mostrar'); });
      setTimeout(function () { toast.classList.remove('mostrar'); }, 4000);
    }
  })();
</script>
