<?php
// Una lección pertenece a un curso O a un evento (nunca ambos) — ver
// schema_lecciones_compartidas.sql. Esto permite que "Convertir a evento"
// reasigne las lecciones existentes sin perder el contenido.
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

$esDeCurso = $leccion['curso_id'] !== null;
$padreId = (int) ($esDeCurso ? $leccion['curso_id'] : $leccion['evento_id']);
$padreSlug = $esDeCurso ? $leccion['curso_slug'] : $leccion['evento_slug'];
$padreTitulo = $esDeCurso ? $leccion['curso_titulo'] : $leccion['evento_titulo'];
$volverAccion = $esDeCurso ? 'curso' : 'evento';

$usuario = current_user();
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
    ? $conn->prepare('SELECT id, titulo FROM lecciones WHERE curso_id = ? ORDER BY orden')
    : $conn->prepare('SELECT id, titulo FROM lecciones WHERE evento_id = ? ORDER BY orden');
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
if ($usuario && $esDeCurso) {
    $stmt = $conn->prepare('SELECT completado FROM progreso WHERE usuario_id = ? AND leccion_id = ?');
    $stmt->bind_param('ii', $usuario['id'], $leccionId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $completada = $fila && (int) $fila['completado'] === 1;
}

$videos = [];
$materiales = [];
if ($tieneAcceso || $esDemo) {
    $stmt = $conn->prepare('SELECT url FROM leccion_videos WHERE leccion_id = ? ORDER BY orden');
    $stmt->bind_param('i', $leccionId);
    $stmt->execute();
    $videos = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'url');
    $stmt->close();

    $stmt = $conn->prepare('SELECT titulo, url FROM leccion_materiales WHERE leccion_id = ? ORDER BY orden');
    $stmt->bind_param('i', $leccionId);
    $stmt->execute();
    $materiales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
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
  <h1 class="h3"><?= htmlspecialchars($leccion['titulo']) ?></h1>

  <div class="my-4">
    <?php if ($leccion['tipo_contenido'] === 'video'): ?>
      <?php foreach ($videos as $url): ?>
        <div class="ratio ratio-16x9 mb-3">
          <iframe src="<?= htmlspecialchars(normalizar_url_youtube($url)) ?>" allowfullscreen></iframe>
        </div>
      <?php endforeach; ?>
      <?php if (!$videos): ?><p class="text-muted">Esta lección todavía no tiene video.</p><?php endif; ?>
    <?php elseif ($leccion['tipo_contenido'] === 'pdf'): ?>
      <a class="btn btn-outline-dark" href="<?= htmlspecialchars(BASE_URL . '/' . ltrim($leccion['contenido_url'], '/')) ?>" target="_blank">Descargar PDF</a>
    <?php elseif ($leccion['tipo_contenido'] === 'quiz'): ?>
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
      <?= $leccion['contenido_texto'] ?>
    <?php endif; ?>
  </div>

  <?php if ($materiales): ?>
    <div class="mb-4">
      <h2 class="h6">Materiales de apoyo</h2>
      <ul class="list-group">
        <?php foreach ($materiales as $m): ?>
          <li class="list-group-item">
            <a href="<?= htmlspecialchars($m['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($m['titulo']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (($leccion['foro_url'] || $esDeCurso) && ($tieneAcceso || $esDemo)): ?>
    <div class="mb-4">
      <a href="<?= $leccion['foro_url'] ? htmlspecialchars(navbar_href($leccion['foro_url'], '../')) : 'foro/curso.php?curso_id=' . $padreId . '&leccion_id=' . $leccionId ?>" class="btn btn-outline-dark btn-sm">
        <i class="bi bi-chat-square-text"></i> Discutir esta lección en el foro
      </a>
    </div>
  <?php endif; ?>

  <?php if ($usuario && $esDeCurso && $tieneAcceso && $leccion['tipo_contenido'] !== 'quiz'): ?>
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

  <?php if ($usuario && $esDeCurso && $tieneAcceso && $leccion['tipo_contenido'] !== 'quiz'): ?>
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
