<nav class="pf-nav">
  <div class="pf-container">
    <a href="../index.php" class="pf-logo pf-logo-img">
      <img src="../digital-creative/img/logo.png" alt="Reto Arjuna">
      <!--<span>Reto Arjuna</span>-->
    </a>

    <ul class="pf-nav-links">
      <li><a href="?action=cursos" class="<?= ($action ?? '') === 'cursos' ? 'active' : '' ?>">Cursos</a></li>
      <li><a href="?action=actividades" class="<?= ($action ?? '') === 'actividades' ? 'active' : '' ?>">Actividades</a></li>
      <li><a href="?action=noticias" class="<?= ($action ?? '') === 'noticias' ? 'active' : '' ?>">Noticias</a></li>
      <li><a href="?action=eventos" class="<?= ($action ?? '') === 'eventos' ? 'active' : '' ?>">Eventos</a></li>
      <li><a href="?action=tienda" class="<?= ($action ?? '') === 'tienda' ? 'active' : '' ?>">Tienda</a></li>
      <li><a href="../foro/">Foro</a></li>
      <li><a href="../reto-arjuna.html">Reto Arjuna</a></li>
    </ul>

    <div class="pf-nav-session">
      <?php $usuarioNav = current_user(); ?>
      <?php if ($usuarioNav): ?>
        <div class="pf-user-menu">
          <button class="pf-user-chip">
            <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $usuarioNav['username'], 0, 1))) ?></span>
            <?= htmlspecialchars((string) $usuarioNav['username']) ?>
          </button>
          <div class="pf-user-dropdown">
            <a href="panel/index.php">Mi panel</a>
            <a href="backend/logout.php">Salir</a>
          </div>
        </div>
      <?php else: ?>
        <a class="pf-btn pf-btn-outline" href="?action=ingreso">Iniciar sesión</a>
        <a class="pf-btn pf-btn-primary" href="?action=registro">Registrarse</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
