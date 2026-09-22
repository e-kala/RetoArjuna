<?php
// Una lección pertenece a un curso O a un evento (nunca ambos) — ver
// schema_lecciones_compartidas.sql. Esto permite que "Convertir a evento"
// reasigne las lecciones existentes sin perder el contenido.
//
// Desde el editor unificado (panel/admin/leccion_form.php), todo el
// contenido que no es quiz vive en un solo campo HTML (contenido_texto),
// con audios protegidos referenciados por data-audio-id — se hidratan aquí
// al reproductor real, nunca se reconstruyen aparte en otro lugar.
function pf_hidratar_audios_embebidos(string $html, bool $esPreview): string
{
    return preg_replace_callback(
        '/<div class="pf-audio-embed" data-audio-id="(\d+)"[^>]*>.*?<\/div>/s',
        function (array $m) use ($esPreview): string {
            $audioId = (int) $m[1];
            ob_start();
            ?>
            <div class="mb-3">
              <button type="button" class="btn btn-sm btn-outline-dark btn-cargar-audio" data-audio-id="<?= $audioId ?>" <?= $esPreview ? 'data-preview="1"' : '' ?>>
                <i class="bi bi-play-fill"></i> Reproducir audio
              </button>
              <audio class="d-none" controls style="width:100%;" controlsList="nodownload noremoteplayback" oncontextmenu="return false;"></audio>
            </div>
            <?php
            return (string) ob_get_clean();
        },
        $html
    );
}

$leccionId = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT l.*,
            c.titulo AS curso_titulo, c.slug AS curso_slug,
            e.titulo AS evento_titulo, e.slug AS evento_slug, e.solo_miembros,
            e.gratuito AS evento_gratuito, e.incluido_membresia AS evento_incluido_membresia
     FROM lecciones l
     LEFT JOIN cursos c ON c.id = l.curso_id
     LEFT JOIN eventos e ON e.id = l.evento_id
     WHERE l.id = ?'
);
$stmt->bind_param('i', $leccionId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leccion) {
    echo '<div class="container" style="margin-top:143px;"><p>Lección no encontrada.</p></div>';
    return;
}

$usuario = current_user();
$esAdmin = $usuario && $usuario['rol'] === 'admin';
// Un admin puede ver el resultado real de un borrador sin publicarlo ni
// necesitar estar inscrito — ver el botón "Vista previa" del editor.
$esPreview = $esAdmin && ($_GET['preview'] ?? '') === '1';

// Un borrador nunca es visible fuera de la vista previa de admin, sin
// importar inscripción/vista previa de demo — todavía se está editando.
if ($leccion['estado_publicacion'] === 'borrador' && !$esPreview) {
    echo '<div class="container" style="margin-top:143px;"><p>Esta lección todavía no está disponible.</p></div>';
    return;
}

$esDeCurso = $leccion['curso_id'] !== null;
$padreId = (int) ($esDeCurso ? $leccion['curso_id'] : $leccion['evento_id']);
$padreSlug = $esDeCurso ? $leccion['curso_slug'] : $leccion['evento_slug'];
$padreTitulo = $esDeCurso ? $leccion['curso_titulo'] : $leccion['evento_titulo'];
$volverAccion = $esDeCurso ? 'curso' : 'evento';

if ($esDeCurso) {
    $tieneAcceso = $usuario ? usuario_tiene_acceso_curso($usuario['id'], $padreId) : false;
} else {
    // El acceso real a un evento es "está inscrito o pagó" (ver
    // usuario_esta_inscrito_evento, que ya reconoce la inscripción gratis
    // por membresía) — ya no basta con "es miembro" sin más, porque eso daba
    // acceso a CUALQUIER evento en vez de solo a los que el miembro se
    // inscribió (ver evento_detalle.php, que ahora exige el mismo paso
    // explícito de inscripción antes de desbloquear el contenido).
    $tieneAcceso = $usuario && usuario_esta_inscrito_evento($usuario['id'], $padreId);
}
$esDemo = (int) $leccion['vista_previa'] === 1;
$tieneAcceso = $tieneAcceso || $esPreview;

