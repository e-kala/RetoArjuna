// Auto guardado asíncrono de borrador para los editores del panel admin
// (curso/evento en contenido_form.php, lección en leccion_form.php).
// Dispara con un breve debounce después de cada cambio real (texto del
// formulario o del editor Quill) — nunca en un intervalo desconectado de la
// edición — y reutiliza $.notify (notify.js, ya cargado en _header.php) para
// avisar tanto del guardado como de una sesión cerrada a mitad de edición.
window.PfAutosave = (function ($) {
  function iniciar(opciones) {
    const form = document.querySelector(opciones.formSelector);
    if (!form) {
      return;
    }

    let sucio = false;
    let enVuelo = false;
    let sesionCerrada = false;
    let temporizador = null;
    const retraso = opciones.retrasoMs || 2500;

    // Indicador junto a los botones de guardar — spinner de Bootstrap (docs:
    // componente Spinners) desde que se detecta el cambio hasta que la
    // petición de guardado termina, sin texto de cuenta regresiva. Opcional:
    // si no se pasa indicadorSelector, todo esto queda como no-op silencioso.
    const indicador = opciones.indicadorSelector ? document.querySelector(opciones.indicadorSelector) : null;
    let intervaloCuenta = null;
    const SPINNER_HTML = '<span class="spinner-border spinner-border-sm text-muted" role="status" aria-hidden="true"></span>';

    function pintarIndicador(html) {
      if (indicador) {
        indicador.innerHTML = html;
      }
    }

    function detenerCuentaRegresiva() {
      clearTimeout(intervaloCuenta);
      intervaloCuenta = null;
    }

    function iniciarCuentaRegresiva() {
      detenerCuentaRegresiva();
      pintarIndicador(SPINNER_HTML);
      intervaloCuenta = setTimeout(detenerCuentaRegresiva, retraso);
    }

    function marcarSucio() {
      if (sesionCerrada) {
        return;
      }
      sucio = true;
      clearTimeout(temporizador);
      temporizador = setTimeout(intentarGuardar, retraso);
      iniciarCuentaRegresiva();
    }

    async function verificarSesionSigueActiva() {
      if (!opciones.verificarSesionUrl) {
        return false; // sin URL de verificación no se puede confirmar — se trata como cerrada, es la opción segura
      }
      try {
        const res = await fetch(opciones.verificarSesionUrl, { credentials: 'same-origin', cache: 'no-store' });
        const data = await res.json();
        return !!data.logged_in;
      } catch (e) {
        return false; // sin red no se puede confirmar nada — misma opción segura
      }
    }

    async function intentarGuardar() {
      detenerCuentaRegresiva();
      if (!sucio || enVuelo || sesionCerrada) {
        pintarIndicador('');
        return;
      }
      const tituloEl = form.querySelector('[name="titulo"]');
      if (tituloEl && tituloEl.value.trim() === '') {
        pintarIndicador('');
        return; // nada útil que autoguardar todavía
      }
      if (opciones.requiereIdExistente) {
        const idEl = form.querySelector(opciones.campoId || 'input[name="id"]');
        if (!idEl || Number(idEl.value) === 0) {
          pintarIndicador('');
          return; // este editor no crea el registro por autoguardado, solo lo actualiza una vez ya existe
        }
      }
      // Si se acaba de pegar una imagen, la subida real todavía puede estar
      // en curso (el matcher quita el base64 de inmediato pero sube e
      // inserta la URL de forma asíncrona) — autoguardar justo en ese hueco
      // mandaría el contenido sin la imagen. Se reintenta en el siguiente
      // "text-change" (la propia inserción de la imagen ya dispara uno).
      if ((opciones.quills || []).some(function (q) { return q.pfSubidasPendientes > 0; })) {
        pintarIndicador('<span class="text-muted">Esperando imagen…</span>');
        sucio = true;
        return;
      }
      if (opciones.antesDeGuardar) {
        opciones.antesDeGuardar();
      }

      sucio = false;
      enVuelo = true;
      pintarIndicador(SPINNER_HTML);
      const datos = new FormData(form);
      datos.set(opciones.campoBandera, opciones.valorBandera);

      try {
        const res = await fetch(form.getAttribute('action') || window.location.href, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: datos,
        });
        // res.redirected pasa cuando require_login() ya hizo un 302 a
        // ingreso.php — ahí sí es una sesión realmente cerrada. Un 403 es
        // ambiguo (puede ser eso, pero también un csrf_token desincronizado
        // o, visto en producción, un bloqueo del servidor al pegar contenido
        // grande/enriquecido en el editor) — antes se asumía "sesión
        // cerrada" a ciegas y sacaba al admin de la página a media edición
        // aunque la sesión siguiera activa. Ahora se confirma con
        // session_check.php antes de decidir: si sigue con sesión, no se
        // navega a ningún lado, solo se reintenta en el siguiente cambio.
        if (res.redirected) {
          pintarIndicador('');
          avisarSesionCerrada();
          return;
        }
        if (res.status === 403) {
          const sigueConSesion = await verificarSesionSigueActiva();
          if (sigueConSesion) {
            sucio = true; // se reintenta con el próximo cambio, sin interrumpir al admin
            pintarIndicador('');
            return;
          }
          pintarIndicador('');
          avisarSesionCerrada();
          return;
        }
        const data = await res.json();
        if (data.success) {
          if (opciones.onGuardadoOk) {
            opciones.onGuardadoOk(data);
          }
          $.notify('Borrador guardado automáticamente.', { className: 'success', position: 'top right', autoHideDelay: 2000 });
          pintarIndicador('<span class="text-success"><i class="bi bi-check-lg"></i> Guardado</span>');
          setTimeout(function () { pintarIndicador(''); }, 2000);
        } else {
          pintarIndicador('');
        }
        // Errores de validación (ej. título vacío, ya filtrado arriba) se
        // ignoran en silencio — no se interrumpe al admin mientras escribe.
      } catch (e) {
        sucio = true; // error de conexión — se reintenta en el próximo cambio detectado
        pintarIndicador('');
      } finally {
        enVuelo = false;
      }
    }

    function avisarSesionCerrada() {
      if (sesionCerrada) {
        return;
      }
      sesionCerrada = true;
      // Compartido con content/session_watch.js (cargado en todo el sitio) —
      // evita apilar el mismo aviso dos veces si ambos lo detectan casi
      // al mismo tiempo.
      if (window.pfAvisarSesionCerrada) {
        window.pfAvisarSesionCerrada();
      } else {
        $.notify(
          'Tu sesión se cerró automáticamente. Copia tu trabajo actual antes de continuar e inicia sesión de nuevo.',
          { className: 'warn', position: 'top right', autoHide: false }
        );
      }
    }

    form.addEventListener('input', marcarSucio);
    form.addEventListener('change', marcarSucio);
    (opciones.quills || []).forEach(function (q) {
      q.on('text-change', marcarSucio);
    });

    window.addEventListener('beforeunload', function (e) {
      if (sucio && !sesionCerrada) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
  }

  return { iniciar: iniciar };
})(jQuery);
