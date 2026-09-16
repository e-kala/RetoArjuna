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

// Log de diagnóstico temporal — "vista previa"/"guardar borrador"/
// autoguardado dejaron de funcionar en producción sin poder reproducirse en
// local. Se registra ANTES de requerir_csrf_form() a propósito: si la
// petición nunca llega a escribirse en logs/errores.log después de
// reproducir el problema en producción, es señal de que ni siquiera está
// llegando hasta PHP (bloqueo del servidor/WAF) — si SÍ aparece esta línea
// pero nada después, el problema está en el CSRF o en el guardado mismo.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ra_registrar_error(sprintf(
        'leccion_form.php POST recibido — accion=%s accion_publicacion=%s id=%s evento_id=%s curso_id=%s content-length=%s csrf_token_presente=%s',
        $_POST['accion'] ?? '(ninguna)',
        $_POST['accion_publicacion'] ?? '(ninguna)',
        $_POST['id'] ?? '?',
        $_POST['evento_id'] ?? ($_GET['evento_id'] ?? '?'),
        $_POST['curso_id'] ?? ($_GET['curso_id'] ?? '?'),
        $_SERVER['CONTENT_LENGTH'] ?? '?',
        isset($_POST['csrf_token']) ? 'si' : 'NO'
    ));
}

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

            ra_registrar_error(sprintf(
                'leccion_form.php guardado OK — leccion_id=%d accion_publicacion=%s estado_publicacion=%s',
                $leccionId,
                $accionPublicacion,
                $estadoPublicacion
            ));

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
        // Antes se asumía a ciegas "orden repetido" — se registra el error
        // real de MySQL para no seguir adivinando la causa.
        ra_registrar_error('leccion_form.php: $stmt->execute() falló — ' . $stmt->error, __FILE__, __LINE__);
        $error = '¿El orden ya está usado en ' . ($esEvento ? 'este evento' : 'este curso') . '?';
        $stmt->close();
    }
    if ($esAjax && $error !== '') {
        ra_registrar_error('leccion_form.php respondiendo error al cliente: ' . $error);
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
<script src="../../assets/pf_editor.js?v=3"></script>
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
  const editor = PfEditor.crear({
    contenedor: '#editorContenidoTexto',
    contexto: 'admin',
    placeholder: 'Escribe el contenido de la lección — inserta imágenes, video, audio o archivos donde los necesites.',
    csrfToken: <?= json_encode(csrf_token()) ?>,
    endpointAudio: '../../backend/leccion_audio_embed_subir.php',
    endpointAdjunto: '../../backend/leccion_archivo_subir.php',
    contenidoInicialHtml: <?= json_encode((string) $leccion['contenido_texto']) ?>,
    capacidades: { video: true, audio: true, adjuntos: true, colapsables: true, tamanoAlineacion: true, undoRedo: true },
  });
  const quillContenidoTexto = editor.quill;

  function sincronizarContenido() {
    document.getElementById('contenidoTextoOculta').value = editor.sincronizar();
  }

  function alternarSegunTipo() {
    document.getElementById('wrapperEditorContenido').classList.toggle('d-none', document.getElementById('tipoContenido').value === 'quiz');
  }
  document.getElementById('tipoContenido').addEventListener('change', alternarSegunTipo);
  alternarSegunTipo();

  const form = document.getElementById('formLeccion');
  // Fijado directo en el <form> (no delegado en document) para garantizar
  // que corra ANTES de que el handler de _footer.php arme el FormData.
  // stopImmediatePropagation() corta también a sincronizarContenido (el
  // siguiente listener de este mismo form) y al submit delegado de
  // _footer.php (que escucha en document, en la fase de burbujeo) — no
  // tiene caso guardar si todavía falta una imagen por subir.
  form.addEventListener('submit', function (e) {
    if (editor.bloquearSiHayCargasPendientes()) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  });
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
  // tamaño/contenido del POST (ver el paste-imagen del editor, pensado
  // justo para evitar esto). Antes cualquier 403 se trataba como sesión
  // cerrada a ciegas; ahora se confirma con session_check.php antes de
  // avisar, para no alarmar en falso ni interrumpir la edición cuando la
  // sesión sigue activa.
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
    if (editor.bloquearSiHayCargasPendientes()) return;
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
    if (editor.bloquearSiHayCargasPendientes()) return;
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
  // contenidoInicialHtml (arriba) termine de disparar su propio
  // "text-change" antes de empezar a vigilar cambios reales del admin — si
  // no, el autoguardado se dispararía de inmediato al abrir un editor ya
  // existente.
  setTimeout(function () {
    PfEditor.conectarAutosaveServidor(editor, {
      formSelector: '#formLeccion',
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
