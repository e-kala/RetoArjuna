<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/certificados.php';
header('Content-Type: application/json');
requerir_csrf_json();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$leccionId = (int) ($_POST['leccion_id'] ?? 0);
$respuestas = json_decode($_POST['respuestas'] ?? '{}', true);
if (!is_array($respuestas)) {
    $respuestas = [];
}

$stmt = $conn->prepare("SELECT curso_id, evento_id, tipo_contenido FROM lecciones WHERE id = ?");
$stmt->bind_param('i', $leccionId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leccion || $leccion['tipo_contenido'] !== 'quiz') {
    echo json_encode(['success' => false, 'message' => 'Lección de quiz no encontrada.']);
    exit;
}

// Una lección es de curso O de evento (ver schema_lecciones_compartidas.sql).
$cursoId = $leccion['curso_id'] !== null ? (int) $leccion['curso_id'] : null;
$eventoId = $leccion['evento_id'] !== null ? (int) $leccion['evento_id'] : null;
$tieneAcceso = $cursoId !== null
    ? usuario_tiene_acceso_curso($usuarioPerfilId, $cursoId)
    : ($eventoId !== null && usuario_esta_inscrito_evento($usuarioPerfilId, $eventoId));

if (!$tieneAcceso) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes acceso a este contenido.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, puntaje_minimo_aprobar FROM quizzes WHERE leccion_id = ?');
$stmt->bind_param('i', $leccionId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    echo json_encode(['success' => false, 'message' => 'Quiz no encontrado.']);
    exit;
}

$quizId = (int) $quiz['id'];
$stmt = $conn->prepare('SELECT id FROM quiz_preguntas WHERE quiz_id = ? ORDER BY orden');
$stmt->bind_param('i', $quizId);
$stmt->execute();
$preguntas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = count($preguntas);
$correctas = 0;

foreach ($preguntas as $pregunta) {
    $preguntaId = (int) $pregunta['id'];
    $opcionElegida = (int) ($respuestas['pregunta_' . $preguntaId] ?? 0);
    if ($opcionElegida === 0) {
        continue;
    }

    $stmt = $conn->prepare(
        'SELECT es_correcta FROM quiz_opciones WHERE id = ? AND pregunta_id = ?'
    );
    $stmt->bind_param('ii', $opcionElegida, $preguntaId);
    $stmt->execute();
    $opcion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($opcion && (int) $opcion['es_correcta'] === 1) {
        $correctas++;
    }
}

$puntaje = $total > 0 ? (int) round($correctas / $total * 100) : 0;
$aprobado = $puntaje >= (int) $quiz['puntaje_minimo_aprobar'];

$stmt = $conn->prepare(
    'INSERT INTO quiz_intentos (usuario_id, quiz_id, puntaje, total_preguntas, aprobado) VALUES (?, ?, ?, ?, ?)'
);
$aprobadoInt = $aprobado ? 1 : 0;
$stmt->bind_param('iiiii', $usuarioPerfilId, $quizId, $puntaje, $total, $aprobadoInt);
$stmt->execute();
$stmt->close();

// El progreso por lección (y el certificado al terminar) solo existe para
// cursos — los eventos ya tienen su propio reconocimiento por asistencia
// (evento_inscripciones + certificados), no depende de esto.
if ($aprobado && $cursoId !== null) {
    $stmt = $conn->prepare(
        "INSERT INTO progreso (usuario_id, curso_id, leccion_id, completado)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE completado = 1"
    );
    $stmt->bind_param('iii', $usuarioPerfilId, $cursoId, $leccionId);
    $stmt->execute();
    $stmt->close();

    verificar_y_emitir_certificado($usuarioPerfilId, $cursoId);
}

echo json_encode(['success' => true, 'puntaje' => $puntaje, 'aprobado' => $aprobado]);
