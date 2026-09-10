<?php
// Editor unificado de lecciones — todo el contenido (texto, imágenes, video
// de YouTube, audio protegido, PDFs/materiales) se construye dentro de un
// solo editor (Quill), en el orden que el admin quiera, en vez de varios
// formularios/listas separadas por tipo. El quiz sigue siendo su propio
// tipo aparte (panel/admin/quiz_form.php) — no encaja en "contenido que se
// escribe y se le insertan cosas", tiene su propia estructura de preguntas.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
requerir_csrf_form();

// Una lección pertenece a un curso O a un evento, nunca ambos (ver
// schema_lecciones_compartidas.sql).
$cursoId = (int) ($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
$eventoId = (int) ($_GET['evento_id'] ?? $_POST['evento_id'] ?? 0);
$esEvento = $eventoId > 0;
$padreId = $esEvento ? $eventoId : $cursoId;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($esEvento) {
    $stmt = $conn->prepare('SELECT id, titulo FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $padreId);
    $stmt->execute();
    $padre = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $volverUrl = 'lecciones.php?evento_id=' . $padreId;
} else {
    $stmt = $conn->prepare('SELECT id, titulo FROM cursos WHERE id = ?');
    $stmt->bind_param('i', $padreId);
    $stmt->execute();
    $padre = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $volverUrl = 'lecciones.php?curso_id=' . $padreId;
}
if (!$padre) {
    header('Location: ' . ($esEvento ? 'eventos.php' : 'cursos.php'));
    exit;
}

$leccion = ['titulo' => '', 'descripcion' => '', 'tipo_contenido' => 'contenido', 'contenido_texto' => '',
            'orden' => 0, 'duracion_min' => '', 'vista_previa' => 0, 'foro_url' => '', 'estado_publicacion' => 'borrador'];

if ($id) {
    $stmt = $esEvento
        ? $conn->prepare('SELECT * FROM lecciones WHERE id = ? AND evento_id = ?')
        : $conn->prepare('SELECT * FROM lecciones WHERE id = ? AND curso_id = ?');
    $stmt->bind_param('ii', $id, $padreId);
    $stmt->execute();
    $leccion = $stmt->get_result()->fetch_assoc() ?: $leccion;
    $stmt->close();
} else {
    $columna = $esEvento ? 'evento_id' : 'curso_id';
    $max = $conn->query("SELECT COALESCE(MAX(orden),0) m FROM lecciones WHERE $columna = " . $padreId)->fetch_assoc()['m'];
    $leccion['orden'] = (int) $max + 1;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') !== 'eliminar_leccion') {
    $esAjax = es_peticion_ajax();
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = ($_POST['tipo_contenido'] ?? 'contenido') === 'quiz' ? 'quiz' : 'contenido';
    $contenidoTexto = trim($_POST['contenido_texto'] ?? '');
    // El editor Quill vacío manda "<p><br></p>" en vez de una cadena vacía
    // — se normaliza para no guardar ese HTML como si fuera contenido real.
    if (trim(str_replace(['<p><br></p>', '<p><br/></p>'], '', $contenidoTexto)) === '') {
        $contenidoTexto = '';
    }
    $orden = (int) ($_POST['orden'] ?? 0);
    $duracion = $_POST['duracion_min'] !== '' ? (int) $_POST['duracion_min'] : null;
    $vistaPrevia = isset($_POST['vista_previa']) ? 1 : 0;
    $foroUrl = trim($_POST['foro_url'] ?? '');

    // "Guardar borrador" / "Guardar" (publicar) / "Vista previa" (guarda sin
    // tocar el estado de publicación que ya tenía) — ver el JS de los 3
    // botones, cada uno fija este campo antes de enviar.
    $accionPublicacion = $_POST['accion_publicacion'] ?? 'publicar';
    if ($accionPublicacion === 'borrador') {
        $estadoPublicacion = 'borrador';
    } elseif ($accionPublicacion === 'publicar') {
        $estadoPublicacion = 'publicado';
    } else {
        $estadoPublicacion = $leccion['estado_publicacion'] ?? 'borrador';
    }

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } else {
        if ($id) {
            $stmt = $esEvento
                ? $conn->prepare('UPDATE lecciones SET titulo=?, descripcion=?, tipo_contenido=?, contenido_texto=?, orden=?, duracion_min=?, vista_previa=?, foro_url=?, estado_publicacion=? WHERE id=? AND evento_id=?')
                : $conn->prepare('UPDATE lecciones SET titulo=?, descripcion=?, tipo_contenido=?, contenido_texto=?, orden=?, duracion_min=?, vista_previa=?, foro_url=?, estado_publicacion=? WHERE id=? AND curso_id=?');
            $stmt->bind_param('ssssiiissii', $titulo, $descripcion, $tipo, $contenidoTexto, $orden, $duracion, $vistaPrevia, $foroUrl, $estadoPublicacion, $id, $padreId);
        } else {
            $stmt = $esEvento
                ? $conn->prepare('INSERT INTO lecciones (evento_id, titulo, descripcion, tipo_contenido, contenido_texto, orden, duracion_min, vista_previa, foro_url, estado_publicacion) VALUES (?,?,?,?,?,?,?,?,?,?)')
                : $conn->prepare('INSERT INTO lecciones (curso_id, titulo, descripcion, tipo_contenido, contenido_texto, orden, duracion_min, vista_previa, foro_url, estado_publicacion) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('issssiiiss', $padreId, $titulo, $descripcion, $tipo, $contenidoTexto, $orden, $duracion, $vistaPrevia, $foroUrl, $estadoPublicacion);
        }
        if ($stmt->execute()) {
            $leccionId = $id ?: $stmt->insert_id;
            $stmt->close();

            // Los audios protegidos se suben DESDE el editor (ver
            // backend/leccion_audio_embed_subir.php) antes incluso de que
            // exista la lección — nacen con leccion_id NULL. Aquí se
            // reclaman los que quedaron referenciados en el contenido final
            // (data-audio-id="N") y se liberan (borran el archivo también)
            // los que ya estaban ligados a esta lección pero el admin quitó
            // del texto — para no dejar audios protegidos huérfanos sin
            // dueño ni archivos sueltos en el servidor.
            preg_match_all('/data-audio-id="(\d+)"/', $contenidoTexto, $coincidencias);
            $idsReferenciados = array_values(array_unique(array_map('intval', $coincidencias[1])));

            if ($idsReferenciados) {
                $placeholders = implode(',', array_fill(0, count($idsReferenciados), '?'));
                $tipos = str_repeat('i', count($idsReferenciados));
                $stmt2 = $conn->prepare("UPDATE leccion_audios SET leccion_id = ? WHERE id IN ($placeholders) AND leccion_id IS NULL");
                $stmt2->bind_param('i' . $tipos, $leccionId, ...$idsReferenciados);
                $stmt2->execute();
                $stmt2->close();

                $stmtSel = $conn->prepare("SELECT id, ruta_archivo FROM leccion_audios WHERE leccion_id = ? AND id NOT IN ($placeholders)");
                $stmtSel->bind_param('i' . $tipos, $leccionId, ...$idsReferenciados);
            } else {
                $stmtSel = $conn->prepare('SELECT id, ruta_archivo FROM leccion_audios WHERE leccion_id = ?');
                $stmtSel->bind_param('i', $leccionId);
            }
            $stmtSel->execute();
            $huerfanos = $stmtSel->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSel->close();
            foreach ($huerfanos as $h) {
                $rutaFisica = __DIR__ . '/../../' . $h['ruta_archivo'];
                if (is_file($rutaFisica)) {
                    @unlink($rutaFisica);
                }
            }
            if ($huerfanos) {
                $conn->query('DELETE FROM leccion_audios WHERE id IN (' . implode(',', array_column($huerfanos, 'id')) . ')');
            }

            if ($esAjax) {
                // "Guardar borrador" y el autoguardado se quedan en el editor
                // (sin redirect) para poder seguir editando; solo "Guardar y
                // publicar" regresa al listado, como antes.
                $respuesta = ['success' => true, 'leccion_id' => $leccionId, 'estado_publicacion' => $estadoPublicacion];
                if ($accionPublicacion === 'publicar') {
                    $respuesta['redirect'] = $volverUrl;
                }
                echo json_encode($respuesta);
                exit;
            }
            header('Location: ' . $volverUrl);
            exit;
        }
        $error = '¿El orden ya está usado en ' . ($esEvento ? 'este evento' : 'este curso') . '?';
        $stmt->close();
    }
    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$pageTitle = $id ? 'Editar lección' : 'Nueva lección';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($padre['titulo']) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" data-ajax-form id="formLeccion">
  <?= csrf_field() ?>
  <input type="hidden" name="<?= $esEvento ? 'evento_id' : 'curso_id' ?>" value="<?= $padreId ?>">
  <input type="hidden" name="id" id="leccionIdOculto" value="<?= (int) $id ?>">
  <input type="hidden" name="accion_publicacion" id="accionPublicacion" value="publicar">
  <div class="col-md-8"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($leccion['titulo']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Orden</label><input type="number" class="form-control" name="orden" value="<?= (int) $leccion['orden'] ?>"></div>
  <div class="col-12"><label class="form-label">Descripción breve</label><textarea class="form-control" name="descripcion" rows="2"><?= htmlspecialchars((string) $leccion['descripcion']) ?></textarea></div>
  <div class="col-md-4">
    <label class="form-label">Tipo</label>
    <select class="form-select" name="tipo_contenido" id="tipoContenido">
      <option value="contenido" <?= $leccion['tipo_contenido'] === 'contenido' ? 'selected' : '' ?>>Contenido (texto, video, audio, etc.)</option>
      <option value="quiz" <?= $leccion['tipo_contenido'] === 'quiz' ? 'selected' : '' ?>>Quiz</option>
    </select>
  </div>
  <div class="col-md-4"><label class="form-label">Duración (min)</label><input type="number" class="form-control" name="duracion_min" value="<?= htmlspecialchars((string) $leccion['duracion_min']) ?>"></div>
  <div class="col-md-4 form-check form-switch mt-4">
    <input type="checkbox" class="form-check-input" role="switch" name="vista_previa" id="vista_previa" <?= (int) $leccion['vista_previa'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="vista_previa">Vista previa gratuita (demo)</label>
  </div>
  <?php
  $foroFieldId = 'leccion';
  $foroFieldValor = (string) $leccion['foro_url'];
  include __DIR__ . '/_foro_link_field.php';
  ?>

  <div class="col-12" id="wrapperEditorContenido">
    <label class="form-label">Contenido (solo si el tipo es "Contenido")</label>
    <div id="editorContenidoTexto" style="background:#fff;height:400px;"></div>
    <textarea name="contenido_texto" id="contenidoTextoOculta" class="d-none"></textarea>
    <div class="form-text">
      Usa la barra del editor para insertar imágenes, video de YouTube (🎬), audio protegido (🔊 — solo lo reproduce quien
      esté inscrito o sea vista previa), archivos adjuntos como PDF (📎) y secciones colapsables (▾ — Shift+Enter o Enter
      en una línea vacía sale de la sección; Enter al final del título crea el contenido), en el punto exacto donde quieras que aparezcan.
    </div>
  </div>

  <?php if ($id && $leccion['tipo_contenido'] === 'quiz'): ?>
    <div class="col-12">
      <p class="text-muted small mb-0">Las preguntas de este quiz se gestionan aparte. <a href="quiz_form.php?leccion_id=<?= (int) $id ?>">Gestionar preguntas</a>.</p>
    </div>
  <?php endif; ?>

  <div class="col-12 d-flex gap-2 flex-wrap align-items-center">
    <button type="submit" class="btn btn-success" id="btnGuardarPublicar">Guardar y publicar</button>
    <button type="button" class="btn btn-outline-secondary" id="btnGuardarBorrador">Guardar borrador</button>
    <span id="wrapperVistaPrevia"<?= $id ? '' : ' class="d-none"' ?>>
      <button type="button" class="btn btn-outline-primary" id="btnVistaPrevia">Vista previa</button>
    </span>
    <span class="align-self-center small text-muted">
      Estado actual:
      <span class="badge <?= ($leccion['estado_publicacion'] ?? 'borrador') === 'publicado' ? 'bg-success' : 'bg-secondary' ?>" id="badgeEstadoPublicacion">
        <?= ($leccion['estado_publicacion'] ?? 'borrador') === 'publicado' ? 'Publicado' : 'Borrador' ?>
      </span>
    </span>
  </div>
</form>

<!-- Editor Quill unificado — mismo patrón que panel/admin/contenido_form.php,
     con dos botones propios: video de YouTube (embebido nativo de Quill,
     con normalización del link igual que antes hacía el backend) y audio
     protegido (blot personalizado: sube el archivo y deja un bloque no
     editable con data-audio-id, que content/leccion.php convierte en el
     reproductor real al mostrarse). -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css">
<style>
  /* Vista dentro del editor del bloque de audio protegido — en la lección
     real (content/leccion.php) este div se sustituye por el reproductor. */
  .ql-editor .pf-audio-embed {
    background: #fff3e0;
    border: 1px dashed #f7931e;
    border-radius: 6px;
    padding: 8px 12px;
    margin-bottom: 10px;
    color: #8a5a00;
    font-size: 14px;
  }
  .ql-editor iframe.ql-video { display: block; width: 100%; aspect-ratio: 16 / 9; height: auto; }
  /* El picker de tamaño de Quill solo trae texto ("Small"/"Large"/"Huge")
     para su propia lista por defecto — con valores en px propios, sin esto
     cae en la regla genérica de abajo y todos se ven como "Normal". */
  .ql-picker.ql-size .ql-picker-label[data-value]:not([data-value=""])::before,
  .ql-picker.ql-size .ql-picker-item[data-value]:not([data-value=""])::before {
    content: attr(data-value);
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
// Aviso VISIBLE de cualquier error de JS sin capturar en esta página — la
// vez pasada un bug real en el editor quedó invisible hasta que se abrió la
// consola a mano (F12) y se copió el error; con esto se nota de inmediato
// con un toast, en vez de fallar en silencio. Un solo aviso por carga de
// página, así no inunda de toasts si el mismo error se repite en cada tecla.
(function () {
  let avisadoDeError = false;
  window.addEventListener('error', function (e) {
    console.error('[Editor] Error no capturado:', e.error || e.message, e);
    if (avisadoDeError || !window.jQuery || !jQuery.notify) return;
    avisadoDeError = true;
    jQuery.notify(
      'Ocurrió un error inesperado en el editor. Abre la consola (F12) y copia el error para reportarlo: ' + (e.message || 'error desconocido'),
      { className: 'error', position: 'top right', autoHide: false }
    );
  });
})();

(function () {
  // Blot de audio protegido — un bloque no editable con data-audio-id;
  // content/leccion.php lo hidrata al reproductor real (fetch + blob) según
  // el mismo criterio de acceso de siempre. Aquí solo se ve como tarjeta.
  const BlockEmbed = Quill.import('blots/block/embed');
  class AudioEmbedBlot extends BlockEmbed {
    static create(value) {
      const node = super.create();
      node.setAttribute('data-audio-id', value.id);
      node.setAttribute('contenteditable', 'false');
      node.textContent = '🔊 ' + value.nombre;
      return node;
    }
    static value(node) {
      return { id: node.getAttribute('data-audio-id'), nombre: node.textContent.replace('🔊 ', '') };
    }
  }
  AudioEmbedBlot.blotName = 'audio-embed';
  AudioEmbedBlot.tagName = 'div';
  AudioEmbedBlot.className = 'pf-audio-embed';
  Quill.register(AudioEmbedBlot);

  // Bloque colapsable — un <details>/<summary> NATIVO del navegador,
  // insertado como un embed OPACO de Quill (mismo patrón que el audio
  // protegido de arriba: Quill lo trata como una sola "unidad" en su
  // documento y nunca mira ni toca lo que hay adentro). El título y el
  // cuerpo son sus propias islas contenteditable="true" DENTRO de un
  // contenedor contenteditable="false" — typing/Enter/Backspace/listas ahí
  // dentro son edición nativa del navegador, no del modelo de Quill. Esto
  // reemplaza un intento anterior con formatos de línea propios que competía
  // con el manejo de teclado interno de Quill de forma impredecible (varias
  // rondas de bugs de "se duplica"/"se borra" que nunca se resolvieron del
  // todo) — con un embed opaco, Quill simplemente no participa.
  const BlockEmbedColapsable = Quill.import('blots/block/embed');
  class ColapsableEmbedBlot extends BlockEmbedColapsable {
    static create(value) {
      const node = super.create();
      node.setAttribute('contenteditable', 'false');
      node.setAttribute('open', '');
      const resumen = document.createElement('summary');
      resumen.setAttribute('contenteditable', 'true');
      resumen.innerHTML = (value && value.titulo) || 'Título del colapsable';
      const cuerpo = document.createElement('div');
      cuerpo.className = 'pf-colapsable-body-editable';
      cuerpo.setAttribute('contenteditable', 'true');
      cuerpo.innerHTML = (value && value.cuerpo) || '<p><br></p>';
      node.appendChild(resumen);
      node.appendChild(cuerpo);
      return node;
    }
    static value(node) {
      const resumen = node.querySelector('summary');
      const cuerpo = node.querySelector('.pf-colapsable-body-editable');
      return {
        titulo: resumen ? resumen.innerHTML : '',
        cuerpo: cuerpo ? cuerpo.innerHTML : '',
      };
    }
  }
  ColapsableEmbedBlot.blotName = 'colapsable-embed';
  ColapsableEmbedBlot.tagName = 'details';
  ColapsableEmbedBlot.className = 'pf-colapsable-embed';
  Quill.register(ColapsableEmbedBlot);

  // Cualquier tecla/entrada dentro del título o el cuerpo del colapsable
  // nunca debe llegarle a Quill — se intercepta en fase de CAPTURA sobre el
  // contenedor (un ancestro de quill.root) para garantizar que se detiene
  // ANTES de que el evento alcance a Quill. Un solo keydown/stopPropagation
  // no bastaba: Quill 2 reacciona a 'beforeinput' (así detecta y aplica lo
  // que el usuario escribió) y también observa la SELECCIÓN del documento
  // completo — al escribir dentro de una isla contenteditable anidada,
  // Quill no sabe traducir esa posición a su propio modelo, la confunde con
  // "todo el embed está seleccionado" y termina borrándolo con la primera
  // tecla (confirmado con un stack trace real: Editor.deleteText llamado
  // desde adentro de quill.js). 'copy'/'cut'/'paste' se agregaron después:
  // Quill los intercepta con su propio módulo de portapapeles
  // (this.quill.root.addEventListener('copy'|'cut'|'paste', ...)) para
  // armar el contenido desde SU modelo de Delta en vez del DOM real —
  // dentro del colapsable ese modelo no ve nada, así que Ctrl+C terminaba
  // copiando vacío aunque la selección nativa sí tuviera el texto correcto
  // (confirmado: getSelection().toString() traía el texto bien, pero el
  // portapapeles llegaba vacío). 'mousedown'/'click' también están en la
  // lista: Quill los escucha en quill.root para su propio manejo de
  // selección/formato del toolbar, y eso interfería con el doble-click
  // nativo del navegador para seleccionar una palabra (confirmado:
  // funcionaba en una página aislada sin Quill, pero no aquí). Frenar
  // TODOS estos tipos de evento aquí es lo que de verdad aísla al
  // colapsable de Quill; edición nativa runs.
  ['beforeinput', 'input', 'compositionstart', 'compositionupdate', 'compositionend', 'keyup', 'keypress', 'copy', 'cut', 'paste', 'mousedown', 'mouseup', 'click', 'dblclick'].forEach(function (tipo) {
    document.getElementById('editorContenidoTexto').addEventListener(tipo, function (e) {
      if (e.target.closest && e.target.closest('.pf-colapsable-embed')) {
        e.stopPropagation();
      }
    }, true);
  });
  // El keydown se maneja aparte porque además necesita casos especiales
  // dentro del <summary>: por ser un elemento nativamente "interactivo",
  // Espacio y Enter activan su comportamiento propio de abrir/cerrar el
  // <details> en vez de escribir texto — Espacio incluso se "come" el
  // carácter (preventDefault bloquea también la inserción nativa, van
  // pegados), así que se inserta a mano con execCommand. Enter no inserta
  // salto de línea en el título (es de una sola línea) — mueve el cursor al
  // cuerpo, para seguir la expectativa original de "Enter avanza".
  document.getElementById('editorContenidoTexto').addEventListener('keydown', function (e) {
    if (!(e.target.closest && e.target.closest('.pf-colapsable-embed'))) return;
    const resumen = e.target.closest('summary');
    const cuerpoDelKeydown = e.target.closest('.pf-colapsable-body-editable');
    const isla = resumen || cuerpoDelKeydown;
    if (isla && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
      // "Seleccionar todo" nativo (Ctrl/Cmd+A) no funciona en un
      // contenteditable=true anidado dentro de uno false anidado dentro de
      // otro true — confirmado hasta en una página aislada sin Quill de
      // por medio, es una limitación real del navegador con este triple
      // anidado, no un bug de este código. Se arma la selección a mano,
      // acotada nada más a la isla enfocada (título o cuerpo).
      e.preventDefault();
      const rango = document.createRange();
      rango.selectNodeContents(isla);
      const seleccion = window.getSelection();
      seleccion.removeAllRanges();
      seleccion.addRange(rango);
    } else if (resumen && e.key === ' ') {
      e.preventDefault();
      document.execCommand('insertText', false, ' ');
    } else if (resumen && e.key === 'Enter') {
      e.preventDefault();
      const cuerpo = resumen.parentElement.querySelector('.pf-colapsable-body-editable');
      if (cuerpo) {
        cuerpo.focus();
        const seleccion = window.getSelection();
        const rango = document.createRange();
        rango.selectNodeContents(cuerpo);
        rango.collapse(true);
        seleccion.removeAllRanges();
        seleccion.addRange(rango);
      }
    } else if (cuerpoDelKeydown && e.key === ' ') {
      // Convierte "- "/"* " o "1. " al inicio de una línea del cuerpo en
      // lista con viñetas/numerada — igual que Notion/Quill, pero armado a
      // mano en vez de con document.execCommand('insertUnorderedList'):
      // ese comando devuelve false (no hace nada) dentro de esta isla
      // contenteditable anidada, aparentemente porque el navegador no
      // logra resolver bien el "editing host" cuando hay contenteditable
      // true→false→true encajados (confirmado probando ambas rutas). Una
      // vez que existe un <ul>/<ol>/<li> real, Enter para seguir la lista o
      // salir de ella al dar Enter en un item vacío es comportamiento
      // nativo del navegador — no hace falta más JS para eso.
      const seleccion = window.getSelection();
      if (seleccion.rangeCount) {
        const rango = seleccion.getRangeAt(0);
        if (rango.collapsed) {
          const nodo = rango.startContainer;
          const textoAntes = nodo.nodeType === Node.TEXT_NODE ? nodo.textContent.slice(0, rango.startOffset) : '';
          const esVineta = textoAntes === '-' || textoAntes === '*';
          const numerada = textoAntes.match(/^(\d+)\.$/);
          if (esVineta || numerada) {
            e.preventDefault();
            let linea = nodo.nodeType === Node.TEXT_NODE ? nodo.parentElement : nodo;
            while (linea && linea.parentElement !== cuerpoDelKeydown) {
              linea = linea.parentElement;
            }
            if (linea) {
              rango.setStart(nodo, 0);
              rango.deleteContents();
              const tipoLista = numerada ? 'ol' : 'ul';
              let lista = linea.previousElementSibling;
              if (!lista || lista.tagName.toLowerCase() !== tipoLista) {
                lista = document.createElement(tipoLista);
                linea.parentNode.insertBefore(lista, linea);
              }
              const item = document.createElement('li');
              item.innerHTML = '<br>';
              lista.appendChild(item);
              linea.remove();
              const nuevoRango = document.createRange();
              nuevoRango.selectNodeContents(item);
              nuevoRango.collapse(true);
              seleccion.removeAllRanges();
              seleccion.addRange(nuevoRango);
            }
          }
        }
      }
    }
    e.stopPropagation();
  }, true);

  // Video/audio/archivo/colapsable anidado TAMBIÉN deben poder insertarse
  // con estos mismos botones de la barra estando el cursor DENTRO de un
  // colapsable — pero un clic en la barra (fuera de #editorContenidoTexto)
  // ya movió el foco antes de que el handler del botón corra, así que para
  // entonces ya no hay forma de saber en qué colapsable/qué punto exacto
  // estaba el cursor. Se guarda esa posición un instante antes (en cada
  // mouseup/keyup DENTRO de un colapsable, ambos ya interceptados arriba) y
  // se limpia en cuanto el cursor sale del colapsable — así, cuando el
  // handler del botón corre, sabe si debe insertar en Quill (de siempre) o
  // directo en el HTML del colapsable recordado.
  let ultimoContextoColapsable = null;
  document.getElementById('editorContenidoTexto').addEventListener('mouseup', actualizarContextoColapsable, true);
  document.getElementById('editorContenidoTexto').addEventListener('keyup', actualizarContextoColapsable, true);
  function actualizarContextoColapsable(e) {
    const isla = e.target.closest && (e.target.closest('summary') || e.target.closest('.pf-colapsable-body-editable'));
    if (!isla || !isla.closest('.pf-colapsable-embed')) {
      ultimoContextoColapsable = null;
      return;
    }
    const seleccion = window.getSelection();
    if (!seleccion.rangeCount) return;
    ultimoContextoColapsable = { isla: isla, rango: seleccion.getRangeAt(0).cloneRange() };
  }

  // Inserta un elemento de bloque (video/audio/colapsable anidado) dentro
  // del cuerpo de un colapsable, en el punto recordado por
  // ultimoContextoColapsable (o el contexto explícito que pase el llamador
  // — subirAudioProtegido/subirArchivoAdjunto necesitan el que había AL
  // HACER CLIC en el botón, no el actual, porque para cuando su fetch()
  // resuelve el usuario ya cerró el selector de archivo y el contexto
  // "en vivo" pudo cambiar o limpiarse mientras tanto) — si el punto
  // recordado era el TÍTULO (<summary>), se inserta al final de su propio
  // cuerpo en su lugar (un <summary> es de una sola línea, no tiene
  // sentido meterle un bloque). Devuelve true si insertó ahí, false si no
  // había contexto de colapsable (el llamador debe usar el camino normal
  // de Quill en ese caso).
  function insertarBloqueEnColapsable(elementoNuevo, contextoExplicito) {
    const contexto = contextoExplicito || ultimoContextoColapsable;
    if (!contexto || !contexto.isla.isConnected) return false;
    let cuerpo = contexto.isla.closest('.pf-colapsable-body-editable');
    if (!cuerpo && contexto.isla.tagName === 'SUMMARY') {
      cuerpo = contexto.isla.parentElement.querySelector('.pf-colapsable-body-editable');
    }
    if (!cuerpo) return false;
    let linea = contexto.rango && cuerpo.contains(contexto.rango.startContainer)
      ? (contexto.rango.startContainer.nodeType === Node.TEXT_NODE ? contexto.rango.startContainer.parentElement : contexto.rango.startContainer)
      : null;
    while (linea && linea.parentElement !== cuerpo) {
      linea = linea.parentElement;
    }
    if (linea) {
      linea.parentNode.insertBefore(elementoNuevo, linea.nextSibling);
    } else {
      cuerpo.appendChild(elementoNuevo);
    }
    return true;
  }

  // Enfoca un elemento editable (el <summary> o un <p> del cuerpo) y coloca
  // el cursor al final de su contenido — usado tanto al crear un colapsable
  // nuevo (de siempre) como al insertar uno anidado dentro de otro.
  function focusIslaColapsable(el) {
    // Sin collapse(): se deja el placeholder ("Título del colapsable")
    // SELECCIONADO, no solo con el cursor detrás — así la primera tecla que
    // el usuario escriba lo reemplaza en vez de agregarse después.
    el.focus();
    const seleccion = window.getSelection();
    const rango = document.createRange();
    rango.selectNodeContents(el);
    seleccion.removeAllRanges();
    seleccion.addRange(rango);
  }

  const icons = Quill.import('ui/icons');
  icons['audio-embed'] = '🔊';
  icons['archivo-adjunto'] = '📎';
  icons['colapsable-embed'] = '▾';
  icons['undo'] = '↶';
  icons['redo'] = '↷';

  function normalizarUrlYoutube(url) {
    url = url.trim();
    if (url === '' || url.indexOf('youtube.com/embed/') !== -1) return url;
    let m = url.match(/youtu\.be\/([a-zA-Z0-9_-]{6,})/i);
    if (!m) m = url.match(/youtube\.com\/(?:watch\?v=|shorts\/|live\/)([a-zA-Z0-9_-]{6,})/i);
    return m ? 'https://www.youtube.com/embed/' + m[1] : url;
  }

  // Tamaño de fuente y alineación con estilo inline (no clases) — así el HTML
  // guardado se ve igual en cualquier página que lo renderice
  // (.pf-contenido-html) sin depender del CSS propio del editor.
  const TamanoEstilo = Quill.import('attributors/style/size');
  TamanoEstilo.whitelist = ['12px', '14px', '16px', '18px', '20px', '24px', '32px', '48px'];
  Quill.register(TamanoEstilo, true);
  Quill.register(Quill.import('attributors/style/align'), true);
  const Delta = Quill.import('delta');

  const quillContenidoTexto = new Quill('#editorContenidoTexto', {
    theme: 'snow',
    placeholder: 'Escribe el contenido de la lección — inserta imágenes, video, audio o archivos donde los necesites.',
    modules: {
      toolbar: {
        container: [
          [{ header: [2, 3, false] }, { size: TamanoEstilo.whitelist }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          [{ align: [] }],
          ['blockquote', 'link', 'image', 'video'],
          ['audio-embed', 'archivo-adjunto', 'colapsable-embed'],
          ['undo', 'redo'],
          ['clean'],
        ],
        handlers: {
          image: subirImagen,
          video: insertarVideoYoutube,
          'audio-embed': subirAudioProtegido,
          'archivo-adjunto': subirArchivoAdjunto,
          'colapsable-embed': insertarColapsable,
          undo: function () { quillContenidoTexto.history.undo(); },
          redo: function () { quillContenidoTexto.history.redo(); },
        },
      },
    },
  });
  quillContenidoTexto.root.innerHTML = <?= json_encode((string) $leccion['contenido_texto']) ?>;

  // Pegar una imagen (Ctrl+V desde el portapapeles, o copiada de un Word/Google
  // Docs/captura de pantalla) NO pasa por subirImagen() de abajo — Quill la
  // pega tal cual como <img src="data:image/...;base64,...">, que puede pesar
  // varios MB en un solo texto. Eso infla el POST de guardar/autoguardar
  // hasta que algún límite de tamaño del lado del servidor lo rechaza con 403
  // — visto en producción, es la causa real de "no autoguarda al pegar
  // información". Este matcher intercepta cualquier <img> pegada con src
  // data:, la quita del pegado (no inserta el base64) y la sube en segundo
  // plano por el mismo endpoint que ya usa el botón de imagen del toolbar,
  // insertando el resultado como una imagen normal (URL, no base64) en
  // cuanto termina.
  quillContenidoTexto.clipboard.addMatcher('IMG', function (node, delta) {
    const src = node.getAttribute('src') || '';
    if (!src.startsWith('data:')) {
      return delta;
    }
    subirImagenPegada(src);
    return new Delta();
  });

  async function subirImagenPegada(dataUrl) {
    try {
      const blob = await (await fetch(dataUrl)).blob();
      const extension = (blob.type.split('/')[1] || 'png').split('+')[0];
      const datos = new FormData();
      datos.append('imagen', blob, 'pegado.' + extension);
      datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
      const res = await fetch('../../backend/quill_imagen_subir.php', { method: 'POST', body: datos });
      const data = await res.json();
      if (data.success) {
        const rango = quillContenidoTexto.getSelection(true) || { index: quillContenidoTexto.getLength() };
        quillContenidoTexto.insertEmbed(rango.index, 'image', data.url, 'user');
      } else {
        $.notify(data.message || 'No se pudo subir una imagen pegada.', { className: 'error', position: 'top right' });
      }
    } catch (e) {
      $.notify('Error de conexión subiendo una imagen pegada.', { className: 'error', position: 'top right' });
    }
  }

  // Inserta un colapsable nuevo en el cursor y enfoca su título para
  // empezar a escribir de inmediato. Ya no hace falta "alternar" nada por
  // teclado — un embed opaco no tiene un estado de "dentro/fuera" que
  // vigilar, así que este botón solo INSERTA (para editar el título o el
  // cuerpo de uno ya existente, simplemente se hace clic ahí y se escribe,
  // como en cualquier campo de texto normal).
  function insertarColapsable() {
    // ColapsableEmbedBlot.create() es el mismo método que usa Quill para
    // construir el <details>/<summary>/cuerpo — se reutiliza tal cual para
    // armar un colapsable ANIDADO (dentro de otro), ya que no es más que
    // una función que arma un nodo DOM, nada que dependa de estar
    // registrado en Quill para poder llamarse.
    const nodoAnidado = ultimoContextoColapsable ? ColapsableEmbedBlot.create({ titulo: '', cuerpo: '' }) : null;
    if (nodoAnidado && insertarBloqueEnColapsable(nodoAnidado)) {
      setTimeout(function () { focusIslaColapsable(nodoAnidado.querySelector('summary')); }, 0);
      return;
    }
    const rango = quillContenidoTexto.getSelection(true);
    if (!rango) return;
    quillContenidoTexto.insertEmbed(rango.index, 'colapsable-embed', { titulo: '', cuerpo: '' }, 'user');
    quillContenidoTexto.setSelection(rango.index + 1, 0, 'user');
    setTimeout(function () {
      const resumenes = quillContenidoTexto.root.querySelectorAll('.pf-colapsable-embed summary');
      const ultimo = resumenes[resumenes.length - 1];
      if (ultimo) focusIslaColapsable(ultimo);
    }, 0);
  }

  function subirImagen() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp,image/gif';
    input.addEventListener('change', async function () {
      const archivo = input.files[0];
      if (!archivo) return;
      const rango = quillContenidoTexto.getSelection(true);
      const datos = new FormData();
      datos.append('imagen', archivo);
      datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
      try {
        const res = await fetch('../../backend/quill_imagen_subir.php', { method: 'POST', body: datos });
        const data = await res.json();
        if (data.success) {
          quillContenidoTexto.insertEmbed(rango.index, 'image', data.url);
          quillContenidoTexto.setSelection(rango.index + 1);
        } else {
          alert(data.message || 'No se pudo subir la imagen.');
        }
      } catch (e) {
        alert('Error de conexión subiendo la imagen.');
      }
    });
    input.click();
  }

  function insertarVideoYoutube() {
    const url = prompt('Pega el link del video de YouTube:');
    if (!url) return;
    const embedUrl = normalizarUrlYoutube(url);
    if (ultimoContextoColapsable) {
      const iframe = document.createElement('iframe');
      iframe.className = 'ql-video';
      iframe.setAttribute('frameborder', '0');
      iframe.setAttribute('allowfullscreen', 'true');
      iframe.src = embedUrl;
      if (insertarBloqueEnColapsable(iframe)) return;
    }
    const rango = quillContenidoTexto.getSelection(true);
    quillContenidoTexto.insertEmbed(rango.index, 'video', embedUrl);
    quillContenidoTexto.setSelection(rango.index + 1);
  }

  function subirAudioProtegido() {
    // Se captura ANTES de abrir el selector de archivo (no adentro del
    // 'change', que corre después de que el usuario ya cerró ese diálogo)
    // — por simetría con quillContenidoTexto.getSelection(true) de abajo,
    // que también asume que la selección de cuando se dio clic sigue vigente.
    const contextoAlClick = ultimoContextoColapsable;
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'audio/*';
    input.addEventListener('change', async function () {
      const archivo = input.files[0];
      if (!archivo) return;
      const rango = quillContenidoTexto.getSelection(true);
      const datos = new FormData();
      datos.append('audio', archivo);
      datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
      try {
        const res = await fetch('../../backend/leccion_audio_embed_subir.php', { method: 'POST', body: datos });
        const data = await res.json();
        if (data.success) {
          if (contextoAlClick) {
            const nodo = document.createElement('div');
            nodo.className = 'pf-audio-embed';
            nodo.setAttribute('data-audio-id', data.id);
            nodo.setAttribute('contenteditable', 'false');
            nodo.textContent = '🔊 ' + data.nombre;
            if (insertarBloqueEnColapsable(nodo, contextoAlClick)) return;
          }
          quillContenidoTexto.insertEmbed(rango.index, 'audio-embed', { id: data.id, nombre: data.nombre });
          quillContenidoTexto.setSelection(rango.index + 1);
        } else {
          alert(data.message || 'No se pudo subir el audio.');
        }
      } catch (e) {
        alert('Error de conexión subiendo el audio.');
      }
    });
    input.click();
  }

  function subirArchivoAdjunto() {
    const contextoAlClick = ultimoContextoColapsable;
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.zip,.epub';
    input.addEventListener('change', async function () {
      const archivo = input.files[0];
      if (!archivo) return;
      const rango = quillContenidoTexto.getSelection(true);
      const datos = new FormData();
      datos.append('archivo', archivo);
      datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
      try {
        const res = await fetch('../../backend/leccion_archivo_subir.php', { method: 'POST', body: datos });
        const data = await res.json();
        if (data.success) {
          if (contextoAlClick) {
            const parrafo = document.createElement('p');
            const enlace = document.createElement('a');
            enlace.href = data.url;
            enlace.textContent = '📎 ' + data.nombre;
            parrafo.appendChild(enlace);
            if (insertarBloqueEnColapsable(parrafo, contextoAlClick)) return;
          }
          quillContenidoTexto.insertText(rango.index, '📎 ' + data.nombre, { link: data.url });
          quillContenidoTexto.insertText(rango.index + ('📎 ' + data.nombre).length, '\n');
          quillContenidoTexto.setSelection(rango.index + ('📎 ' + data.nombre).length + 1);
        } else {
          alert(data.message || 'No se pudo subir el archivo.');
        }
      } catch (e) {
        alert('Error de conexión subiendo el archivo.');
      }
    });
    input.click();
  }

  function sincronizarContenido() {
    document.getElementById('contenidoTextoOculta').value = quillContenidoTexto.root.innerHTML;
  }

  function alternarSegunTipo() {
    document.getElementById('wrapperEditorContenido').classList.toggle('d-none', document.getElementById('tipoContenido').value === 'quiz');
  }
  document.getElementById('tipoContenido').addEventListener('change', alternarSegunTipo);
  alternarSegunTipo();

  const form = document.getElementById('formLeccion');
  // Fijado directo en el <form> (no delegado en document) para garantizar
  // que corra ANTES de que el handler de _footer.php arme el FormData.
  form.addEventListener('submit', sincronizarContenido);

  document.getElementById('btnGuardarPublicar').addEventListener('click', function () {
    document.getElementById('accionPublicacion').value = 'publicar';
  });

  function actualizarTrasGuardar(data) {
    document.getElementById('leccionIdOculto').value = data.leccion_id;
    const badge = document.getElementById('badgeEstadoPublicacion');
    badge.textContent = data.estado_publicacion === 'publicado' ? 'Publicado' : 'Borrador';
    badge.className = 'badge ' + (data.estado_publicacion === 'publicado' ? 'bg-success' : 'bg-secondary');
    document.getElementById('wrapperVistaPrevia').classList.remove('d-none');
  }

  // Un 403 aquí puede ser una sesión de verdad cerrada (res.redirected, ya
  // que require_login() hace un 302 a ingreso.php) o puede no serlo — un
  // csrf_token desincronizado, o en producción un bloqueo del servidor por
  // tamaño/contenido del POST (ver subirImagenPegada() arriba, pensado
  // justo para evitar esto con imágenes pegadas). Antes cualquier 403 se
  // trataba como sesión cerrada a ciegas; ahora se confirma con
  // session_check.php antes de avisar, para no alarmar en falso ni
  // interrumpir la edición cuando la sesión sigue activa.
  async function pfSesionSigueActiva() {
    try {
      const res = await fetch('../../backend/session_check.php', { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      return !!data.logged_in;
    } catch (e) {
      return false;
    }
  }

  // "Guardar borrador" ya no es un submit del formulario — es su propio POST
  // manual (mismo patrón que "Vista previa" abajo) para poder quedarse en el
  // editor sin redirigir a la lista, y así seguir editando sin interrupción.
  document.getElementById('btnGuardarBorrador').addEventListener('click', async function () {
    const boton = this;
    boton.disabled = true;
    try {
      sincronizarContenido();
      document.getElementById('accionPublicacion').value = 'borrador';
      const res = await fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });
      if (res.redirected) {
        if (window.pfAvisarSesionCerrada) {
          window.pfAvisarSesionCerrada();
        } else {
          $.notify('Tu sesión se cerró automáticamente. Copia tu trabajo actual antes de continuar e inicia sesión de nuevo.', { className: 'warn', position: 'top right', autoHide: false });
        }
        return;
      }
      if (res.status === 403) {
        if (await pfSesionSigueActiva()) {
          $.notify('No se pudo guardar el borrador (el servidor rechazó la petición). Tu sesión sigue activa — intenta de nuevo en un momento.', { className: 'error', position: 'top right', autoHide: false });
        } else if (window.pfAvisarSesionCerrada) {
          window.pfAvisarSesionCerrada();
        } else {
          $.notify('Tu sesión se cerró automáticamente. Copia tu trabajo actual antes de continuar e inicia sesión de nuevo.', { className: 'warn', position: 'top right', autoHide: false });
        }
        return;
      }
      const data = await res.json();
      if (data.success) {
        actualizarTrasGuardar(data);
        $.notify('Borrador guardado.', { className: 'success', position: 'top right', autoHideDelay: 2000 });
      } else {
        $.notify(data.mensaje || 'No se pudo guardar.', { className: 'error', position: 'top right' });
      }
    } catch (e) {
      $.notify('Error de conexión.', { className: 'error', position: 'top right' });
    } finally {
      boton.disabled = false;
    }
  });

  const btnVistaPrevia = document.getElementById('btnVistaPrevia');
  btnVistaPrevia.addEventListener('click', async function () {
    sincronizarContenido();
    document.getElementById('accionPublicacion').value = 'preview';
    this.disabled = true;
    try {
      const res = await fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });
      const data = await res.json();
      if (data.success) {
        window.open('../../index.php?action=leccion&id=' + data.leccion_id + '&preview=1', '_blank');
      } else {
        alert(data.mensaje || 'No se pudo guardar para la vista previa.');
      }
    } catch (e) {
      alert('Error de conexión.');
    }
    this.disabled = false;
  });

  // Se arranca con un pequeño retraso para que la asignación inicial de
  // quillContenidoTexto.root.innerHTML (arriba) termine de disparar su propio
  // "text-change" antes de empezar a vigilar cambios reales del admin — si no,
  // el autoguardado se dispararía de inmediato al abrir un editor ya existente.
  setTimeout(function () {
    PfAutosave.iniciar({
      formSelector: '#formLeccion',
      quills: [quillContenidoTexto],
      campoBandera: 'accion_publicacion',
      valorBandera: 'autosave',
      antesDeGuardar: sincronizarContenido,
      onGuardadoOk: actualizarTrasGuardar,
      verificarSesionUrl: '../../backend/session_check.php',
    });
  }, 300);
})();
</script>
<script src="_autosave.js"></script>
<?php include __DIR__ . '/_footer.php'; ?>
