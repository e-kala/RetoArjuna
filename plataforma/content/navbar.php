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
$esAdminNav = $usuarioNav && $usuarioNav['rol'] === 'admin';
// Campana de notificaciones — visible en TODA la plataforma (antes solo
// vivía en el foro). Ver backend/notificaciones.php.
$noLeidasNav = $usuarioNav ? notificaciones_no_leidas((int) $usuarioNav['id']) : 0;
$notifsDropdownNav = $usuarioNav ? notificaciones_recientes((int) $usuarioNav['id'], 8) : [];
$panelUrlNav = BASE_URL . '/panel/' . ($esAdminNav ? 'index.php' : 'dashboard.php') . '?action=notificaciones';
// El modo prueba de Stripe también lo puede usar una cuenta marcada
// es_prueba (no solo admins) — ver stripe_helper.php.
$puedeStripeModoPrueba = false;
if ($usuarioNav) {
    require_once __DIR__ . '/../backend/pagos/stripe_helper.php';
    $puedeStripeModoPrueba = stripe_modo_prueba_permitido();
}
?>
<?php if ($puedeStripeModoPrueba): echo stripe_modo_prueba_banner_html(); endif; ?>
<nav class="pf-nav" id="navbar">
  <div class="pf-container">
    <a href="<?= htmlspecialchars($navPrefijo) ?>index.php" class="pf-logo pf-logo-img">
      <img src="<?= htmlspecialchars($navPrefijo) ?>plataforma/digital-creative/img/logo.png" alt="">
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
        <div class="pf-nav-bell-menu">
          <button type="button" class="pf-nav-bell" title="Notificaciones">
            <i class="bi bi-bell-fill"></i><?php if ($noLeidasNav > 0): ?><span class="pf-nav-bell-badge" data-pf-bell-badge><?= $noLeidasNav > 9 ? '9+' : $noLeidasNav ?></span><?php endif; ?>
          </button>
          <div class="pf-nav-bell-dropdown">
            <div class="pf-nav-bell-dropdown-header">
              <span>Notificaciones</span>
              <button type="button" class="pf-nav-bell-marcar-todas" data-pf-marcar-todas <?= $noLeidasNav > 0 ? '' : 'hidden' ?>>
                <i class="bi bi-check2-all"></i> Marcar todas
              </button>
            </div>
            <div class="pf-nav-bell-dropdown-lista" data-pf-bell-lista>
              <?php foreach ($notifsDropdownNav as $n): ?>
                <div class="pf-nav-bell-item<?= (int) $n['leida'] === 0 ? ' pf-nav-bell-item-no-leida' : '' ?>" data-pf-notif-item="<?= (int) $n['id'] ?>">
                  <a href="<?= htmlspecialchars(BASE_URL . '/' . $n['enlace']) ?>" class="pf-nav-bell-item-link">
                    <i class="bi <?= notificacion_icono($n['tipo']) ?>"></i>
                    <span>
                      <?= htmlspecialchars($n['titulo']) ?>
                      <small><?= notificacion_tiempo_relativo($n['created_at']) ?></small>
                    </span>
                  </a>
                  <button type="button" class="pf-nav-bell-item-marcar" data-pf-marcar-una title="Marcar como leída" <?= (int) $n['leida'] === 0 ? '' : 'hidden' ?>>
                    <i class="bi bi-check2"></i>
                  </button>
                </div>
              <?php endforeach; ?>
              <?php if (!$notifsDropdownNav): ?>
                <div class="pf-nav-bell-vacio">No tienes notificaciones todavía.</div>
              <?php endif; ?>
            </div>
            <a href="<?= htmlspecialchars($panelUrlNav) ?>" class="pf-nav-bell-ver-todas">Ver todas</a>
          </div>
        </div>
        <div class="pf-user-menu">
          <button class="pf-user-chip">
            <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $usuarioNav['username'], 0, 1))) ?></span>
            <?= htmlspecialchars((string) $usuarioNav['username']) ?>
          </button>
          <div class="pf-user-dropdown">
            <?php if ($esAdminNav): ?>
              <a href="<?= htmlspecialchars(BASE_URL) ?>/panel/index.php"><i class="bi bi-speedometer2"></i> Panel admin</a>
              <a href="<?= htmlspecialchars(BASE_URL) ?>/panel/dashboard.php"><i class="bi bi-mortarboard"></i> Mi panel</a>
            <?php else: ?>
              <a href="<?= htmlspecialchars(BASE_URL) ?>/panel/dashboard.php"><i class="bi bi-mortarboard"></i> Mi panel</a>
            <?php endif; ?>
            <?php if ($puedeStripeModoPrueba): ?>
              <form method="post" action="<?= htmlspecialchars(BASE_URL) ?>/panel/admin/stripe_modo_prueba.php" class="pf-dropdown-switch-form">
                <?= csrf_field() ?>
                <input type="hidden" name="volver" value="<?= htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                <div class="form-check form-switch mb-0">
                  <input type="checkbox" class="form-check-input" role="switch" name="activar" value="1" id="navSwitchModoPrueba" <?= stripe_modo_prueba_activo() ? 'checked' : '' ?> onchange="this.form.submit()">
                  <label class="form-check-label" for="navSwitchModoPrueba">🧪 Modo prueba de Stripe</label>
                </div>
              </form>
            <?php endif; ?>
            <div class="pf-dropdown-divider"></div>
            <a href="<?= htmlspecialchars(BASE_URL) ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>
          </div>
        </div>
      <?php else: ?>
        <?php $navVolver = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '')); ?>
        <a class="pf-btn pf-btn-outline" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=ingreso&volver=<?= $navVolver ?>">Iniciar sesión</a>
        <a class="pf-btn pf-btn-primary" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=registro&volver=<?= $navVolver ?>">Crear Cuenta</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div id="pfToast" class="pf-toast" role="status" aria-live="polite">
  <i class="bi bi-check-circle-fill"></i>
  <div>
    <strong id="pfToastTitulo">Sesión cerrada correctamente</strong>
    <div class="pf-toast-sub" id="pfToastSub">¡Vuelve pronto!</div>
  </div>
