// Aviso de "tu sesión se cerró automáticamente", para cualquier usuario
// logueado en cualquier parte del sitio — sondea backend/session_check.php
// cada pocos minutos; en cuanto detecta que ya no hay sesión (expiró sola,
// no por un logout manual — ese ya tiene su propio aviso en navbar.php),
// muestra un aviso fijo una sola vez. La URL de sondeo viene en el propio
// <script src="...session_watch.js" data-check-url="...">, porque cada
// layout del sitio (index.php, panel de usuario, panel admin, foro) está a
// una profundidad relativa distinta de backend/session_check.php.
(function () {
  const scriptActual = document.currentScript;
  const urlSondeo = scriptActual && scriptActual.dataset.checkUrl;
  if (!urlSondeo) {
    return;
  }
  // Prefijo relativo hacia la raíz de plataforma/ ('', '../' o '../../'
  // según la profundidad de la página actual) — se deriva de la propia URL
  // de sondeo en vez de venir fijo, porque este mismo script se carga desde
  // 4 profundidades distintas (index.php, panel de usuario, panel admin, foro).
  const prefijo = urlSondeo.replace(/backend\/session_check\.php$/, '');

  const INTERVALO_MS = 3 * 60 * 1000;

  // Compartido con panel/admin/_autosave.js y el botón "Guardar borrador" de
  // leccion_form.php, para no apilar el mismo aviso dos veces si ambos lo
  // detectan casi al mismo tiempo. Siempre redirige a iniciar sesión — con
  // "volver" a la página actual, para regresar aquí mismo tras loguearse de
  // nuevo (mismo mecanismo que ya usan los botones "Iniciar sesión" del navbar).
  window.pfAvisarSesionCerrada = window.pfAvisarSesionCerrada || (function () {
    let avisado = false;
    return function () {
      if (avisado) {
        return;
      }
      avisado = true;
      if (window.jQuery && jQuery.notify) {
        jQuery.notify(
          'Tu sesión se cerró automáticamente. Te llevamos a iniciar sesión de nuevo — al volver a entrar, regresas aquí mismo.',
          { className: 'warn', position: 'top right', autoHide: false }
        );
      }
      setTimeout(function () {
        const volver = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = prefijo + 'index.php?action=ingreso&volver=' + volver;
      }, 3000);
    };
  })();

  async function sondear() {
    try {
      const res = await fetch(urlSondeo, { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      if (!data.logged_in) {
        clearInterval(temporizador);
        window.pfAvisarSesionCerrada();
      }
    } catch (e) {
      // Error de red — se reintenta en el siguiente sondeo.
    }
  }

  const temporizador = setInterval(sondear, INTERVALO_MS);
})();
