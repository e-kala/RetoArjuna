// Agrega un botón "Descargar" debajo de cada <img> dentro del contenido
// guardado desde el editor (.pf-contenido-html en curso/evento/lección/
// producto, .pf-forum-post-body en el foro) — incluyendo las que están
// dentro de una sección colapsable (.pf-colapsable-embed), ya que ese HTML
// se muestra tal cual se guardó, sin pasar de nuevo por el editor.
//
// Un solo módulo genérico en vez de tocarlo en cada una de las páginas que
// renderizan contenido: cualquier <img> dentro de esos contenedores es
// candidata, sin importar el tipo de contenido ni si está anidada.
(function () {
  'use strict';

  function nombreDeArchivo(src) {
    try {
      const ruta = new URL(src, window.location.href).pathname;
      return ruta.split('/').pop() || 'imagen.png';
    } catch (e) {
      return 'imagen.png';
    }
  }

  function agregarBotonDescarga(img) {
    if (img.nextElementSibling && img.nextElementSibling.classList && img.nextElementSibling.classList.contains('pf-imagen-descargar')) {
      return; // ya tiene botón (por si el script corre más de una vez)
    }
    // Clases de Bootstrap (ya cargado en todo el sitio) en vez de un botón
    // propio — mismo look que el resto de botones de la plataforma.
    const enlace = document.createElement('a');
    enlace.className = 'pf-imagen-descargar btn btn-sm btn-outline-secondary';
    enlace.href = img.currentSrc || img.src;
    enlace.download = nombreDeArchivo(img.currentSrc || img.src);
    enlace.innerHTML = '<i class="bi bi-download"></i> Descargar imagen';
    img.insertAdjacentElement('afterend', enlace);
  }

  function procesarContenedor(contenedor) {
    contenedor.querySelectorAll('img').forEach(agregarBotonDescarga);
  }

  // Este script se carga en <head> en algunas páginas (foro/inc/header.php,
  // antes de que <body> exista) y al final de <body> en otras
  // (content/scripts.php) — se difiere a DOMContentLoaded solo cuando hace
  // falta, para no depender de dónde quede el <script> en cada sitio.
  function inicializar() {
    document.querySelectorAll('.pf-contenido-html, .pf-forum-post-body').forEach(procesarContenedor);

    // El foro arma la vista previa y algunos cuerpos de respuesta de forma
    // dinámica después de la carga inicial (ver foro/tema.php) — se observa
    // cualquier .pf-forum-post-body/.pf-contenido-html que aparezca
    // después, para no depender de que este script se vuelva a llamar a
    // mano en cada lugar donde se inserta contenido por AJAX.
    const observador = new MutationObserver(function (mutaciones) {
      mutaciones.forEach(function (m) {
        m.addedNodes.forEach(function (nodo) {
          if (nodo.nodeType !== Node.ELEMENT_NODE) return;
          if (nodo.matches && nodo.matches('.pf-contenido-html, .pf-forum-post-body')) {
            procesarContenedor(nodo);
          }
          if (nodo.querySelectorAll) {
            nodo.querySelectorAll('.pf-contenido-html, .pf-forum-post-body').forEach(procesarContenedor);
          }
        });
      });
    });
    observador.observe(document.body, { childList: true, subtree: true });
  }

  if (document.body) {
    inicializar();
  } else {
    document.addEventListener('DOMContentLoaded', inicializar);
  }
})();