</div>

<script>
  (function () {
    // Toast de sesión — logout.php redirige con ?sesion=cerrada; ingreso.php/
    // registro.php/olvide_contrasena.php/restablecer_contrasena.php redirigen
    // aquí con ?sesion=activa cuando alguien ya logueado intenta volver a
    // entrar. Se limpia el parámetro de la URL de inmediato para que no
    // vuelva a mostrarse con un refresh o al navegar hacia atrás/adelante.
    var mensajes = {
      cerrada: { titulo: 'Sesión cerrada correctamente', sub: '¡Vuelve pronto!' },
      activa: { titulo: 'Sesión abierta 😊', sub: 'Ya habías iniciado sesión' }
    };
    var params = new URLSearchParams(window.location.search);
    var estado = params.get('sesion');
    if (mensajes[estado]) {
      var toast = document.getElementById('pfToast');
      document.getElementById('pfToastTitulo').textContent = mensajes[estado].titulo;
      document.getElementById('pfToastSub').textContent = mensajes[estado].sub;
      params.delete('sesion');
      var resto = params.toString();
      window.history.replaceState({}, '', window.location.pathname + (resto ? '?' + resto : ''));
      requestAnimationFrame(function () { toast.classList.add('mostrar'); });
      setTimeout(function () { toast.classList.remove('mostrar'); }, 4000);
    }
  })();
</script>

<?php if ($usuarioNav): ?>
<script>
  (function () {
    // Dropdown de notificaciones (campana) — JS puro, sin depender de que
    // jQuery ya haya cargado (el navbar se incluye en TODAS las páginas,
    // algunas cargan jQuery hasta el final del body).
    var csrfToken = <?= json_encode(csrf_token()) ?>;
    var urlMarcarLeida = <?= json_encode(BASE_URL . '/backend/marcar_notificacion_leida.php') ?>;
    var badge = document.querySelector('[data-pf-bell-badge]');
    var btnMarcarTodas = document.querySelector('[data-pf-marcar-todas]');
    var lista = document.querySelector('[data-pf-bell-lista]');

    function actualizarBadge(delta) {
      if (!badge) return;
      var actual = parseInt(badge.textContent, 10);
      if (isNaN(actual)) actual = badge.textContent === '9+' ? 10 : 0;
      var nuevo = Math.max(0, actual + delta);
      if (nuevo <= 0) {
        badge.remove();
        if (btnMarcarTodas) btnMarcarTodas.hidden = true;
      } else {
        badge.textContent = nuevo > 9 ? '9+' : String(nuevo);
      }
    }

    function marcar(datos, callbackExito) {
      fetch(urlMarcarLeida, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: new URLSearchParams(Object.assign({ csrf_token: csrfToken }, datos)),
      })
        .then(function (res) { return res.json(); })
        .then(function (data) { if (data.success) callbackExito(); })
        .catch(function () { /* red caída: no pasa nada, el usuario puede reintentar */ });
    }

    if (btnMarcarTodas) {
      btnMarcarTodas.addEventListener('click', function () {
        marcar({ todas: '1' }, function () {
          document.querySelectorAll('[data-pf-notif-item].pf-nav-bell-item-no-leida').forEach(function (item) {
            item.classList.remove('pf-nav-bell-item-no-leida');
          });
          document.querySelectorAll('[data-pf-marcar-una]').forEach(function (btn) { btn.hidden = true; });
          if (badge) badge.remove();
          btnMarcarTodas.hidden = true;
        });
      });
    }

    if (lista) {
      lista.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-pf-marcar-una]');
        if (!btn) return;
        event.preventDefault();
        var item = btn.closest('[data-pf-notif-item]');
        var id = item ? item.getAttribute('data-pf-notif-item') : null;
        if (!id) return;
        marcar({ notificacion_id: id }, function () {
          item.classList.remove('pf-nav-bell-item-no-leida');
          btn.hidden = true;
          actualizarBadge(-1);
        });
      });
    }
  })();
</script>
<?php endif; ?>