if (!$tieneAcceso && !$esDemo) {
    if (!$esDeCurso) {
        $esMiembro = $usuario && usuario_tiene_membresia_activa($usuario['id']);
        $soloMiembros = (int) ($leccion['solo_miembros'] ?? 0) === 1;
        $incluidoMembresia = (int) ($leccion['evento_incluido_membresia'] ?? 0) === 1;
        $soloMiembrosBloqueado = $soloMiembros && !$esMiembro;
        $puedeAccederGratis = (int) ($leccion['evento_gratuito'] ?? 0) === 1
            || (($soloMiembros || $incluidoMembresia) && $esMiembro);

        // Si ya está logueado, el evento no tiene ningún camino gratuito
        // para él (ni exclusivo-bloqueado, que tampoco se resuelve
        // comprando), lo mandamos directo a pagar en vez de rebotarlo a la
        // página del evento — así "Siguiente" desde la demo lleva directo a
        // la pantalla de compra si eso es lo único que falta.
        if ($usuario && !$soloMiembrosBloqueado && !$puedeAccederGratis) {
            header('Location: ' . BASE_URL . '/backend/pagos/checkout.php?evento_id=' . $padreId);
            exit;
        }
    }
    header('Location: ' . BASE_URL . '/index.php?action=' . $volverAccion . '&slug=' . urlencode($padreSlug));
    exit;
}

$stmt = $esDeCurso
    ? $conn->prepare('SELECT id, titulo FROM lecciones WHERE curso_id = ? AND estado_publicacion = "publicado" ORDER BY orden')
    : $conn->prepare('SELECT id, titulo FROM lecciones WHERE evento_id = ? AND estado_publicacion = "publicado" ORDER BY orden');
$stmt->bind_param('i', $padreId);
$stmt->execute();
$hermanas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$indiceActual = null;
foreach ($hermanas as $i => $h) {
    if ((int) $h['id'] === $leccionId) {
        $indiceActual = $i;
        break;
    }
}
$anterior = $indiceActual !== null && $indiceActual > 0 ? $hermanas[$indiceActual - 1] : null;
$siguiente = $indiceActual !== null && $indiceActual < count($hermanas) - 1 ? $hermanas[$indiceActual + 1] : null;

