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
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px; max-width: 800px;">
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
  </div>

  <div class="my-4">
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
</script>
