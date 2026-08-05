<?php
$leccionId = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT l.*, c.titulo AS curso_titulo, c.slug AS curso_slug, c.gratuito
     FROM lecciones l JOIN cursos c ON c.id = l.curso_id
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

$cursoId = (int) $leccion['curso_id'];
$usuario = current_user();
$tieneAcceso = $usuario ? usuario_tiene_acceso_curso($usuario['id'], $cursoId) : false;
$esDemo = (int) $leccion['vista_previa'] === 1;

if (!$tieneAcceso && !$esDemo) {
    header('Location: ' . BASE_URL . '/index.php?action=curso&slug=' . urlencode($leccion['curso_slug']));
    exit;
}

$stmt = $conn->prepare('SELECT id, titulo FROM lecciones WHERE curso_id = ? ORDER BY orden');
$stmt->bind_param('i', $cursoId);
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
  <a href="?action=curso&slug=<?= urlencode($leccion['curso_slug']) ?>" class="d-inline-block mb-3">&larr; <?= htmlspecialchars($leccion['curso_titulo']) ?></a>
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

  <?php if ($tieneAcceso || $esDemo): ?>
    <div class="mb-4">
      <a href="../foro/curso.php?curso_id=<?= $cursoId ?>&leccion_id=<?= $leccionId ?>" class="btn btn-outline-dark btn-sm">
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