$completada = false;
if ($usuario) {
    $stmt = $conn->prepare('SELECT completado FROM progreso WHERE usuario_id = ? AND leccion_id = ?');
    $stmt->bind_param('ii', $usuario['id'], $leccionId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $completada = $fila && (int) $fila['completado'] === 1;
}

$preguntasQuiz = [];
if ($leccion['tipo_contenido'] === 'quiz' && $tieneAcceso) {
    $stmt = $conn->prepare('SELECT * FROM quizzes WHERE leccion_id = ?');
    $stmt->bind_param('i', $leccionId);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($quiz) {
        $stmt = $conn->prepare('SELECT * FROM quiz_preguntas WHERE quiz_id = ? ORDER BY orden');
        $stmt->bind_param('i', $quiz['id']);
        $stmt->execute();
        $preguntas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($preguntas as &$p) {
            $stmt = $conn->prepare('SELECT * FROM quiz_opciones WHERE pregunta_id = ? ORDER BY orden');
            $stmt->bind_param('i', $p['id']);
            $stmt->execute();
            $p['opciones'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        unset($p);
        $preguntasQuiz = $preguntas;
    }
}

// Pestaña "Preguntas y respuestas" acotada a ESTA lección — ver
// curso_detalle.php/foro_temas_de() para el criterio completo. Solo tiene
// sentido con acceso real (o vista previa/demo, mismo gate que el resto de
// la página) — sin acceso no se llega ni siquiera hasta aquí (ver el
// redirect de más arriba).
require_once __DIR__ . '/../foro/backend/foro_helpers.php';
$cursoIdLeccion = $esDeCurso ? $padreId : null;
$eventoIdLeccion = $esDeCurso ? null : $padreId;
$temasLeccion = foro_temas_de($cursoIdLeccion, $eventoIdLeccion, $leccionId, $usuario);
$columnaLeccion = $esDeCurso ? 'curso_id' : 'evento_id';
$stmtTemaExistente = $conn->prepare("SELECT id FROM foro_temas WHERE {$columnaLeccion} = ? AND leccion_id = ? LIMIT 1");
$stmtTemaExistente->bind_param('ii', $padreId, $leccionId);
$stmtTemaExistente->execute();
$temaExistenteId = (int) ($stmtTemaExistente->get_result()->fetch_assoc()['id'] ?? 0) ?: null;
$stmtTemaExistente->close();
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=<?= $volverAccion ?>&slug=<?= urlencode($padreSlug) ?>" class="d-inline-block mb-3">&larr; <?= htmlspecialchars($padreTitulo) ?></a>
  <?php if ($leccion['estado_publicacion'] === 'borrador'): ?>
    <div class="alert alert-warning">Estás viendo un borrador (vista previa de admin) — los alumnos todavía no pueden ver esta lección.</div>
  <?php endif; ?>
  <div class="d-flex align-items-center flex-wrap gap-2">
    <h1 class="h3 mb-0"><?= htmlspecialchars($leccion['titulo']) ?></h1>
    <?php if ($esAdmin): ?>
      <?php $parametroPadre = $leccion['evento_id'] ? 'evento_id=' . (int) $leccion['evento_id'] : 'curso_id=' . (int) $leccion['curso_id']; ?>
      <a href="panel/admin/leccion_form.php?<?= $parametroPadre ?>&id=<?= $leccionId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
    <?php if ($hermanas): ?>
      <button type="button" class="btn btn-outline-secondary btn-sm ms-auto pf-detalle-toggle-sidebar" id="btnToggleTemario" aria-expanded="true" aria-controls="temarioSidebar">
        <i class="bi bi-list-ul"></i> Contenido
      </button>
    <?php endif; ?>
  </div>

  <div class="pf-detalle-grid">
    <div class="pf-detalle-main">
      <ul class="nav nav-tabs pf-detalle-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-descripcion" type="button">Contenido</button></li>
        <?php if ($tieneAcceso || $esDemo): ?>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-preguntas" type="button">Preguntas y respuestas <?php if ($temasLeccion): ?><span class="badge bg-secondary"><?= count($temasLeccion) ?></span><?php endif; ?></button></li>
        <?php endif; ?>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-descripcion">
          <div class="my-2">
            <?php if ($leccion['tipo_contenido'] === 'quiz'): ?>
              <form id="quizForm">
                <?php foreach ($preguntasQuiz as $p): ?>
                  <div class="mb-3">
                    <p class="fw-bold"><?= htmlspecialchars($p['enunciado']) ?></p>
                    <?php foreach ($p['opciones'] as $o): ?>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="pregunta_<?= (int) $p['id'] ?>" value="<?= (int) $o['id'] ?>" required>
                        <label class="form-check-label"><?= htmlspecialchars($o['texto']) ?></label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
                <button type="submit" class="btn" style="background:#f7931e;color:#fff;">Enviar respuestas</button>
                <div id="quizResultado" class="mt-3"></div>
              </form>
            <?php else: ?>
              <div class="pf-contenido-html"><?= pf_hidratar_audios_embebidos((string) $leccion['contenido_texto'], $esPreview) ?></div>
              <?php if (trim((string) $leccion['contenido_texto']) === ''): ?>
                <p class="text-muted">Esta lección todavía no tiene contenido.</p>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <?php if ($tieneAcceso || $esDemo): ?>
            <?php
            $foroLeccionUrl = $leccion['foro_url']
                ? navbar_href($leccion['foro_url'], '../')
                : ($esDeCurso
                    ? 'foro/curso.php?curso_id=' . $padreId . '&leccion_id=' . $leccionId
                    : 'foro/evento.php?evento_id=' . $padreId . '&leccion_id=' . $leccionId);
            ?>
            <div class="mb-4">
              <a href="<?= htmlspecialchars($foroLeccionUrl) ?>" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-chat-square-text"></i> Discutir esta lección en el foro
              </a>
            </div>
          <?php endif; ?>

          <?php if ($usuario && $tieneAcceso && $leccion['tipo_contenido'] !== 'quiz'): ?>
            <button id="btnCompletar" class="btn btn-outline-success" <?= $completada ? 'disabled' : '' ?>>
              <?= $completada ? 'Lección completada' : 'Marcar como completada' ?>
            </button>
          <?php endif; ?>

          <div class="d-flex justify-content-between mt-4">
            <?php if ($anterior): ?><a class="btn btn-link" href="?action=leccion&id=<?= (int) $anterior['id'] ?>">&larr; Anterior</a><?php else: ?><span></span><?php endif; ?>
            <?php if ($siguiente): ?><a class="btn btn-link" href="?action=leccion&id=<?= (int) $siguiente['id'] ?>">Siguiente &rarr;</a><?php endif; ?>
          </div>
        </div>

        <?php if ($tieneAcceso || $esDemo): ?>
          <div class="tab-pane fade" id="tab-preguntas">
            <?php
            $temasPregyresp = $temasLeccion;
            $temaExistenteIdPregyresp = $temaExistenteId;
            $verForoUrlPregyresp = $foroLeccionUrl;
            require __DIR__ . '/_preguntas_respuestas.php';
            ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($hermanas): ?>
      <aside class="pf-detalle-sidebar" id="temarioSidebar">
        <h2 class="h6 fw-bold mb-3"><?= htmlspecialchars($padreTitulo) ?></h2>
        <div class="list-group">
          <?php foreach ($hermanas as $h): ?>
            <a href="?action=leccion&id=<?= (int) $h['id'] ?>" class="list-group-item list-group-item-action<?= (int) $h['id'] === $leccionId ? ' active' : '' ?>">
              <?= htmlspecialchars($h['titulo']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>
    <?php endif; ?>
  </div>
</div>

<script>
  const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

  // El audio no lleva una URL directa en el HTML (ver .btn-cargar-audio) —
  // se pide por fetch con la cookie de sesión y se reproduce como blob: en
  // memoria, para que no quede un link real que se pueda copiar del
  // inspector y pegar en otra pestaña o descargar directo. Delegado en
  // document porque puede haber cualquier cantidad de audios embebidos.
  document.addEventListener('click', async function (event) {
    const boton = event.target.closest('.btn-cargar-audio');
    if (!boton) return;
    const audioEl = boton.nextElementSibling;
    const textoOriginal = boton.innerHTML;
    boton.disabled = true;
    boton.innerHTML = 'Cargando…';
    try {
      const urlAudio = 'backend/audio_stream.php?id=' + boton.dataset.audioId + (boton.dataset.preview ? '&preview=1' : '');
      const res = await fetch(urlAudio, { credentials: 'same-origin' });
      if (!res.ok) {
        throw new Error('No autorizado');
      }
      const blob = await res.blob();
      audioEl.src = URL.createObjectURL(blob);
      audioEl.classList.remove('d-none');
      boton.classList.add('d-none');
      audioEl.play();
    } catch (e) {
      boton.disabled = false;
      boton.innerHTML = textoOriginal;
      alert('No se pudo cargar el audio, intenta de nuevo.');
    }
  });

  <?php if ($usuario && $tieneAcceso && $leccion['tipo_contenido'] !== 'quiz'): ?>
  document.getElementById('btnCompletar')?.addEventListener('click', async function () {
    const res = await fetch('backend/progreso_back.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ leccion_id: <?= $leccionId ?>, csrf_token: CSRF_TOKEN }),
    });
    const data = await res.json();
    if (data.success) {
      this.textContent = 'Lección completada';
      this.disabled = true;
    }
  });
  <?php endif; ?>

  <?php if ($leccion['tipo_contenido'] === 'quiz'): ?>
  document.getElementById('quizForm')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const respuestas = {};
    new FormData(this).forEach((valor, clave) => { respuestas[clave] = valor; });

    const res = await fetch('backend/quiz_submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        leccion_id: <?= $leccionId ?>,
        respuestas: JSON.stringify(respuestas),
        csrf_token: CSRF_TOKEN,
      }),
    });
    const data = await res.json();
    const resultado = document.getElementById('quizResultado');
    if (data.success) {
      resultado.className = data.aprobado ? 'alert alert-success mt-3' : 'alert alert-warning mt-3';
      resultado.textContent = `Puntaje: ${data.puntaje}% — ${data.aprobado ? 'Aprobado' : 'No aprobado, intenta de nuevo'}`;
    } else {
      resultado.className = 'alert alert-danger mt-3';
      resultado.textContent = data.message || 'No se pudo enviar el quiz.';
    }
  });
  <?php endif; ?>

  // Ver curso_detalle.php para el mismo mecanismo de toggle de sidebar.
  (function () {
    const btn = document.getElementById('btnToggleTemario');
    const grid = document.querySelector('.pf-detalle-grid');
    const sidebar = document.getElementById('temarioSidebar');
    if (!btn || !grid || !sidebar) return;

    function esMovil() { return window.matchMedia('(max-width: 900px)').matches; }

    let offcanvasInstancia = null;
    function offcanvas() {
      if (!offcanvasInstancia) {
        sidebar.classList.add('offcanvas', 'offcanvas-end');
        offcanvasInstancia = new bootstrap.Offcanvas(sidebar);
      }
      return offcanvasInstancia;
    }

    btn.addEventListener('click', function () {
      if (esMovil()) {
        offcanvas().toggle();
        return;
      }
      const oculto = grid.classList.toggle('pf-detalle-sin-sidebar');
      btn.setAttribute('aria-expanded', oculto ? 'false' : 'true');
    });
  })();

  // Pestaña "Preguntas y respuestas" acotada a ESTA lección — ver
  // curso_detalle.php para el mismo mecanismo.
  <?php if ($tieneAcceso || $esDemo): ?>
  (function () {
    const btn = document.getElementById('pfBtnPublicarPregunta');
    if (!btn) return;
    const textarea = document.getElementById('pfNuevaPregunta');
    const msg = document.getElementById('pfPreguntaMsg');
    let temaExistenteId = <?= json_encode($temaExistenteId) ?>;

    btn.addEventListener('click', async function () {
      const contenido = textarea.value.trim();
      if (!contenido) {
        msg.textContent = 'Escribe algo antes de publicar.';
        msg.className = 'form-text text-danger mt-1';
        return;
      }
      btn.disabled = true;
      msg.textContent = '';
      try {
        let res;
        if (temaExistenteId) {
          res = await fetch('foro/backend/responder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ tema_id: temaExistenteId, contenido, csrf_token: CSRF_TOKEN }),
          });
        } else {
          res = await fetch('foro/backend/crear_tema_leccion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              tipo: <?= json_encode($esDeCurso ? 'curso' : 'evento') ?>,
              item_id: <?= $padreId ?>,
              leccion_id: <?= $leccionId ?>,
              contenido,
              csrf_token: CSRF_TOKEN,
            }),
          });
        }
        const data = await res.json();
        if (!data.success) {
          msg.textContent = data.message || 'No se pudo publicar, intenta de nuevo.';
          msg.className = 'form-text text-danger mt-1';
          btn.disabled = false;
          return;
        }
        if (data.tema_id) temaExistenteId = data.tema_id;
        textarea.value = '';
        $.notify('¡Publicado!', { className: 'success', position: 'top right', autoHideDelay: 2500 });
        window.location.reload();
      } catch (e) {
        msg.textContent = 'Error de conexión. Intenta de nuevo.';
        msg.className = 'form-text text-danger mt-1';
        btn.disabled = false;
      }
    });
  })();
  <?php endif; ?>
</script>
<style>
  /* Ver curso_detalle.php para el mismo sistema. */
  .pf-detalle-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 28px; align-items: start; }
  .pf-detalle-grid.pf-detalle-sin-sidebar { grid-template-columns: minmax(0, 1fr); }
  .pf-detalle-grid.pf-detalle-sin-sidebar > .pf-detalle-sidebar { display: none; }
  .pf-detalle-sidebar {
    background: var(--pf-surface, #fff);
    border: 1px solid var(--pf-line, #e5e0d8);
    border-radius: var(--pf-radius-lg, 12px);
    padding: 18px;
    position: sticky;
    top: 90px;
    max-height: calc(100vh - 110px);
    overflow-y: auto;
  }
  .pf-detalle-sidebar .list-group-item.active { background: #f7931e; border-color: #f7931e; }
  @media (max-width: 900px) {
    .pf-detalle-grid { grid-template-columns: minmax(0, 1fr); }
    .pf-detalle-sidebar { position: static; max-height: none; }
  }
  .pf-detalle-tabs .nav-link { font-weight: 700; color: var(--pf-muted, #6c757d); }
  .pf-detalle-tabs .nav-link.active { color: var(--pf-ink, #212529); border-bottom: 2px solid #f7931e; }
</style>
