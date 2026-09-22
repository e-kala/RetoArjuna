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

// Progreso de TODAS las hermanas (no solo la actual) — para marcar cada
// lección ya completada en el temario lateral, mismo criterio que
// curso_detalle.php/evento_detalle.php.
$completadasHermanas = [];
if ($usuario && $hermanas) {
    $stmt = $esDeCurso
        ? $conn->prepare('SELECT leccion_id FROM progreso WHERE usuario_id = ? AND curso_id = ? AND completado = 1')
        : $conn->prepare('SELECT leccion_id FROM progreso WHERE usuario_id = ? AND evento_id = ? AND completado = 1');
    $stmt->bind_param('ii', $usuario['id'], $padreId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $completadasHermanas[(int) $row['leccion_id']] = true;
    }
    $stmt->close();
}

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
$temaExistenteId = foro_tema_ancla_de($cursoIdLeccion, $eventoIdLeccion, $leccionId, $leccion['foro_url']);
$respuestasLeccion = $temaExistenteId ? foro_respuestas_de($temaExistenteId) : [];
?>
<?php
$totalHermanas = count($hermanas);
$totalCompletadas = count($completadasHermanas);
$pctProgreso = $totalHermanas > 0 ? round($totalCompletadas / $totalHermanas * 100) : 0;
?>
<div class="pf-leccion-page">
  <div class="container" style="margin-top: 143px; margin-bottom: 60px;">
    <a href="?action=<?= $volverAccion ?>&slug=<?= urlencode($padreSlug) ?>" class="pf-leccion-volver d-inline-flex align-items-center gap-1 mb-3">
      <i class="bi bi-arrow-left"></i> <?= htmlspecialchars($padreTitulo) ?>
    </a>
    <?php if ($leccion['estado_publicacion'] === 'borrador'): ?>
      <div class="alert alert-warning">Estás viendo un borrador (vista previa de admin) — los alumnos todavía no pueden ver esta lección.</div>
    <?php endif; ?>

    <div class="pf-leccion-header">
      <div class="d-flex align-items-start flex-wrap gap-3">
        <div class="flex-grow-1">
          <?php if ($totalHermanas > 0): ?>
            <div class="pf-leccion-header-eyebrow">Lección <?= ($indiceActual ?? 0) + 1 ?> de <?= $totalHermanas ?></div>
          <?php endif; ?>
          <h1 class="pf-leccion-titulo"><?= htmlspecialchars($leccion['titulo']) ?><?php if ($completada): ?> <i class="bi bi-check-circle-fill text-success" title="Completada"></i><?php endif; ?></h1>
        </div>
        <div class="d-flex align-items-center gap-2">
          <?php if ($esAdmin): ?>
            <?php $parametroPadre = $leccion['evento_id'] ? 'evento_id=' . (int) $leccion['evento_id'] : 'curso_id=' . (int) $leccion['curso_id']; ?>
            <a href="panel/admin/leccion_form.php?<?= $parametroPadre ?>&id=<?= $leccionId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Editar</a>
          <?php endif; ?>
          <?php if ($hermanas): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm pf-detalle-toggle-sidebar" id="btnToggleTemario" aria-expanded="true" aria-controls="temarioSidebar">
              <i class="bi bi-list-ul"></i> Contenido
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($totalHermanas > 0): ?>
        <div class="pf-leccion-progreso mt-3">
          <div class="progress" style="height:8px;">
            <div class="progress-bar" role="progressbar" style="width:<?= $pctProgreso ?>%;background:var(--pf-accent);" aria-valuenow="<?= $pctProgreso ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <span class="pf-leccion-progreso-texto"><?= $totalCompletadas ?> de <?= $totalHermanas ?> lecciones completadas (<?= $pctProgreso ?>%)</span>
        </div>
      <?php endif; ?>
    </div>

    <?php
    $foroLeccionUrl = $leccion['foro_url']
        ? navbar_href($leccion['foro_url'], '../')
        : ($esDeCurso
            ? 'foro/curso.php?curso_id=' . $padreId . '&leccion_id=' . $leccionId
            : 'foro/evento.php?evento_id=' . $padreId . '&leccion_id=' . $leccionId);
    ?>
    <div class="pf-detalle-grid">
      <div class="pf-detalle-main">
        <div class="pf-leccion-card">
          <div class="pf-leccion-card-body">
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

            <?php if ($tieneAcceso || $esDemo): ?>
              <div class="mt-4">
                <a href="<?= htmlspecialchars($foroLeccionUrl) ?>" class="btn btn-outline-dark btn-sm">
                  <i class="bi bi-chat-square-text"></i> Discutir esta lección en el foro
                </a>
              </div>
            <?php endif; ?>

            <?php if ($usuario && $tieneAcceso && $leccion['tipo_contenido'] !== 'quiz'): ?>
              <button id="btnCompletar" class="btn pf-btn-completar<?= $completada ? ' pf-btn-completar-hecho' : '' ?> mt-3" <?= $completada ? 'disabled' : '' ?>>
                <i class="bi <?= $completada ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i>
                <?= $completada ? 'Lección completada' : 'Marcar como completada' ?>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="pf-leccion-nav mt-3">
          <?php if ($anterior): ?>
            <a class="btn btn-outline-secondary pf-leccion-nav-btn" href="?action=leccion&id=<?= (int) $anterior['id'] ?>">
              <i class="bi bi-arrow-left"></i> <span class="pf-leccion-nav-label">Anterior</span>
            </a>
          <?php else: ?><span></span><?php endif; ?>
          <?php if ($siguiente): ?>
            <a class="btn pf-leccion-nav-btn pf-leccion-nav-siguiente" href="?action=leccion&id=<?= (int) $siguiente['id'] ?>">
              <span class="pf-leccion-nav-label">Siguiente</span> <i class="bi bi-arrow-right"></i>
            </a>
          <?php endif; ?>
        </div>

        <?php if ($tieneAcceso || $esDemo): ?>
          <div class="pf-leccion-card mt-3">
            <div class="pf-leccion-card-body">
              <h2 class="h5 fw-bold mb-3"><i class="bi bi-chat-square-text"></i> Preguntas y respuestas <?php if ($respuestasLeccion): ?><span class="badge bg-secondary"><?= count($respuestasLeccion) ?></span><?php endif; ?></h2>
              <?php
              $respuestasPregyresp = $respuestasLeccion;
              $temaExistenteIdPregyresp = $temaExistenteId;
              $verForoUrlPregyresp = $foroLeccionUrl;
              require __DIR__ . '/_preguntas_respuestas.php';
              ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($hermanas): ?>
        <aside class="pf-detalle-sidebar" id="temarioSidebar">
          <h2 class="h6 fw-bold mb-3"><?= htmlspecialchars($padreTitulo) ?></h2>
          <div class="list-group">
            <?php foreach ($hermanas as $h): ?>
              <?php $hCompletada = !empty($completadasHermanas[(int) $h['id']]); ?>
              <a href="?action=leccion&id=<?= (int) $h['id'] ?>" class="list-group-item list-group-item-action<?= (int) $h['id'] === $leccionId ? ' active' : '' ?><?= $hCompletada ? ' pf-leccion-completada' : '' ?>">
                <i class="bi <?= $hCompletada ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?>"></i>
                <?= htmlspecialchars($h['titulo']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </aside>
      <?php endif; ?>
    </div>
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
      this.innerHTML = '<i class="bi bi-check-circle-fill"></i> Lección completada';
      this.classList.add('pf-btn-completar-hecho');
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
  // curso_detalle.php: siempre publica vía crear_tema_leccion.php (nunca
  // responder.php directo), porque el tema ancla nace oculto y responder.php
  // no acepta respuestas a un tema oculto para un no-admin.
  <?php if ($tieneAcceso || $esDemo): ?>
  (function () {
    const btn = document.getElementById('pfBtnPublicarPregunta');
    if (!btn) return;
    const textarea = document.getElementById('pfNuevaPregunta');
    const msg = document.getElementById('pfPreguntaMsg');

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
        const res = await fetch('foro/backend/crear_tema_leccion.php', {
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
        const data = await res.json();
        if (!data.success) {
          msg.textContent = data.message || 'No se pudo publicar, intenta de nuevo.';
          msg.className = 'form-text text-danger mt-1';
          btn.disabled = false;
          return;
        }
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
    box-shadow: var(--pf-shadow, 0 8px 24px rgba(35,38,43,0.06));
  }
  .pf-detalle-sidebar .list-group-item { border: none; border-radius: var(--pf-radius-md, 10px) !important; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; padding: 10px 12px; font-weight: 500; transition: background .15s ease; }
  .pf-detalle-sidebar .list-group-item:hover { background: var(--pf-bg, #f7f5f0); }
  .pf-detalle-sidebar .list-group-item.active { background: var(--pf-accent, #f7931e); border-color: var(--pf-accent, #f7931e); color: #fff; }
  .pf-detalle-sidebar .list-group-item.active i { color: #fff; }
  @media (max-width: 900px) {
    .pf-detalle-grid { grid-template-columns: minmax(0, 1fr); }
    .pf-detalle-sidebar { position: static; max-height: none; }
  }

  /* Header de la lección: eyebrow ("Lección X de Y"), título con más peso,
     y una barra de progreso del curso/evento completo — antes no había
     ninguna señal de avance dentro de la lección misma, solo en el sidebar. */
  .pf-leccion-volver { color: var(--pf-muted, #6b7076); text-decoration: none; font-weight: 600; font-size: .92rem; }
  .pf-leccion-volver:hover { color: var(--pf-accent-ink, #7a4a00); }
  .pf-leccion-header { margin-bottom: 24px; }
  .pf-leccion-header-eyebrow { color: var(--pf-accent-ink, #7a4a00); font-weight: 700; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
  .pf-leccion-titulo { font-size: 1.7rem; font-weight: 800; margin: 0; color: var(--pf-ink, #23262b); }
  .pf-leccion-progreso { display: flex; align-items: center; gap: 12px; }
  .pf-leccion-progreso .progress { flex-grow: 1; max-width: 320px; border-radius: 999px; background: var(--pf-line, #eae5da); }
  .pf-leccion-progreso .progress-bar { border-radius: 999px; }
  .pf-leccion-progreso-texto { font-size: .85rem; color: var(--pf-muted, #6b7076); font-weight: 600; white-space: nowrap; }

  /* Contenido principal en tarjeta con sombra — antes el contenido "flotaba"
     directo sobre el fondo de la página, sin ningún borde ni profundidad
     que lo distinguiera. */
  .pf-leccion-card { background: var(--pf-surface, #fff); border-radius: var(--pf-radius-lg, 20px); box-shadow: var(--pf-shadow, 0 8px 24px rgba(35,38,43,0.06)); overflow: hidden; }
  .pf-leccion-card-body { padding: 28px; }

  /* Botón "Marcar como completada" — antes era un btn-outline-success chico
     y discreto, ahora tiene más presencia visual y un estado "hecho" claro. */
  .pf-btn-completar { background: #fff; border: 2px solid #198754; color: #198754; font-weight: 700; padding: 10px 22px; border-radius: 999px; display: inline-flex; align-items: center; gap: 8px; transition: all .15s ease; }
  .pf-btn-completar:hover:not(:disabled) { background: #198754; color: #fff; }
  .pf-btn-completar-hecho { background: #198754; border-color: #198754; color: #fff; opacity: 1; }

  /* Navegación anterior/siguiente — antes eran btn-link discretos, ahora
     botones reales con más peso, "Siguiente" con el color de marca para
     invitar a seguir avanzando. */
  .pf-leccion-nav { display: flex; justify-content: space-between; gap: 12px; }
  .pf-leccion-nav-btn { font-weight: 700; padding: 10px 20px; border-radius: 999px; display: inline-flex; align-items: center; gap: 8px; }
  .pf-leccion-nav-siguiente { background: var(--pf-accent, #f7931e); border-color: var(--pf-accent, #f7931e); color: #fff; margin-left: auto; }
  .pf-leccion-nav-siguiente:hover { background: var(--pf-accent-ink, #7a4a00); border-color: var(--pf-accent-ink, #7a4a00); color: #fff; }
  @media (max-width: 480px) {
    .pf-leccion-nav-label { display: none; }
  }

  /* Lección completada en el temario lateral — mismo verde que el check. La
     lección actual (.active, fondo naranja) no necesita el verde encima: ya
     es obvio cuál es por estar resaltada de otra forma. */
  .pf-leccion-completada:not(.active) { background: rgba(25,135,84,0.08); }
  .pf-leccion-completada:not(.active) { color: var(--pf-ink, #212529); }
</style>
