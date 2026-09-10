<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$leccionId = (int) ($_GET['leccion_id'] ?? $_POST['leccion_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM lecciones WHERE id = ?');
$stmt->bind_param('i', $leccionId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leccion || $leccion['tipo_contenido'] !== 'quiz') {
    header('Location: cursos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_quiz') {
        $titulo = trim($_POST['titulo'] ?? '');
        $minimo = (int) ($_POST['puntaje_minimo_aprobar'] ?? 70);
        $stmt = $conn->prepare('INSERT INTO quizzes (leccion_id, titulo, puntaje_minimo_aprobar) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $leccionId, $titulo, $minimo);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'eliminar_pregunta') {
        $preguntaId = (int) $_POST['pregunta_id'];
        $stmt = $conn->prepare('DELETE FROM quiz_preguntas WHERE id = ?');
        $stmt->bind_param('i', $preguntaId);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'agregar_pregunta') {
        $quizId = (int) $_POST['quiz_id'];
        $enunciado = trim($_POST['enunciado'] ?? '');
        $orden = (int) ($_POST['orden'] ?? 0);
        $opciones = array_values(array_filter($_POST['opcion'] ?? [], fn ($o) => trim($o) !== ''));
        $correctaIdx = (int) ($_POST['correcta'] ?? -1);

        if ($enunciado !== '' && count($opciones) >= 2) {
            $stmt = $conn->prepare('INSERT INTO quiz_preguntas (quiz_id, enunciado, orden) VALUES (?, ?, ?)');
            $stmt->bind_param('isi', $quizId, $enunciado, $orden);
            $stmt->execute();
            $preguntaId = $stmt->insert_id;
            $stmt->close();

            foreach ($opciones as $i => $texto) {
                $esCorrecta = ($i === $correctaIdx) ? 1 : 0;
                $stmt = $conn->prepare('INSERT INTO quiz_opciones (pregunta_id, texto, es_correcta, orden) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('isii', $preguntaId, $texto, $esCorrecta, $i);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    if ($esAjax) {
        echo json_encode(['success' => true, 'redirect' => 'quiz_form.php?leccion_id=' . $leccionId]);
        exit;
    }
    header('Location: quiz_form.php?leccion_id=' . $leccionId);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM quizzes WHERE leccion_id = ?');
$stmt->bind_param('i', $leccionId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

$preguntas = [];
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
}

$pageTitle = 'Quiz · ' . $leccion['titulo'];
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Quiz de "<?= htmlspecialchars($leccion['titulo']) ?>"</h1>

<?php if (!$quiz): ?>
  <form method="post" class="row g-3" data-ajax-form>
    <?= csrf_field() ?>
    <input type="hidden" name="leccion_id" value="<?= $leccionId ?>">
    <input type="hidden" name="accion" value="crear_quiz">
    <div class="col-md-8"><label class="form-label">Título del quiz</label><input class="form-control" name="titulo" required></div>
    <div class="col-md-4"><label class="form-label">% mínimo para aprobar</label><input type="number" class="form-control" name="puntaje_minimo_aprobar" value="70"></div>
    <div class="col-12"><button class="btn btn-success">Crear quiz</button></div>
  </form>
<?php else: ?>
  <?php foreach ($preguntas as $p): ?>
    <div class="card mb-3 p-3">
      <p class="fw-bold"><?= htmlspecialchars($p['enunciado']) ?></p>
      <ul>
        <?php foreach ($p['opciones'] as $o): ?>
          <li><?= htmlspecialchars($o['texto']) ?> <?= (int) $o['es_correcta'] === 1 ? '✓' : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <form method="post" data-ajax-form data-confirm="¿Eliminar esta pregunta?">
        <?= csrf_field() ?>
        <input type="hidden" name="leccion_id" value="<?= $leccionId ?>">
        <input type="hidden" name="pregunta_id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="accion" value="eliminar_pregunta">
        <button class="btn btn-sm btn-outline-danger">Eliminar pregunta</button>
      </form>
    </div>
  <?php endforeach; ?>

  <div class="card p-3">
    <h5>Agregar pregunta</h5>
    <form method="post" class="row g-3" data-ajax-form>
      <?= csrf_field() ?>
      <input type="hidden" name="leccion_id" value="<?= $leccionId ?>">
      <input type="hidden" name="quiz_id" value="<?= (int) $quiz['id'] ?>">
      <input type="hidden" name="accion" value="agregar_pregunta">
      <input type="hidden" name="orden" value="<?= count($preguntas) ?>">
      <div class="col-12"><label class="form-label">Enunciado</label><input class="form-control" name="enunciado" required></div>
      <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="col-md-6 d-flex align-items-center gap-2">
          <input type="radio" name="correcta" value="<?= $i ?>" required>
          <input class="form-control" name="opcion[]" placeholder="Opción <?= $i + 1 ?><?= $i < 2 ? ' (requerida)' : ' (opcional)' ?>" <?= $i < 2 ? 'required' : '' ?>>
        </div>
      <?php endfor; ?>
      <div class="col-12"><button class="btn btn-success">Agregar pregunta</button></div>
    </form>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
