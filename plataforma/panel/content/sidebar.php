<!-- Sidebar IN-->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="?action=inicio">
                <img src="../../digital-creative/img/logo.png" alt="Reto Arjuna" style="height:32px;width:auto;">
                <div class="sidebar-brand-text mx-3">Reto Arjuna</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Mis cursos -->
            <li class="nav-item <?= ($action ?? 'inicio') === 'inicio' ? 'active' : '' ?>">
                <a class="nav-link" href="?action=inicio">
                    <i class="fas fa-fw fa-graduation-cap"></i>
                    <span>Mis cursos</span></a>
            </li>

            <!-- Nav Item - Mis eventos -->
            <li class="nav-item">
                <a class="nav-link" href="?action=inicio#mis-eventos">
                    <i class="fas fa-fw fa-calendar-alt"></i>
                    <span>Mis eventos</span></a>
            </li>

            <!-- Nav Item - Mis reconocimientos -->
            <li class="nav-item">
                <a class="nav-link" href="?action=inicio#mis-reconocimientos">
                    <i class="fas fa-fw fa-award"></i>
                    <span>Mis reconocimientos</span></a>
            </li>

            <!-- Nav Item - Mis compras -->
            <li class="nav-item <?= ($action ?? '') === 'mis_compras' ? 'active' : '' ?>">
                <a class="nav-link" href="?action=mis_compras">
                    <i class="fas fa-fw fa-receipt"></i>
                    <span>Mis compras</span></a>
            </li>

            <!-- Nav Item - Perfil -->
            <li class="nav-item <?= ($action ?? '') === 'perfil' ? 'active' : '' ?>">
                <a class="nav-link" href="?action=perfil">
                    <i class="fas fa-fw fa-user"></i>
                    <span>Perfil</span></a>
            </li>

            <?php if (($usuario['rol'] ?? '') === 'admin'): ?>
              <hr class="sidebar-divider">
              <div class="sidebar-heading">Admin</div>
              <li class="nav-item">
                  <a class="nav-link" href="admin/cursos.php">
                      <i class="fas fa-fw fa-book"></i>
                      <span>Cursos</span></a>
              </li>
              <li class="nav-item">
                  <a class="nav-link" href="admin/usuarios.php">
                      <i class="fas fa-fw fa-users"></i>
                      <span>Usuarios</span></a>
              </li>
              <li class="nav-item">
                  <a class="nav-link" href="admin/pagos.php">
                      <i class="fas fa-fw fa-money-bill"></i>
                      <span>Pagos</span></a>
              </li>
              <li class="nav-item">
                  <a class="nav-link" href="admin/cupones.php">
                      <i class="fas fa-fw fa-tags"></i>
                      <span>Cupones</span></a>
              </li>
              <li class="nav-item">
                  <a class="nav-link" href="admin/foro_categorias.php">
                      <i class="fas fa-fw fa-comments"></i>
                      <span>Foro</span></a>
              </li>
              <li class="nav-item">
                  <a class="nav-link" href="admin/actividades.php">
                      <i class="fas fa-fw fa-praying-hands"></i>
                      <span>Actividades</span></a>
              </li>
              <li class="nav-item">
                  <a class="nav-link" href="admin/noticias.php">
                      <i class="fas fa-fw fa-newspaper"></i>
                      <span>Noticias</span></a>
              </li>
              <li class="nav-item">
                  <a class="nav-link" href="admin/reportes.php">
                      <i class="fas fa-fw fa-chart-bar"></i>
                      <span>Reportes</span></a>
              </li>
            <?php endif; ?>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

            <!-- Sidebar Message -->
            <!--
            <div class="sidebar-card d-none d-lg-flex">
                <img class="sidebar-card-illustration mb-2" src="img/undraw_rocket.svg" alt="...">
                <p class="text-center mb-2"><strong>SB Admin Pro</strong> is packed with premium features, components, and more!</p>
                <a class="btn btn-success btn-sm" href="https://startbootstrap.com/theme/sb-admin-pro">Upgrade to Pro!</a>
            </div>
            -->

        </ul>
        <!-- Sidebar END -->