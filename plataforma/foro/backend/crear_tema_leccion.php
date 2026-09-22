<?php
// Publicar una pregunta desde la pestaña "Preguntas y respuestas" embebida en
// content/curso_detalle.php / content/evento_detalle.php / content/leccion.php
// (mockup del cliente: en vez de ver comentarios propios, esa pestaña muestra
// los temas del FORO ya vinculados a ese curso/evento/lección, con un botón
// "Ver todo en el foro"). Deliberadamente aparte de crear_tema.php: ese
// archivo exige elegir una categoría libre a mano, admite visibilidad
// compartida/privada, detecta menciones con selector de usuario, etc. — todo
// eso tiene sentido al publicar desde el foro mismo, pero sería fricción
// innecesaria para "comentar rápido en esta lección".
//
// Auto-creación LAZY del tema de la lección: no existe ningún mecanismo que
// cree un tema al crear la lección en el admin — el tema nace aquí, la
// primera vez que alguien comenta, con ESE mismo comentario como su post
// original. Si ya existe un tema para este curso/evento (+ lección, si
// aplica), se reusa: este endpoint nunca duplica temas, el cliente decide si
// llamarlo o llamar a responder.php mirando si la página ya trae un tema_id
// resuelto (ver el bloque PHP que arma $temaExistenteId en cada content/*.php).
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$tipo = ($_POST['tipo'] ?? '') === 'evento' ? 'evento' : 'curso';
$itemId = (int) ($_POST['item_id'] ?? 0);
$leccionId = (int) ($_POST['leccion_id'] ?? 0) ?: null;
$contenido = trim($_POST['contenido'] ?? '');

if (!$itemId || $contenido === '') {
    echo json_encode(['success' => false, 'message' => 'Escribe tu pregunta.']);
    exit;
}

// Validación cruzada — mismo criterio que crear_tema.php: el curso/evento
// debe existir, y si viene leccion_id, debe pertenecer exactamente a ese
// curso/evento (nunca confiar en que el cliente mandó una combinación real).
if ($tipo === 'curso') {
    $stmt = $conn->prepare('SELECT id FROM cursos WHERE id = ? LIMIT 1');
} else {
    $stmt = $conn->prepare('SELECT id FROM eventos WHERE id = ? LIMIT 1');
}
$stmt->bind_param('i', $itemId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'No se encontró el contenido.']);
    exit;
}
$stmt->close();

$leccionTitulo = null;
if ($leccionId) {
    $columnaPadre = $tipo === 'curso' ? 'curso_id' : 'evento_id';
    $stmt = $conn->prepare("SELECT id, titulo FROM lecciones WHERE id = ? AND {$columnaPadre} = ? LIMIT 1");
    $stmt->bind_param('ii', $leccionId, $itemId);
    $stmt->execute();
    $leccionFila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$leccionFila) {
        $leccionId = null;
    } else {
        $leccionTitulo = $leccionFila['titulo'];
    }
}

$contenidoHtml = foro_sanitizar_html_editor($contenido);
if (foro_contenido_html_vacio($contenidoHtml)) {
    echo json_encode(['success' => false, 'message' => 'Escribe tu pregunta.']);
    exit;
}

$cursoId = $tipo === 'curso' ? $itemId : null;
$eventoId = $tipo === 'evento' ? $itemId : null;

// ¿Ya existe un tema para esta combinación exacta? — el propio cliente ya
// debería saberlo (la página lo resuelve al cargar), pero se revalida aquí
// del lado del servidor: nunca confiar en que el estado que vio el cliente
// sigue siendo el actual (alguien más pudo haber creado el tema mientras
// tanto). Reusa el mismo criterio de foro_temas_de(), sin filtro de
// visibilidad — si existe cualquiera (aunque esté oculto), se reusa ese,
// nunca se crea un segundo tema para la misma lección.
$columna = $cursoId ? 'curso_id' : 'evento_id';
$padreId = $cursoId ?: $eventoId;
if ($leccionId) {
    $stmt = $conn->prepare("SELECT id FROM foro_temas WHERE {$columna} = ? AND leccion_id = ? LIMIT 1");
    $stmt->bind_param('ii', $padreId, $leccionId);
} else {
    $stmt = $conn->prepare("SELECT id FROM foro_temas WHERE {$columna} = ? AND leccion_id IS NULL LIMIT 1");
    $stmt->bind_param('i', $padreId);
}
$stmt->execute();
$temaExistente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($temaExistente) {
    echo json_encode(['success' => true, 'tema_id' => (int) $temaExistente['id'], 'ya_existia' => true]);
    exit;
}

$titulo = $leccionTitulo ?: ($tipo === 'curso' ? 'Preguntas del curso' : 'Preguntas del evento');
$categoriaLibreId = foro_resolver_o_crear_categoria_libre('General', (int) $usuario['id']);
if (!$categoriaLibreId) {
    echo json_encode(['success' => false, 'message' => 'No se pudo crear la pregunta, intenta de nuevo.']);
    exit;
}
$slug = foro_slug_unico(foro_slugify($titulo));

$stmt = $conn->prepare(
    // oculto=0 explícito — mismo motivo que crear_tema.php: la columna trae
    // DEFAULT 1 (heredado de cuando se agregó, para ocultar de un jalón lo
    // que ya existía), un tema nuevo desde aquí en adelante siempre nace visible.
    'INSERT INTO foro_temas (curso_id, evento_id, categoria_libre_id, leccion_id, usuario_id, titulo, slug, contenido, visibilidad, oculto, ultima_respuesta_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, "publico", 0, NOW())'
);
$stmt->bind_param('iiiiisss', $cursoId, $eventoId, $categoriaLibreId, $leccionId, $usuario['id'], $titulo, $slug, $contenidoHtml);
$stmt->execute();
$temaId = $stmt->insert_id;
$stmt->close();

foreach (foro_detectar_menciones($contenido, (int) $usuario['id']) as $mencionadoId) {
    $stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE id = ?');
    $stmt->bind_param('i', $mencionadoId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        foro_crear_notificacion($mencionadoId, 'mencion', $temaId, null, (int) $usuario['id']);
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'tema_id' => $temaId, 'ya_existia' => false]);
