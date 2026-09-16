// Editor de texto enriquecido propio — reemplazo completo de Quill.js.
//
// Por qué existe: Quill trataba los bloques colapsables (<details>/<summary>)
// como "embeds opacos" con contenteditable anidado (true→false→true), y
// necesitaba interceptar ~14 tipos de evento en fase de captura para que su
// propio modelo interno (Delta) nunca viera lo que pasaba dentro de esa
// isla. Ese aislamiento era frágil y ahí vivían los bugs recurrentes: pegar
// contenido dentro de un colapsable a veces lo dejaba vacío, deshacer/rehacer
// no respondía a Ctrl+Y de forma confiable, y reportes de "no se puede
// guardar/copiar", "se queda en blanco" en los editores de admin.
//
// Este editor usa el DOM real (un solo contenteditable="true" de nivel
// superior) como única fuente de verdad, sin ningún modelo intermedio. Los
// colapsables son HTML normal DENTRO de ese mismo contenteditable — nunca
// contenteditable anidado — así que escribir/pegar/anidar cosas adentro es
// edición nativa del navegador sin nada que aislar.
//
// contexto:'admin' habilita endpoints e íconos propios del panel; 'foro' usa
// el endpoint abierto a cualquier usuario con sesión y nunca expone
// colapsables/audio/adjuntos/tamañoAlineación (ver capacidades más abajo) —
// el saneador del foro (foro_sanitizar_html_editor) no conoce esas
// etiquetas/atributos y las borraría en silencio si se activaran ahí.
window.PfEditor = (function () {
  'use strict';

  // === Utilidades compartidas ===

  function avisarError(mensaje) {
    if (window.jQuery && jQuery.notify) {
      jQuery.notify(mensaje, { className: 'error', position: 'top right' });
    } else {
      alert(mensaje);
    }
  }

  // Un solo listener global de errores de JS por página (aunque haya varios
  // editores instanciados, como en foro/tema.php con varios a la vez).
  let avisoErrorGlobalInstalado = false;
  function instalarAvisoErrorGlobal() {
    if (avisoErrorGlobalInstalado) return;
    avisoErrorGlobalInstalado = true;
    let avisado = false;
    window.addEventListener('error', function (e) {
      console.error('[Editor] Error no capturado:', e.error || e.message, e);
      if (avisado || !window.jQuery || !jQuery.notify) return;
      avisado = true;
      jQuery.notify(
        'Ocurrió un error inesperado en el editor. Abre la consola (F12) y copia el error para reportarlo: ' + (e.message || 'error desconocido'),
        { className: 'error', position: 'top right', autoHide: false }
      );
    });
  }

  function normalizarUrlYoutube(url) {
    url = (url || '').trim();
    if (url === '' || url.indexOf('youtube.com/embed/') !== -1) return url;
    let m = url.match(/youtu\.be\/([a-zA-Z0-9_-]{6,})/i);
    if (!m) m = url.match(/youtube\.com\/(?:watch\?v=|shorts\/|live\/)([a-zA-Z0-9_-]{6,})/i);
    return m ? 'https://www.youtube.com/embed/' + m[1] : url;
  }

  function generarId() {
    return 'pf' + Math.random().toString(36).slice(2) + Date.now().toString(36);
  }

  // === CSS de la toolbar/editor — inyectado una sola vez desde JS, para no
  // depender de que cada una de las 6 páginas cargue un <link> adicional. ===
  let cssInstalado = false;
  function instalarCss() {
    if (cssInstalado) return;
    cssInstalado = true;
    const style = document.createElement('style');
    style.textContent = `
      .pf-editor-wrap { background:#fff; border:1px solid #ccc; border-radius:6px; overflow:hidden; }
      .pf-editor-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:6px; border-bottom:1px solid #ccc; background:#f8f8f8; }
      .pf-editor-toolbar .pf-editor-grupo { display:flex; align-items:center; gap:2px; padding:0 4px; border-right:1px solid #ddd; }
      .pf-editor-toolbar .pf-editor-grupo:last-child { border-right:none; }
      .pf-editor-indicador-guardado { display:inline-flex; align-items:center; font-size:12px; white-space:nowrap; }
      .pf-editor-indicador-guardado:empty { display:none; }
      .pf-editor-ayuda-panel { position:absolute; top:calc(100% + 4px); right:0; z-index:20; width:280px; max-width:80vw; background:#fff; border:1px solid #ccc; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,0.15); padding:10px 12px; font-size:12.5px; line-height:1.5; color:#333; }
      .pf-editor-ayuda-panel ul { margin:0; padding-left:18px; }
      .pf-editor-ayuda-panel li { margin-bottom:8px; }
      .pf-editor-ayuda-panel li:last-child { margin-bottom:0; }
      .pf-editor-boton { border:1px solid transparent; background:transparent; border-radius:4px; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:15px; line-height:1; color:#333; }
      .pf-editor-boton:hover { background:#e9e9e9; }
      .pf-editor-boton.activo { background:#dbe7ff; border-color:#a9c4ff; }
      .pf-editor-select { border:1px solid #ccc; border-radius:4px; height:30px; font-size:13px; background:#fff; color:#333; padding:0 4px; }
      .pf-editor-superficie { min-height:120px; max-height:520px; overflow-y:auto; padding:12px 14px; outline:none; font-size:14.5px; line-height:1.55; }
      .pf-editor-superficie:empty::before { content: attr(data-placeholder); color:#999; pointer-events:none; }
      .pf-editor-superficie p { margin:0 0 10px; }
      .pf-editor-superficie img { max-width:100%; border-radius:6px; display:block; margin:6px 0; }
      .pf-editor-superficie iframe { max-width:100%; width:100%; aspect-ratio:16/9; border:none; border-radius:6px; margin:6px 0; }
      .pf-editor-superficie blockquote { margin:0 0 10px; padding:4px 14px; border-left:3px solid #ccc; color:#555; }
      .pf-editor-superficie pre { background:#f4f4f4; border-radius:6px; padding:10px 12px; overflow-x:auto; }
      .pf-editor-superficie ul, .pf-editor-superficie ol { margin:0 0 10px; padding-left:22px; }
      .pf-editor-codigo { width:100%; min-height:200px; border:none; padding:12px 14px; font-family:monospace; font-size:13px; resize:vertical; outline:none; }
      .pf-audio-embed { background:#fff3e0; border:1px solid #ffcc80; border-radius:6px; padding:8px 12px; margin:6px 0; display:block; user-select:none; }
      /* Botón "eliminar sección" — solo dentro del editor (fuera de aquí, en
         la página ya publicada, .pf-colapsable-embed nunca tiene este botón
         porque este CSS no se carga ahí). Position:relative es exclusivo del
         editor por la misma razón: no toca el CSS público del bloque. */
      .pf-editor-superficie .pf-colapsable-embed { position:relative; }
      .pf-colapsable-embed-eliminar { display:none; position:absolute; top:6px; right:6px; z-index:5; width:22px; height:22px; border:1px solid #d9534f; border-radius:50%; background:#fff; color:#d9534f; font-size:14px; line-height:1; cursor:pointer; align-items:center; justify-content:center; }
      .pf-colapsable-embed:hover .pf-colapsable-embed-eliminar { display:flex; }
      .pf-colapsable-embed-eliminar:hover { background:#d9534f; color:#fff; }
      .pf-editor-wrap.pf-editor-pantalla-completa { position:fixed; inset:0; z-index:1050; border-radius:0; display:flex; flex-direction:column; }
      .pf-editor-wrap.pf-editor-pantalla-completa .pf-editor-superficie { flex:1; max-height:none; }
      body.pf-editor-bloqueo-scroll { overflow:hidden; }
      /* Bloques de nivel superior arrastrables — el handle solo aparece al
         pasar el mouse sobre el bloque, para no ensuciar visualmente el
         contenido mientras se lee/escribe normalmente. */
      .pf-editor-superficie[data-arrastre-activo] > * { position:relative; }
      .pf-editor-drag-handle { display:none; position:absolute; left:-22px; top:2px; width:18px; height:22px; cursor:grab; color:#999; font-size:13px; align-items:center; justify-content:center; user-select:none; }
      .pf-editor-superficie[data-arrastre-activo] > *:hover > .pf-editor-drag-handle { display:flex; }
      .pf-editor-drag-handle:hover { color:#333; }
      .pf-editor-superficie > .pf-arrastrando { opacity:.4; }
      .pf-editor-superficie > .pf-arrastre-indicador { border-top:2px solid #f7931e; }
    `;
    document.head.appendChild(style);
  }

  // === Selección / cursor (helpers DOM puros — sin reinventar nada que el
  // navegador ya resuelve, solo lo mínimo para insertar nodos custom y
  // sostener el historial de deshacer) ===

  function obtenerRangoActual(raiz) {
    const seleccion = window.getSelection();
    if (!seleccion.rangeCount) return null;
    const rango = seleccion.getRangeAt(0);
    if (!raiz.contains(rango.commonAncestorContainer)) return null;
    return rango;
  }

  // Serializa el cursor como rutas de índice de hijo desde `raiz` — no se
  // pueden guardar referencias directas a nodos porque tras restaurar
  // innerHTML en un deshacer/rehacer los nodos viejos ya no existen.
  function rutaDeNodo(raiz, nodo, offset) {
    const ruta = [];
    let actual = nodo;
    while (actual && actual !== raiz) {
      const padre = actual.parentNode;
      if (!padre) return null;
      ruta.unshift(Array.prototype.indexOf.call(padre.childNodes, actual));
      actual = padre;
    }
    if (actual !== raiz) return null;
    ruta.push(offset);
    return ruta;
  }

  function nodoDeRuta(raiz, ruta) {
    if (!ruta || !ruta.length) return null;
    let actual = raiz;
    for (let i = 0; i < ruta.length - 1; i++) {
      if (!actual || !actual.childNodes || !actual.childNodes[ruta[i]]) return null;
      actual = actual.childNodes[ruta[i]];
    }
    return { nodo: actual, offset: ruta[ruta.length - 1] };
  }

  function guardarPosicionCursor(raiz) {
    const rango = obtenerRangoActual(raiz);
    if (!rango) return null;
    return {
      inicio: rutaDeNodo(raiz, rango.startContainer, rango.startOffset),
      fin: rutaDeNodo(raiz, rango.endContainer, rango.endOffset),
    };
  }

  function restaurarPosicionCursor(raiz, posicion) {
    if (!posicion || !posicion.inicio) return;
    const inicio = nodoDeRuta(raiz, posicion.inicio);
    const fin = nodoDeRuta(raiz, posicion.fin) || inicio;
    if (!inicio || !inicio.nodo) return;
    try {
      const rango = document.createRange();
      rango.setStart(inicio.nodo, Math.min(inicio.offset, inicio.nodo.length || inicio.nodo.childNodes.length));
      rango.setEnd(fin.nodo, Math.min(fin.offset, fin.nodo.length || fin.nodo.childNodes.length));
      const seleccion = window.getSelection();
      seleccion.removeAllRanges();
      seleccion.addRange(rango);
    } catch (e) { /* rango inválido tras un cambio de estructura — se ignora, el cursor solo queda donde estaba */ }
  }

  // Tags de nivel de bloque que este editor inserta como nodo custom — a
  // diferencia de <img>/<a> (inline, pueden vivir dentro de un <p> sin
  // problema), Range.insertNode() con estos NO parte automáticamente un
  // <p> que los contenga (eso solo lo hace el parser HTML al leer un
  // string nuevo, nunca al construir el árbol nodo-por-nodo vía DOM API) —
  // el resultado sería <p>texto<details>...</details></p>, HTML inválido
  // que el navegador SÍ normaliza/reordena de forma impredecible la
  // próxima vez que se vuelva a parsear ese string (ej. al mostrarlo en la
  // página pública). Se detecta aquí y se "sale" del <p> antes de insertar.
  const TAGS_DE_BLOQUE = ['DETAILS', 'IFRAME', 'PRE', 'DIV'];

  function esNodoDeBloque(nodo) {
    return nodo.nodeType === Node.ELEMENT_NODE && TAGS_DE_BLOQUE.indexOf(nodo.tagName) !== -1;
  }

  // Si `rango` (ya colapsado) está dentro de un <p>, mueve el punto de
  // inserción a JUSTO DESPUÉS de ese <p> — equivalente a lo que
  // execCommand('insertParagraph') haría al "salir" del párrafo, pero sin
  // crear un párrafo vacío de más cuando no hace falta.
  function salirDelParrafoSiHaceFalta(raiz, rango) {
    let nodo = rango.startContainer;
    if (nodo.nodeType === Node.TEXT_NODE) nodo = nodo.parentElement;
    const parrafo = nodo ? nodo.closest('p') : null;
    if (!parrafo || !raiz.contains(parrafo)) return rango;
    const nuevoRango = document.createRange();
    nuevoRango.setStartAfter(parrafo);
    nuevoRango.collapse(true);
    return nuevoRango;
  }

  // Inserta un nodo en la posición actual del cursor (si hay uno válido
  // dentro de `raiz`); si no, lo agrega al final. Deja el cursor justo
  // después del nodo insertado. `nodo` puede ser un DocumentFragment (ej.
  // HTML pegado con varios elementos hijos) — insertNode() vacía el
  // fragment al insertarlo (sus hijos pasan al DOM real), así que el
  // "último hijo real" se captura ANTES de insertar para poder colocar el
  // cursor después de él (el fragment ya vacío no tiene padre ni sirve
  // como referencia).
  function insertarNodoEnCursor(raiz, nodo) {
    const esFragmento = nodo.nodeType === Node.DOCUMENT_FRAGMENT_NODE;
    const referenciaFinal = esFragmento ? nodo.lastChild : nodo;
    let rango = obtenerRangoActual(raiz);
    if (!rango) {
      raiz.appendChild(nodo);
      if (referenciaFinal) colocarCursorDespuesDe(referenciaFinal);
      return;
    }
    if (esNodoDeBloque(nodo)) {
      rango.deleteContents();
      rango = salirDelParrafoSiHaceFalta(raiz, rango);
    } else {
      rango.deleteContents();
    }
    rango.insertNode(nodo);
    if (referenciaFinal) colocarCursorDespuesDe(referenciaFinal);
  }

  function colocarCursorDespuesDe(nodo) {
    const rango = document.createRange();
    rango.setStartAfter(nodo);
    rango.collapse(true);
    const seleccion = window.getSelection();
    seleccion.removeAllRanges();
    seleccion.addRange(rango);
  }

  // === Historial (undo/redo) ===
  //
  // Snapshots de innerHTML + cursor, con debounce para no crear uno por cada
  // tecla, y "checkpoints" explícitos ANTES de operaciones estructurales
  // (insertar imagen/video/colapsable/etc, pegar, alternar a vista código) —
  // así el primer Ctrl+Z tras una de esas operaciones la deshace en un solo
  // paso limpio. Bindings de teclado propios (no el undo nativo del
  // navegador, que es la causa más probable del bug de "Ctrl+Y no
  // responde" actual): el editor controla el preventDefault y decide qué
  // entra al historial.
  function historialCrear(raiz, alCambiarEstado) {
    const pila = [];
    let indice = -1;
    let temporizadorDebounce = null;
    let temporizadorMaximo = null;
    let aplicandoHistorial = false;
    const LIMITE = 100;
    const DEBOUNCE_MS = 500;
    // Cota superior de una sola "ráfaga" de tecleo sin pausas: sin esto, si
    // el usuario nunca deja de escribir por más de DEBOUNCE_MS, el
    // setTimeout de abajo se reinicia en cada tecla y jamás llega a
    // dispararse — Ctrl+Z quedaba sin ningún checkpoint intermedio que
    // deshacer (bug real reportado: "Ctrl+Z no responde" al escribir texto
    // normal de corrido). Con este tope, una ráfaga larga igual se parte en
    // checkpoints de ~2s cada uno.
    const MAXIMO_MS = 2000;

    function snapshotActual() {
      return { html: raiz.innerHTML, cursor: guardarPosicionCursor(raiz) };
    }

    function empujar(snapshot) {
      // Al escribir tras haber deshecho unos pasos, se descarta el futuro
      // (comportamiento estándar de cualquier editor).
      pila.length = indice + 1;
      pila.push(snapshot);
      if (pila.length > LIMITE) pila.shift();
      indice = pila.length - 1;
    }

    function limpiarTemporizadores() {
      clearTimeout(temporizadorDebounce);
      clearTimeout(temporizadorMaximo);
      temporizadorDebounce = null;
      temporizadorMaximo = null;
    }

    function registrarCheckpoint() {
      limpiarTemporizadores();
      if (aplicandoHistorial) return;
      if (indice === -1 || pila[indice].html !== raiz.innerHTML) {
        empujar(snapshotActual());
      }
    }

    function marcarSucioConDebounce() {
      if (aplicandoHistorial) return;
      clearTimeout(temporizadorDebounce);
      if (indice === -1) {
        // Primer cambio de la sesión de edición: guarda el estado ANTERIOR
        // al cambio no alcanza a capturarse aquí (ya ocurrió), así que se
        // asegura al menos un punto de partida en el próximo tick.
        empujar(snapshotActual());
        return;
      }
      temporizadorDebounce = setTimeout(function () {
        limpiarTemporizadores();
        empujar(snapshotActual());
      }, DEBOUNCE_MS);
      if (!temporizadorMaximo) {
        temporizadorMaximo = setTimeout(function () {
          limpiarTemporizadores();
          empujar(snapshotActual());
        }, MAXIMO_MS);
      }
    }

    function aplicar(snapshot) {
      aplicandoHistorial = true;
      raiz.innerHTML = snapshot.html;
      restaurarPosicionCursor(raiz, snapshot.cursor);
      aplicandoHistorial = false;
      if (alCambiarEstado) alCambiarEstado();
    }

    function deshacer() {
      limpiarTemporizadores();
      if (indice <= 0) {
        // Nada más atrás — si hay un cambio pendiente sin registrar, al
        // menos asegura que el estado actual no se pierda para un redo.
        if (indice === -1 && pila.length === 0) return;
        return;
      }
      // Si hay una edición reciente sin registrar (dentro del debounce),
      // se registra primero para no perderla como "redo" disponible.
      if (indice === pila.length - 1 && pila[indice].html !== raiz.innerHTML) {
        empujar(snapshotActual());
      }
      indice--;
      aplicar(pila[indice]);
    }

    function rehacer() {
      if (indice >= pila.length - 1) return;
      indice++;
      aplicar(pila[indice]);
    }

    // Estado inicial en la pila para poder deshacer el primer cambio real.
    empujar(snapshotActual());

    return {
      registrarCheckpoint: registrarCheckpoint,
      marcarSucioConDebounce: marcarSucioConDebounce,
      deshacer: deshacer,
      rehacer: rehacer,
    };
  }

  // === Limpieza de HTML pegado ===
  //
  // En ADMIN no hay saneador server-side — el propio editor debe evitar que
  // pasen <script>/manejadores on*. En FORO además se filtran style/class
  // (el saneador los rechazaría de todos modos, y dejarlos en el editor
  // produciría un salto visual entre "cómo se ve mientras editas" y "cómo
  // se ve tras guardar").
  const TAGS_PROHIBIDOS_PEGADO = ['script', 'style', 'link', 'meta', 'object', 'embed', 'form', 'input', 'button', 'iframe'];

  function limpiarFragmentoPegado(htmlCrudo, contexto) {
    const plantilla = document.createElement('template');
    plantilla.innerHTML = htmlCrudo;
    const fragmento = plantilla.content;

    function limpiarNodo(nodo) {
      Array.prototype.slice.call(nodo.childNodes).forEach(function (hijo) {
        if (hijo.nodeType === Node.COMMENT_NODE) {
          hijo.remove();
          return;
        }
        if (hijo.nodeType !== Node.ELEMENT_NODE) return;
        const tag = hijo.tagName.toLowerCase();
        if (TAGS_PROHIBIDOS_PEGADO.indexOf(tag) !== -1) {
          hijo.remove();
          return;
        }
        limpiarNodo(hijo);
        Array.prototype.slice.call(hijo.attributes || []).forEach(function (atributo) {
          const nombre = atributo.name.toLowerCase();
          if (nombre.indexOf('on') === 0) {
            hijo.removeAttribute(atributo.name);
            return;
          }
          if (contexto !== 'admin' && (nombre === 'style' || nombre === 'class') && tag !== 'img') {
            hijo.removeAttribute(atributo.name);
          }
        });
      });
    }
    limpiarNodo(fragmento);
    return fragmento;
  }

  // === Comandos de toolbar ===

  function ejecutarComandoBasico(nombre, valor) {
    document.execCommand(nombre, false, valor || null);
  }

  // Envuelve la selección actual en <span style="font-size:Npx">. Se usa
  // Range.extractContents()+insertNode en vez del truco clásico de
  // execCommand('fontSize','7')+reemplazo de <font> — más predecible y no
  // deja tags <font> residuales si algo falla a mitad de camino.
  function aplicarTamanoFuente(raiz, px) {
    const rango = obtenerRangoActual(raiz);
    if (!rango || rango.collapsed) return;
    const span = document.createElement('span');
    span.style.fontSize = px;
    span.appendChild(rango.extractContents());
    rango.insertNode(span);
    colocarCursorDespuesDe(span);
  }

  function insertarBloqueCodigo(raiz) {
    const seleccion = window.getSelection();
    const texto = seleccion.toString();
    const pre = document.createElement('pre');
    const code = document.createElement('code');
    code.textContent = texto || '';
    pre.appendChild(code);
    insertarNodoEnCursor(raiz, pre);
  }

  // === Colapsables ===
  //
  // HTML normal (details/summary/div) viviendo DENTRO del contenteditable
  // único — nada de contenteditable anidado. El único comportamiento
  // especial es Enter en el <summary> (decisión de producto: es de una
  // sola línea, Enter mueve el foco al cuerpo en vez de crear línea nueva).
  // Botón para eliminar el bloque colapsable completo — Backspace/Delete
  // normal dentro del título o el cuerpo NO puede hacerlo de forma
  // confiable: sin contenteditable anidado (a propósito, para no repetir
  // la fragilidad que tenía Quill), un <summary>/cuerpo no es su propio
  // "editing host", así que seleccionar y borrar ahí puede fusionarse con
  // el contenido de fuera del colapsable en vez de quedarse contenido.
  // contenteditable="false" para que nunca compita con la edición de texto
  // — es puramente un control de la UI del editor, se quita antes de
  // guardar (ver htmlSinControlesDeEditor) y se reinstala al cargar HTML
  // ya guardado o al volver de la vista de código (ver
  // reinstalarBotonesEliminarColapsable).
  function crearBotonEliminarColapsable() {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'pf-colapsable-embed-eliminar';
    boton.title = 'Eliminar esta sección colapsable';
    boton.textContent = '×';
    boton.setAttribute('contenteditable', 'false');
    return boton;
  }

  function reinstalarBotonesEliminarColapsable(raiz) {
    raiz.querySelectorAll('.pf-colapsable-embed').forEach(function (details) {
      if (!details.querySelector(':scope > .pf-colapsable-embed-eliminar')) {
        details.appendChild(crearBotonEliminarColapsable());
      }
    });
  }

  function crearNodoColapsable() {
    const details = document.createElement('details');
    details.className = 'pf-colapsable-embed';
    details.setAttribute('open', '');
    const summary = document.createElement('summary');
    summary.textContent = 'Título del colapsable';
    const cuerpo = document.createElement('div');
    cuerpo.className = 'pf-colapsable-body-editable';
    cuerpo.innerHTML = '<p><br></p>';
    details.appendChild(summary);
    details.appendChild(cuerpo);
    details.appendChild(crearBotonEliminarColapsable());
    return details;
  }

  // Deliberadamente sin el.focus(): un <summary>/<div> hijo de un
  // contenteditable de nivel superior no es un punto de "foco de
  // documento" independiente — el navegador reporta (y mantiene) el
  // activeElement en la raíz contenteditable pase lo que pase, y llamar
  // focus() en el hijo justo después de un evento de click en un botón de
  // toolbar hace que el navegador regrese el foco a la raíz de forma
  // síncrona (comportamiento reproducible incluso con un dispatchEvent
  // sintético, no es un artefacto de Playwright). Lo único que de verdad
  // importa para que el usuario pueda escribir ahí es dónde apunta el
  // Range/Selection, que sí se mantiene correctamente sin necesidad de
  // focus() explícito.
  function seleccionarTextoDe(el) {
    const seleccion = window.getSelection();
    const rango = document.createRange();
    rango.selectNodeContents(el);
    seleccion.removeAllRanges();
    seleccion.addRange(rango);
  }

  // === Objeto de compatibilidad ".quill" ===
  //
  // _autosave.js y conectarBorradorLocal (portados literales más abajo)
  // solo necesitan: .root.innerHTML (get/set), .on('text-change', cb), y
  // .pfSubidasPendientes — esto es exactamente lo que se expone aquí, nada
  // más. `.root` es un objeto plano con getter/setter, no el elemento DOM
  // real (el único uso observado de `.root` en el código existente es leer/
  // escribir `.innerHTML`).
  // El botón "×" de cada colapsable (crearNodoColapsable) es solo control
  // de UI del editor, contenteditable="false" para no interferir con la
  // edición — pero sigue siendo un nodo real dentro de raiz.innerHTML, así
  // que se quita antes de servir el HTML hacia afuera (guardado, borrador
  // local, vista previa) para no guardarlo en la base de datos ni mostrarlo
  // en la página ya publicada.
  function htmlSinControlesDeEditor(html) {
    if (html.indexOf('pf-colapsable-embed-eliminar') === -1 && html.indexOf('pf-editor-drag-handle') === -1) return html;
    const plantilla = document.createElement('template');
    plantilla.innerHTML = html;
    plantilla.content.querySelectorAll('.pf-colapsable-embed-eliminar, .pf-editor-drag-handle').forEach(function (n) { n.remove(); });
    return plantilla.innerHTML;
  }

  function crearQuillCompat(raiz, estaEnModoCodigo, obtenerTextareaCodigo) {
    const listeners = [];
    function dispararTextChange() {
      listeners.forEach(function (cb) { cb(); });
    }
    const compat = {
      pfSubidasPendientes: 0,
      on: function (evento, cb) {
        if (evento === 'text-change') listeners.push(cb);
      },
      root: {
        get innerHTML() {
          return estaEnModoCodigo() ? obtenerTextareaCodigo().value : htmlSinControlesDeEditor(raiz.innerHTML);
        },
        set innerHTML(html) {
          if (estaEnModoCodigo()) {
            obtenerTextareaCodigo().value = html;
          } else {
            raiz.innerHTML = html;
          }
          dispararTextChange();
        },
      },
    };
    return { compat: compat, dispararTextChange: dispararTextChange };
  }

  // Botón "(?)" con un panel propio (no un tooltip/popover de Bootstrap —
  // este módulo no puede depender de que bootstrap.bundle.min.js ya esté
  // cargado en el momento en que se inicializa el editor, ver comentario en
  // foro/tema.php sobre el orden de carga) que documenta los comportamientos
  // del editor que no son obvios a simple vista. La lista se arma según las
  // capacidades activas — no tiene caso explicar Ctrl+Enter para salir de un
  // colapsable en un editor donde esa capacidad ni siquiera está encendida.
  function instalarBotonAyuda(grupo, cap) {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'pf-editor-boton';
    boton.title = 'Ayuda del editor';
    boton.textContent = '?';
    boton.addEventListener('mousedown', function (e) { e.preventDefault(); });

    const tips = [];
    tips.push('<b>Ctrl+Z</b> / <b>Ctrl+Y</b> (o Ctrl+Shift+Z): deshacer y rehacer.');
    if (cap.colapsables) {
      tips.push('Dentro de una <b>sección colapsable</b>: Enter en el título pasa al cuerpo; <b>Ctrl+Enter</b> dentro del cuerpo sale de la sección y continúa el texto normal después de ella. Para <b>eliminar la sección completa</b>, pasa el mouse sobre ella y usa el botón <b>×</b> que aparece en su esquina — Suprimir/Backspace normal no la elimina de forma confiable.');
    }
    tips.push('Puedes <b>pegar una imagen</b> directamente (Ctrl+V) — de una captura de pantalla o copiada de otra página — sin necesidad de subirla primero a tu computadora.');
    tips.push('Para <b>quitar una imagen</b> o un video: haz clic sobre él para seleccionarlo y presiona <b>Suprimir</b> (o Backspace).');
    tips.push('El botón <b>&lt;/&gt;</b> muestra y permite editar el HTML del contenido directamente, por si necesitas un ajuste que la barra de herramientas no cubre.');
    if (cap.video) tips.push('El botón de <b>video</b> pide el link de YouTube y lo inserta ya listo para reproducirse.');

    const panel = document.createElement('div');
    panel.className = 'pf-editor-ayuda-panel';
    panel.innerHTML = '<ul>' + tips.map(function (t) { return '<li>' + t + '</li>'; }).join('') + '</ul>';
    panel.hidden = true;

    boton.addEventListener('click', function (e) {
      e.stopPropagation();
      panel.hidden = !panel.hidden;
    });
    document.addEventListener('click', function (e) {
      if (!panel.hidden && !panel.contains(e.target) && e.target !== boton) panel.hidden = true;
    });

    grupo.appendChild(boton);
    grupo.appendChild(panel);
    grupo.style.position = 'relative';
  }

  /**
   * Crea un editor configurado según capacidades, con paste-imagen (archivo
   * real + HTML), contador de subidas pendientes, deshacer/rehacer propio,
   * vista de código HTML y, si aplica, colapsables/audio/adjuntos/video/
   * tamaño-alineación.
   *
   * opciones:
   *   contenedor: selector o elemento del <div> del editor (ya en el DOM)
   *   contexto: 'admin' | 'foro'
   *   placeholder
   *   csrfToken (requerido)
   *   endpointImagen (default según contexto)
   *   endpointAudio / endpointAdjunto (requeridos si esas capacidades están activas)
   *   contenidoInicialHtml (se asigna como HTML inicial de la superficie)
   *   capacidades: { video, colapsables, audio, adjuntos, tamanoAlineacion, undoRedo, codeBlock }
   */
  function crear(opciones) {
    if (!opciones || !opciones.contenedor) throw new Error('PfEditor.crear: falta "contenedor".');
    if (!opciones.csrfToken) throw new Error('PfEditor.crear: falta "csrfToken".');
    const contexto = opciones.contexto === 'admin' ? 'admin' : 'foro';
    const cap = Object.assign({
      video: false, colapsables: false, audio: false, adjuntos: false,
      tamanoAlineacion: false, undoRedo: false, codeBlock: false,
    }, opciones.capacidades || {});

    if (contexto !== 'admin' && (cap.audio || cap.adjuntos || cap.tamanoAlineacion)) {
      throw new Error('PfEditor.crear: audio/adjuntos/tamanoAlineacion son exclusivos de contexto "admin" (no sanitizados/soportados en el foro).');
    }

    instalarAvisoErrorGlobal();
    instalarCss();

    const contenedorEl = typeof opciones.contenedor === 'string' ? document.querySelector(opciones.contenedor) : opciones.contenedor;
    if (!contenedorEl) throw new Error('PfEditor.crear: no se encontró el contenedor "' + opciones.contenedor + '".');

    const endpointImagen = opciones.endpointImagen || (contexto === 'admin' ? '../../backend/quill_imagen_subir.php' : 'backend/imagen_subir.php');

    // --- Estructura DOM: wrapper > toolbar + superficie/textarea-código ---
    // El contenedor puede traer un style inline heredado de cuando este div
    // era directamente el root de Quill (ej. height:220px fijo en
    // contenido_form.php) — con la estructura nueva (wrap > toolbar +
    // superficie con su propio scroll interno) esa altura fija en el
    // CONTENEDOR EXTERNO, sin overflow propio, dejaba que el contenido de
    // la superficie (una imagen recién insertada) se desbordara visualmente
    // encima de lo que viene después en la página. Se limpia para que solo
    // el CSS propio de .pf-editor-wrap/.pf-editor-superficie controle el
    // tamaño y el scroll.
    contenedorEl.removeAttribute('style');
    contenedorEl.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'pf-editor-wrap';
    const toolbarEl = document.createElement('div');
    toolbarEl.className = 'pf-editor-toolbar';
    const raiz = document.createElement('div');
    raiz.className = 'pf-editor-superficie';
    raiz.contentEditable = 'true';
    if (opciones.placeholder) raiz.setAttribute('data-placeholder', opciones.placeholder);
    const textareaCodigo = document.createElement('textarea');
    textareaCodigo.className = 'pf-editor-codigo';
    textareaCodigo.style.display = 'none';
    wrap.appendChild(toolbarEl);
    wrap.appendChild(raiz);
    wrap.appendChild(textareaCodigo);
    contenedorEl.appendChild(wrap);

    if (typeof opciones.contenidoInicialHtml === 'string' && opciones.contenidoInicialHtml !== '') {
      raiz.innerHTML = opciones.contenidoInicialHtml;
      if (cap.colapsables) reinstalarBotonesEliminarColapsable(raiz);
      reinstalarHandlesDeArrastre();
    }

    // --- Estado de modo (visual vs código) ---
    let modoCodigo = false;
    function estaEnModoCodigo() { return modoCodigo; }
    function obtenerTextareaCodigo() { return textareaCodigo; }

    const quillCompatInfo = crearQuillCompat(raiz, estaEnModoCodigo, obtenerTextareaCodigo);
    const quillCompat = quillCompatInfo.compat;
    // Envuelve el disparador real: cualquier cambio de contenido (tecleo,
    // inserción de imagen/video/colapsable, pegado) puede haber agregado un
    // nuevo hijo de nivel superior a `raiz` sin su handle de arrastre — se
    // reinstala aquí, en el único punto por el que pasan todos los cambios,
    // en vez de tener que acordarse de llamarlo en cada sitio de inserción.
    function dispararTextChange() {
      if (!estaEnModoCodigo()) reinstalarHandlesDeArrastre();
      quillCompatInfo.dispararTextChange();
    }

    // --- Historial (undo/redo) ---
    const historial = historialCrear(raiz, dispararTextChange);

    // === Colapsables: contexto de "último foco" para insertar dentro ===
    // Con un solo contenteditable, insertar dentro o fuera de un colapsable
    // es exactamente la misma operación (insertarNodoEnCursor usa el
    // Range activo, sin importar dónde esté) — no hace falta rastrear un
    // "contexto de colapsable" aparte como en la versión con Quill.

    // --- Imagen: subida por botón, por HTML pegado, y por archivo pegado ---
    async function subirImagen(blobOArchivo, nombreArchivo) {
      const extension = ((blobOArchivo.type || '').split('/')[1] || 'png').split('+')[0];
      const datos = new FormData();
      datos.append('imagen', blobOArchivo, nombreArchivo || ('pegado.' + extension));
      datos.append('csrf_token', opciones.csrfToken);
      const res = await fetch(endpointImagen, { method: 'POST', body: datos });
      return res.json();
    }

    function insertarImagenPorBoton() {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/png,image/jpeg,image/webp,image/gif';
      input.addEventListener('change', async function () {
        const archivo = input.files[0];
        if (!archivo) return;
        try {
          const data = await subirImagen(archivo, archivo.name);
          if (data.success) {
            historial.registrarCheckpoint();
            const img = document.createElement('img');
            img.src = data.url;
            insertarNodoEnCursor(raiz, img);
            historial.registrarCheckpoint();
            dispararTextChange();
          } else {
            avisarError(data.message || 'No se pudo subir la imagen.');
          }
        } catch (e) {
          avisarError('Error de conexión subiendo la imagen.');
        }
      });
      input.click();
    }

    async function manejarImagenPegadaComoArchivo(archivo) {
      quillCompat.pfSubidasPendientes++;
      try {
        const data = await subirImagen(archivo, archivo.name);
        if (data.success) {
          historial.registrarCheckpoint();
          const img = document.createElement('img');
          img.src = data.url;
          insertarNodoEnCursor(raiz, img);
          historial.registrarCheckpoint();
          dispararTextChange();
        } else {
          avisarError(data.message || 'No se pudo subir una imagen pegada.');
        }
      } catch (e) {
        avisarError('Error de conexión subiendo una imagen pegada.');
      } finally {
        quillCompat.pfSubidasPendientes--;
      }
    }

    async function manejarPegadoHtml(htmlCrudo) {
      const fragmento = limpiarFragmentoPegado(htmlCrudo, contexto);
      const imgsData = fragmento.querySelectorAll('img[src^="data:"]');
      if (imgsData.length) {
        quillCompat.pfSubidasPendientes += imgsData.length;
        await Promise.all(Array.prototype.map.call(imgsData, async function (img) {
          try {
            const blob = await (await fetch(img.src)).blob();
            const data = await subirImagen(blob, null);
            if (data.success) {
              img.src = data.url;
            } else {
              img.remove();
            }
          } catch (e) {
            img.remove();
          } finally {
            quillCompat.pfSubidasPendientes--;
          }
        }));
      }
      // En admin también se aceptan img[src] http(s) tal cual (vienen de
      // otra página, no hace falta resubirlas); en foro el saneador server
      // ya las filtraría si no fueran http(s), así que se dejan pasar igual.
      //
      // execCommand('insertHTML', ...) en vez de Range.insertNode manual:
      // el navegador ya sabe partir correctamente un <p> existente cuando
      // se pega contenido a nivel de bloque en medio de su texto (mismo
      // motor de edición nativo que usa para Enter/backspace) — insertar
      // el fragmento a mano dejaba <p> anidados dentro de <p> (HTML
      // inválido) cuando el cursor estaba a mitad de un párrafo.
      const contenedorTemporal = document.createElement('div');
      contenedorTemporal.appendChild(fragmento);
      historial.registrarCheckpoint();
      document.execCommand('insertHTML', false, contenedorTemporal.innerHTML);
      historial.registrarCheckpoint();
      dispararTextChange();
    }

    raiz.addEventListener('paste', function (evento) {
      const archivos = evento.clipboardData && evento.clipboardData.files;
      const archivoImagen = archivos && Array.prototype.find.call(archivos, function (f) {
        return f.type && f.type.indexOf('image/') === 0;
      });
      if (archivoImagen) {
        evento.preventDefault();
        manejarImagenPegadaComoArchivo(archivoImagen);
        return;
      }
      const html = evento.clipboardData && evento.clipboardData.getData('text/html');
      if (html) {
        evento.preventDefault();
        manejarPegadoHtml(html);
        return;
      }
      // Sin HTML ni archivo: se deja pasar el comportamiento default del
      // navegador (inserta texto plano de forma nativa).
    });

    // --- Video ---
    function insertarVideoPorBoton() {
      const url = prompt('Pega el link del video de YouTube:');
      if (!url) return;
      const embedUrl = normalizarUrlYoutube(url);
      historial.registrarCheckpoint();
      const iframe = document.createElement('iframe');
      iframe.className = 'ql-video';
      iframe.setAttribute('frameborder', '0');
      iframe.setAttribute('allowfullscreen', 'true');
      iframe.src = embedUrl;
      insertarNodoEnCursor(raiz, iframe);
      historial.registrarCheckpoint();
      dispararTextChange();
    }

    // --- Audio protegido (admin-only) ---
    function subirAudioProtegido() {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'audio/*';
      input.addEventListener('change', async function () {
        const archivo = input.files[0];
        if (!archivo) return;
        const datos = new FormData();
        datos.append('audio', archivo);
        datos.append('csrf_token', opciones.csrfToken);
        try {
          const res = await fetch(opciones.endpointAudio, { method: 'POST', body: datos });
          const data = await res.json();
          if (data.success) {
            historial.registrarCheckpoint();
            const nodo = document.createElement('div');
            nodo.className = 'pf-audio-embed';
            nodo.setAttribute('data-audio-id', data.id);
            nodo.setAttribute('contenteditable', 'false');
            nodo.textContent = '🔊 ' + data.nombre;
            insertarNodoEnCursor(raiz, nodo);
            historial.registrarCheckpoint();
            dispararTextChange();
          } else {
            avisarError(data.message || 'No se pudo subir el audio.');
          }
        } catch (e) {
          avisarError('Error de conexión subiendo el audio.');
        }
      });
      input.click();
    }

    // --- Archivo adjunto (admin-only) ---
    function subirArchivoAdjunto() {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = '.pdf,.zip,.epub';
      input.addEventListener('change', async function () {
        const archivo = input.files[0];
        if (!archivo) return;
        const datos = new FormData();
        datos.append('archivo', archivo);
        datos.append('csrf_token', opciones.csrfToken);
        try {
          const res = await fetch(opciones.endpointAdjunto, { method: 'POST', body: datos });
          const data = await res.json();
          if (data.success) {
            historial.registrarCheckpoint();
            const enlace = document.createElement('a');
            enlace.href = data.url;
            enlace.textContent = '📎 ' + data.nombre;
            insertarNodoEnCursor(raiz, enlace);
            historial.registrarCheckpoint();
            dispararTextChange();
          } else {
            avisarError(data.message || 'No se pudo subir el archivo.');
          }
        } catch (e) {
          avisarError('Error de conexión subiendo el archivo.');
        }
      });
      input.click();
    }

    // --- Colapsable ---
    function insertarColapsable() {
      historial.registrarCheckpoint();
      const nodo = crearNodoColapsable();
      insertarNodoEnCursor(raiz, nodo);
      historial.registrarCheckpoint();
      dispararTextChange();
      seleccionarTextoDe(nodo.querySelector('summary'));
    }

    // Encuentra el <summary>/<div class="pf-colapsable-body-editable">
    // donde REALMENTE está el cursor. e.target de un evento de teclado
    // dentro de un contenteditable siempre reporta el elemento con foco de
    // documento (la raíz misma — un <summary> hijo nunca es un punto de
    // foco de documento independiente), así que nunca sirve para saber en
    // qué isla visual está escribiendo el usuario — hay que preguntarle al
    // Range/Selection activo, no al evento.
    function nodoDeIslaActual(selectorIsla) {
      const rango = obtenerRangoActual(raiz);
      if (!rango) return null;
      const nodo = rango.startContainer.nodeType === Node.ELEMENT_NODE ? rango.startContainer : rango.startContainer.parentElement;
      return nodo ? nodo.closest(selectorIsla) : null;
    }

    // Ctrl/Cmd+Enter dentro del cuerpo de un colapsable: sale de él y crea
    // un párrafo nuevo justo después, con el cursor ahí. Sin este atajo,
    // Enter normal dentro del cuerpo solo crea líneas nuevas DENTRO de él
    // (comportamiento nativo esperado de un contenteditable normal — no
    // hay forma de que el navegador "adivine" cuándo el usuario quiere
    // salir en vez de seguir escribiendo dentro del bloque).
    function salirDelColapsable(cuerpoOColapsable) {
      const colapsable = cuerpoOColapsable.closest('.pf-colapsable-embed');
      if (!colapsable) return false;
      const parrafo = document.createElement('p');
      parrafo.innerHTML = '<br>';
      colapsable.parentNode.insertBefore(parrafo, colapsable.nextSibling);
      const rango = document.createRange();
      rango.selectNodeContents(parrafo);
      rango.collapse(true);
      const seleccion = window.getSelection();
      seleccion.removeAllRanges();
      seleccion.addRange(rango);
      return true;
    }

    // Enter en <summary> mueve el cursor al cuerpo (decisión de producto: el
    // título es de una sola línea) — único comportamiento especial que
    // necesitan los colapsables, ya sin nada de aislamiento de eventos.
    raiz.addEventListener('keydown', function (e) {
      // Ctrl/Cmd+A dentro de un <summary> o del cuerpo de un colapsable:
      // sin contenteditable anidado (a propósito, ver el resto de este
      // archivo), un <summary>/<div> hijo NO es su propio "editing host" —
      // Ctrl+A nativo del navegador selecciona TODO el documento del
      // editor, no solo esa isla. Sin este freno, Backspace tras ese Ctrl+A
      // borraba el editor COMPLETO en vez de solo el colapsable (bug real
      // reportado: "no se eliminan los colapsables creados"). Se limita la
      // selección a la isla actual — así Backspace/Delete después sí borra
      // solo lo que el usuario ve seleccionado.
      if (cap.colapsables && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
        const isla = nodoDeIslaActual('summary') || nodoDeIslaActual('.pf-colapsable-body-editable');
        if (isla) {
          e.preventDefault();
          const rango = document.createRange();
          rango.selectNodeContents(isla);
          const seleccion = window.getSelection();
          seleccion.removeAllRanges();
          seleccion.addRange(rango);
          return;
        }
      }
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        const cuerpo = nodoDeIslaActual('.pf-colapsable-body-editable') || nodoDeIslaActual('summary');
        if (cuerpo && salirDelColapsable(cuerpo)) {
          e.preventDefault();
          historial.registrarCheckpoint();
          dispararTextChange();
          return;
        }
      }
      // Enter dentro de un título (H2/H3): sin esto, execCommand('insertParagraph')
      // nativo del navegador crea un <div> (Chrome) que sigue en el mismo
      // formato de bloque — el texto siguiente se queda "grande" como si
      // fuera parte del título. Se intercepta y se fuerza explícitamente a
      // <p> normal, dejando el título intacto como línea propia.
      if (e.key === 'Enter' && !e.shiftKey) {
        const nodo = rango => rango.startContainer.nodeType === Node.TEXT_NODE ? rango.startContainer.parentElement : rango.startContainer;
        const rangoActual = obtenerRangoActual(raiz);
        const encabezado = rangoActual ? (nodo(rangoActual) ? nodo(rangoActual).closest('h1,h2,h3,h4,h5,h6') : null) : null;
        if (encabezado && raiz.contains(encabezado)) {
          e.preventDefault();
          historial.registrarCheckpoint();
          // Si el cursor está en medio del título, lo que quede después se
          // mueve al párrafo nuevo (partir el título), en vez de perderse.
          const rangoResto = rangoActual.cloneRange();
          rangoResto.setEndAfter(encabezado.lastChild || encabezado);
          const resto = rangoResto.extractContents();
          const parrafo = document.createElement('p');
          if (resto.textContent.trim() === '' && !resto.querySelector('img,iframe')) {
            parrafo.innerHTML = '<br>';
          } else {
            parrafo.appendChild(resto);
          }
          if (!encabezado.hasChildNodes()) encabezado.innerHTML = '<br>';
          encabezado.parentNode.insertBefore(parrafo, encabezado.nextSibling);
          const rangoNuevo = document.createRange();
          rangoNuevo.selectNodeContents(parrafo);
          rangoNuevo.collapse(true);
          const seleccionNueva = window.getSelection();
          seleccionNueva.removeAllRanges();
          seleccionNueva.addRange(rangoNuevo);
          historial.registrarCheckpoint();
          dispararTextChange();
          return;
        }
      }
      const resumen = e.key === 'Enter' ? nodoDeIslaActual('summary') : null;
      if (resumen) {
        e.preventDefault();
        const cuerpo = resumen.parentElement.querySelector('.pf-colapsable-body-editable');
        if (cuerpo) {
          const rango = document.createRange();
          rango.selectNodeContents(cuerpo);
          rango.collapse(true);
          const seleccion = window.getSelection();
          seleccion.removeAllRanges();
          seleccion.addRange(rango);
        }
        return;
      }
      // Deshacer/rehacer propios — se instalan aquí mismo porque solo
      // aplican mientras el foco está dentro de la superficie de edición.
      if (cap.undoRedo) {
        const ctrlOCmd = e.ctrlKey || e.metaKey;
        if (ctrlOCmd && !e.shiftKey && e.key.toLowerCase() === 'z') {
          e.preventDefault();
          historial.deshacer();
          return;
        }
        if (ctrlOCmd && ((e.shiftKey && e.key.toLowerCase() === 'z') || e.key.toLowerCase() === 'y')) {
          e.preventDefault();
          historial.rehacer();
          return;
        }
      }
    });

    // Botón "×" de cada colapsable — delegado desde raiz porque los
    // bloques se insertan dinámicamente. mousedown con preventDefault evita
    // que el clic mueva el cursor de edición antes de procesar el borrado.
    if (cap.colapsables) {
      raiz.addEventListener('mousedown', function (e) {
        if (e.target.closest && e.target.closest('.pf-colapsable-embed-eliminar')) e.preventDefault();
      });
      raiz.addEventListener('click', function (e) {
        const boton = e.target.closest && e.target.closest('.pf-colapsable-embed-eliminar');
        if (!boton) return;
        const colapsable = boton.closest('.pf-colapsable-embed');
        if (!colapsable) return;
        historial.registrarCheckpoint();
        colapsable.remove();
        historial.registrarCheckpoint();
        dispararTextChange();
      });
    }

    raiz.addEventListener('input', function () {
      historial.marcarSucioConDebounce();
      dispararTextChange();
    });
    // Al salir del editor (clic afuera, cambiar de pestaña) se cierra
    // cualquier checkpoint pendiente del debounce — si no, un cambio hecho
    // justo antes de perder el foco podía quedar fuera del historial hasta
    // el siguiente tecleo dentro del editor.
    raiz.addEventListener('blur', function () {
      historial.registrarCheckpoint();
    });

    // === Arrastrar bloques para reordenar ===
    //
    // Cada hijo de nivel superior de `raiz` (párrafo, imagen, colapsable,
    // video, lista, etc.) es una unidad arrastrable — un "handle" (⠿)
    // aparece a su izquierda al pasar el mouse encima (ver CSS en
    // instalarCss) y se usa Drag and Drop nativo del navegador (no una
    // librería) para reordenar. El handle mismo tiene draggable=true (no el
    // bloque completo) para no competir con la selección normal de texto o
    // el drag nativo de una <img> dentro del contenido.
    let nodoArrastrado = null;

    function reinstalarHandlesDeArrastre() {
      Array.prototype.forEach.call(raiz.children, function (bloque) {
        if (!bloque.querySelector(':scope > .pf-editor-drag-handle')) {
          const handle = document.createElement('span');
          handle.className = 'pf-editor-drag-handle';
          handle.setAttribute('contenteditable', 'false');
          handle.setAttribute('draggable', 'true');
          handle.title = 'Arrastra para reordenar';
          handle.textContent = '⠿';
          bloque.insertBefore(handle, bloque.firstChild);
        }
      });
    }
    raiz.setAttribute('data-arrastre-activo', '');
    reinstalarHandlesDeArrastre();

    // Sube desde `nodo` hasta encontrar el hijo directo de `raiz` que lo
    // contiene (o es él mismo) — closest(':scope > *') NO sirve aquí: el
    // ':scope' de closest() se ancla al propio nodo de partida, no a `raiz`.
    function hijoDirectoDeRaizQueContiene(nodo) {
      let actual = nodo;
      while (actual && actual.parentElement !== raiz) {
        actual = actual.parentElement;
      }
      return actual && actual.parentElement === raiz ? actual : null;
    }

    raiz.addEventListener('dragstart', function (e) {
      const handle = e.target.closest && e.target.closest('.pf-editor-drag-handle');
      if (!handle) { e.preventDefault(); return; }
      nodoArrastrado = handle.parentElement;
      e.dataTransfer.effectAllowed = 'move';
      // Un dataTransfer vacío es necesario para que algunos navegadores
      // completen el gesto de arrastre — el contenido real nunca se lee,
      // el reordenamiento ocurre por referencia directa al nodo DOM.
      try { e.dataTransfer.setData('text/plain', ''); } catch (err) { /* Firefox exige setData para iniciar el drag */ }
      requestAnimationFrame(function () { if (nodoArrastrado) nodoArrastrado.classList.add('pf-arrastrando'); });
    });

    raiz.addEventListener('dragover', function (e) {
      if (!nodoArrastrado) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      const destino = e.target.nodeType === Node.ELEMENT_NODE ? hijoDirectoDeRaizQueContiene(e.target) : null;
      raiz.querySelectorAll('.pf-arrastre-indicador').forEach(function (n) { n.classList.remove('pf-arrastre-indicador'); });
      if (destino && destino !== nodoArrastrado) {
        destino.classList.add('pf-arrastre-indicador');
      }
    });

    raiz.addEventListener('drop', function (e) {
      if (!nodoArrastrado) return;
      e.preventDefault();
      const destino = e.target.nodeType === Node.ELEMENT_NODE ? hijoDirectoDeRaizQueContiene(e.target) : null;
      raiz.querySelectorAll('.pf-arrastre-indicador').forEach(function (n) { n.classList.remove('pf-arrastre-indicador'); });
      if (destino && destino !== nodoArrastrado) {
        historial.registrarCheckpoint();
        const rectDestino = destino.getBoundingClientRect();
        const insertarDespues = e.clientY > rectDestino.top + rectDestino.height / 2;
        raiz.insertBefore(nodoArrastrado, insertarDespues ? destino.nextSibling : destino);
        historial.registrarCheckpoint();
        dispararTextChange();
      }
    });

    raiz.addEventListener('dragend', function () {
      if (nodoArrastrado) nodoArrastrado.classList.remove('pf-arrastrando');
      raiz.querySelectorAll('.pf-arrastre-indicador').forEach(function (n) { n.classList.remove('pf-arrastre-indicador'); });
      nodoArrastrado = null;
    });

    // === Vista de código HTML (siempre disponible) ===
    function alternarVistaCodigo() {
      if (!modoCodigo) {
        // El botón "×" de cada colapsable es control de UI, no contenido
        // real — no debe verse ni editarse como si fuera parte del HTML.
        textareaCodigo.value = htmlSinControlesDeEditor(raiz.innerHTML);
        raiz.style.display = 'none';
        textareaCodigo.style.display = 'block';
        modoCodigo = true;
      } else {
        historial.registrarCheckpoint();
        raiz.innerHTML = textareaCodigo.value;
        if (cap.colapsables) reinstalarBotonesEliminarColapsable(raiz);
        reinstalarHandlesDeArrastre();
        historial.registrarCheckpoint();
        raiz.style.display = '';
        textareaCodigo.style.display = 'none';
        modoCodigo = false;
        dispararTextChange();
      }
    }

    // === Toolbar ===
    function agregarGrupo() {
      const grupo = document.createElement('div');
      grupo.className = 'pf-editor-grupo';
      toolbarEl.appendChild(grupo);
      return grupo;
    }
    // `comandoEstado` (opcional): nombre para document.queryCommandState —
    // si se pasa, este botón se registra en `indicadoresEstado` para que
    // actualizarEstadoToolbar() le agregue/quite la clase "activo" según la
    // selección actual (ver más abajo). Sin esto, un botón como Negrita no
    // reflejaba si el cursor ya estaba sobre texto en negrita — el usuario
    // no tenía forma de saber qué formato tenía lo seleccionado.
    function agregarBoton(grupo, icono, titulo, onClick, comandoEstado) {
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'pf-editor-boton';
      boton.title = titulo;
      boton.textContent = icono;
      boton.addEventListener('mousedown', function (e) { e.preventDefault(); }); // no perder el foco/selección del editor
      boton.addEventListener('click', onClick);
      grupo.appendChild(boton);
      if (comandoEstado) {
        indicadoresEstado.push(function () {
          let activo = false;
          try { activo = document.queryCommandState(comandoEstado); } catch (e) { /* comando no soportado en este navegador */ }
          boton.classList.toggle('activo', activo);
        });
      }
      return boton;
    }
    // Callbacks que actualizarEstadoToolbar() ejecuta en cada cambio de
    // selección — cada botón/select con estado que mostrar se registra aquí
    // (botones vía el parámetro comandoEstado de agregarBoton, selects a
    // mano donde el mapeo no es 1:1 con queryCommandState).
    const indicadoresEstado = [];
    let temporizadorEstadoToolbar = null;
    function actualizarEstadoToolbar() {
      // Solo tiene sentido reflejar el formato si el foco/selección está
      // realmente dentro de ESTE editor — con varias instancias en la misma
      // página (ej. foro/tema.php), "selectionchange" es un evento global
      // de `document`, no de cada editor.
      const seleccion = window.getSelection();
      if (!seleccion.rangeCount || !raiz.contains(seleccion.getRangeAt(0).commonAncestorContainer)) return;
      indicadoresEstado.forEach(function (fn) { fn(); });
    }
    function actualizarEstadoToolbarDiferido() {
      // queryCommandState puede reportar el estado ANTERIOR si se consulta
      // en el mismo tick que el evento que movió la selección (ej. clic) —
      // se difiere un frame para leerlo ya asentado.
      clearTimeout(temporizadorEstadoToolbar);
      temporizadorEstadoToolbar = setTimeout(actualizarEstadoToolbar, 0);
    }
    document.addEventListener('selectionchange', actualizarEstadoToolbarDiferido);
    raiz.addEventListener('keyup', actualizarEstadoToolbarDiferido);
    raiz.addEventListener('mouseup', actualizarEstadoToolbarDiferido);

    // Wrapper de comandos execCommand: registra un checkpoint de historial
    // ANTES y DESPUÉS de cada comando de toolbar (no solo de tecleo libre),
    // para que Ctrl+Z inmediatamente después de, por ejemplo, aplicar
    // negrita, la deshaga en un solo paso sin esperar el debounce de
    // tecleo — el debounce es para ráfagas de escritura, no para acciones
    // discretas de un clic de botón.
    function comando(nombre, valor) {
      historial.registrarCheckpoint();
      ejecutarComandoBasico(nombre, valor);
      historial.registrarCheckpoint();
      dispararTextChange();
      raiz.focus();
    }

    if (cap.tamanoAlineacion) {
      const grupoBloque = agregarGrupo();
      const selectEncabezado = document.createElement('select');
      selectEncabezado.className = 'pf-editor-select';
      [['p', 'Normal'], ['h2', 'Título 2'], ['h3', 'Título 3']].forEach(function (par) {
        const opt = document.createElement('option');
        opt.value = par[0];
        opt.textContent = par[1];
        selectEncabezado.appendChild(opt);
      });
      selectEncabezado.addEventListener('mousedown', function (e) { e.stopPropagation(); });
      selectEncabezado.addEventListener('change', function () {
        comando('formatBlock', '<' + selectEncabezado.value + '>');
      });
      grupoBloque.appendChild(selectEncabezado);
      // Refleja el bloque real del cursor en vez de resetear siempre a
      // "Normal" — así el usuario ve qué formato tiene el texto donde está
      // parado, y puede quitar un título seleccionando "Normal" de vuelta.
      indicadoresEstado.push(function () {
        let valor = 'p';
        try {
          const bloque = (document.queryCommandValue('formatBlock') || '').toLowerCase();
          if (bloque === 'h2' || bloque === 'h3') valor = bloque;
        } catch (e) { /* no soportado */ }
        selectEncabezado.value = valor;
      });

      const selectTamano = document.createElement('select');
      selectTamano.className = 'pf-editor-select';
      const opcionVacia = document.createElement('option');
      opcionVacia.value = '';
      opcionVacia.textContent = 'Tamaño';
      selectTamano.appendChild(opcionVacia);
      ['12px', '14px', '16px', '18px', '20px', '24px', '32px', '48px'].forEach(function (px) {
        const opt = document.createElement('option');
        opt.value = px;
        opt.textContent = px;
        selectTamano.appendChild(opt);
      });
      selectTamano.addEventListener('mousedown', function (e) { e.stopPropagation(); });
      selectTamano.addEventListener('change', function () {
        if (selectTamano.value) {
          historial.registrarCheckpoint();
          aplicarTamanoFuente(raiz, selectTamano.value);
          historial.registrarCheckpoint();
          dispararTextChange();
        }
        selectTamano.value = '';
        raiz.focus();
      });
      grupoBloque.appendChild(selectTamano);
    }

    const grupoTexto = agregarGrupo();
    agregarBoton(grupoTexto, 'B', 'Negrita', function () { comando('bold'); }, 'bold');
    agregarBoton(grupoTexto, 'I', 'Cursiva', function () { comando('italic'); }, 'italic');
    if (cap.tamanoAlineacion) {
      agregarBoton(grupoTexto, 'U', 'Subrayado', function () { comando('underline'); }, 'underline');
      agregarBoton(grupoTexto, 'S', 'Tachado', function () { comando('strikeThrough'); }, 'strikeThrough');
    }

    const grupoListas = agregarGrupo();
    agregarBoton(grupoListas, '≡', 'Lista numerada', function () { comando('insertOrderedList'); }, 'insertOrderedList');
    agregarBoton(grupoListas, '•', 'Lista con viñetas', function () { comando('insertUnorderedList'); }, 'insertUnorderedList');

    if (cap.tamanoAlineacion) {
      const grupoAlineacion = agregarGrupo();
      agregarBoton(grupoAlineacion, '⯇', 'Alinear a la izquierda', function () { comando('justifyLeft'); }, 'justifyLeft');
      agregarBoton(grupoAlineacion, '☰', 'Centrar', function () { comando('justifyCenter'); }, 'justifyCenter');
      agregarBoton(grupoAlineacion, '⯈', 'Alinear a la derecha', function () { comando('justifyRight'); }, 'justifyRight');
    }

    const grupoInsercion = agregarGrupo();
    agregarBoton(grupoInsercion, '❝', 'Cita', function () { comando('formatBlock', 'blockquote'); });
    if (cap.codeBlock) {
      agregarBoton(grupoInsercion, '</>', 'Bloque de código', function () {
        historial.registrarCheckpoint();
        insertarBloqueCodigo(raiz);
        historial.registrarCheckpoint();
        dispararTextChange();
      });
    }
    agregarBoton(grupoInsercion, '🔗', 'Enlace', function () {
      const url = prompt('Pega el link:');
      if (url) comando('createLink', url);
    });
    agregarBoton(grupoInsercion, '🖼', 'Insertar imagen', insertarImagenPorBoton);
    if (cap.video) agregarBoton(grupoInsercion, '▶', 'Insertar video de YouTube', insertarVideoPorBoton);

    if (cap.audio || cap.adjuntos || cap.colapsables) {
      const grupoEmbeds = agregarGrupo();
      if (cap.audio) agregarBoton(grupoEmbeds, '🔊', 'Subir audio', subirAudioProtegido);
      if (cap.adjuntos) agregarBoton(grupoEmbeds, '📎', 'Adjuntar archivo', subirArchivoAdjunto);
      if (cap.colapsables) agregarBoton(grupoEmbeds, '▾', 'Insertar sección colapsable (Ctrl+Enter dentro del cuerpo para salir)', insertarColapsable);
    }

    if (cap.undoRedo) {
      const grupoHistorial = agregarGrupo();
      agregarBoton(grupoHistorial, '↶', 'Deshacer', function () { historial.deshacer(); });
      agregarBoton(grupoHistorial, '↷', 'Rehacer', function () { historial.rehacer(); });
    }

    // --- Pantalla completa: el wrap sale del flujo normal de la página
    // (position:fixed sobre todo lo demás) para dar más espacio de edición
    // sin cambiar nada del contrato de datos — sincronizar()/quill.root
    // siguen leyendo del mismo `raiz`, pantalla completa es puramente
    // visual. Esc también sale, igual que cualquier overlay de la plataforma.
    let enPantallaCompleta = false;
    function alternarPantallaCompleta() {
      enPantallaCompleta = !enPantallaCompleta;
      wrap.classList.toggle('pf-editor-pantalla-completa', enPantallaCompleta);
      document.body.classList.toggle('pf-editor-bloqueo-scroll', enPantallaCompleta);
      botonPantallaCompleta.textContent = enPantallaCompleta ? '⤡' : '⤢';
      botonPantallaCompleta.title = enPantallaCompleta ? 'Salir de pantalla completa (Esc)' : 'Pantalla completa';
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && enPantallaCompleta) alternarPantallaCompleta();
    });

    const grupoFinal = agregarGrupo();
    agregarBoton(grupoFinal, '⌫', 'Limpiar formato', function () { comando('removeFormat'); });
    agregarBoton(grupoFinal, '</>', 'Ver/editar código HTML', alternarVistaCodigo);
    const botonPantallaCompleta = agregarBoton(grupoFinal, '⤢', 'Pantalla completa', alternarPantallaCompleta);
    // Indicador de guardado (autosave de servidor / borrador local) — vive
    // aquí, junto al botón de código, en vez de que cada una de las 6
    // páginas tenga que colocar su propio <span> suelto cerca de los
    // botones de Guardar/Publicar. conectarBorradorLocal()/
    // conectarAutosaveServidor() lo usan automáticamente si el llamador no
    // pasa su propio indicadorSelector.
    const indicadorGuardado = document.createElement('span');
    indicadorGuardado.className = 'pf-editor-indicador-guardado ms-1';
    indicadorGuardado.id = generarId(); // conectarAutosaveServidor() lo pasa a PfAutosave como indicadorSelector (string), no como elemento
    grupoFinal.appendChild(indicadorGuardado);

    // --- Ayuda (?) con los comportamientos no evidentes del editor ---
    const grupoAyuda = agregarGrupo();
    grupoAyuda.style.marginLeft = 'auto'; // pegado a la derecha de la toolbar, no revuelto con el resto de botones
    grupoAyuda.style.borderRight = 'none';
    instalarBotonAyuda(grupoAyuda, cap);

    return {
      quill: quillCompat,
      sincronizar: function () { return modoCodigo ? textareaCodigo.value : htmlSinControlesDeEditor(raiz.innerHTML); },
      hayCargasPendientes: function () { return quillCompat.pfSubidasPendientes > 0; },
      bloquearSiHayCargasPendientes: function (mensaje) {
        if (quillCompat.pfSubidasPendientes > 0) {
          alert(mensaje || 'Espera un momento — todavía se está subiendo una imagen pegada.');
          return true;
        }
        return false;
      },
      indicadorGuardadoEl: indicadorGuardado,
    };
  }

  // --- Borrador en localStorage (foro) — un tema/respuesta nuevo no existe
  // todavía y publicarlo a medias mientras se escribe lo haría visible para
  // cualquiera en el foro antes de tiempo, por eso este autoguardado es
  // solo local, nunca al servidor.
  function conectarBorradorLocal(instancia, storageKey, opciones) {
    opciones = opciones || {};
    const quill = instancia.quill;
    const campoTitulo = opciones.campoTitulo || null;
    const confirmarRestaurar = !!opciones.confirmarRestaurar;
    // Por default se pinta junto al botón "</>" de la propia toolbar del
    // editor (instancia.indicadorGuardadoEl); indicadorSelector sigue
    // aceptándose para ubicarlo en otro lugar si algún sitio lo necesita.
    const indicador = opciones.indicadorSelector ? document.querySelector(opciones.indicadorSelector) : instancia.indicadorGuardadoEl;
    const retraso = opciones.retrasoMs || 1500;

    function pintarIndicador(html) {
      if (indicador) indicador.innerHTML = html;
    }

    const SPINNER_HTML = '<span class="spinner-border spinner-border-sm text-muted" role="status" aria-hidden="true"></span>';
    let intervaloCuenta = null;
    function detenerCuentaRegresiva() {
      clearTimeout(intervaloCuenta);
      intervaloCuenta = null;
    }
    function iniciarCuentaRegresiva() {
      detenerCuentaRegresiva();
      pintarIndicador(SPINNER_HTML);
      intervaloCuenta = setTimeout(detenerCuentaRegresiva, retraso);
    }

    let guardado = null;
    try {
      const crudo = localStorage.getItem(storageKey);
      guardado = crudo ? JSON.parse(crudo) : null;
    } catch (e) { /* localStorage no disponible o dato corrupto: se ignora */ }

    if (guardado && guardado.contenido) {
      const htmlActual = quill.root.innerHTML.trim();
      const vacio = htmlActual === '' || htmlActual === '<p><br></p>' || htmlActual === '<p><br/></p>';
      if (vacio || (confirmarRestaurar && confirm('Tienes un borrador sin guardar de una edición anterior de este texto. ¿Quieres recuperarlo?'))) {
        quill.root.innerHTML = guardado.contenido;
        if (campoTitulo && guardado.titulo) campoTitulo.value = guardado.titulo;
      }
    }

    let temporizador = null;
    function guardar() {
      detenerCuentaRegresiva();
      try {
        localStorage.setItem(storageKey, JSON.stringify({
          contenido: quill.root.innerHTML,
          titulo: campoTitulo ? campoTitulo.value : undefined
        }));
        pintarIndicador('<span class="text-success"><i class="bi bi-check-lg"></i> Borrador guardado en este navegador</span>');
        setTimeout(function () { pintarIndicador(''); }, 2500);
      } catch (e) {
        pintarIndicador('');
      }
    }
    function marcarSucio() {
      clearTimeout(temporizador);
      temporizador = setTimeout(guardar, retraso);
      iniciarCuentaRegresiva();
    }
    quill.on('text-change', marcarSucio);
    if (campoTitulo) {
      campoTitulo.addEventListener('input', marcarSucio);
    }

    return {
      limpiar: function () {
        clearTimeout(temporizador);
        detenerCuentaRegresiva();
        pintarIndicador('');
        try { localStorage.removeItem(storageKey); } catch (e) {}
      }
    };
  }

  // --- Autosave de servidor (admin) — delega en PfAutosave (_autosave.js),
  // ya probado; solo evita repetir el "quills: [instancia.quill]" a mano.
  // Por default pinta el indicador junto al botón "</>" de la propia
  // toolbar del editor (PfAutosave solo acepta un selector string, no un
  // elemento, por eso el indicador se creó con un id propio) —
  // indicadorSelector sigue aceptándose para ubicarlo en otro lugar.
  function conectarAutosaveServidor(instancia, opcionesPfAutosave) {
    const opts = Object.assign({}, opcionesPfAutosave);
    opts.quills = (opcionesPfAutosave.quills || []).concat([instancia.quill]);
    if (!opts.indicadorSelector && instancia.indicadorGuardadoEl) {
      opts.indicadorSelector = '#' + instancia.indicadorGuardadoEl.id;
    }
    window.PfAutosave.iniciar(opts);
  }

  return {
    crear: crear,
    normalizarUrlYoutube: normalizarUrlYoutube,
    conectarBorradorLocal: conectarBorradorLocal,
    conectarAutosaveServidor: conectarAutosaveServidor,
  };
})();

// Alias por compatibilidad, por si algún sitio quedara sin migrar el nombre
// de la llamada — apunta al mismo objeto, no es una copia.
window.PfEditorQuill = window.PfEditor;
