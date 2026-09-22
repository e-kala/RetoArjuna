<?php
// Publicar la primera respuesta desde la pestaña "Preguntas y respuestas"
// embebida en content/curso_detalle.php / content/evento_detalle.php /
// content/leccion.php — esa pestaña muestra las RESPUESTAS del tema "ancla"
// de ese curso/evento/lección (ver foro_crear_tema_ancla()/foro_tema_ancla_de()
// en foro_helpers.php), con un botón "Ver todo en el foro".
//
// Desde que existe foro_crear_tema_ancla(), todo curso/evento/lección nuevo
// ya nace con su tema ancla oculto (creado en contenido_form.php/
// leccion_form.php) — este endpoint YA NO crea temas nuevos en el caso
// normal, solo activa el ancla (oculto=0) y guarda el contenido como su
// PRIMERA RESPUESTA (nunca reemplaza el tema en sí). El fallback de crear un
// tema aquí mismo se conserva solo para contenido creado ANTES de que
// existiera este mecanismo y que un admin todavía no migró a mano (ver
// tema.php, selector "Reasignar" — la vía recomendada para vincular un tema
// histórico ya existente como el ancla real, en vez de dejar que este
// fallback cree uno nuevo vacío al lado).
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
    $stmt = $conn->prepare('SELECT id, foro_url FROM cursos WHERE id = ? LIMIT 1');
} else {
    $stmt = $conn->prepare('SELECT id, foro_url FROM eventos WHERE id = ? LIMIT 1');
}
$stmt->bind_param('i', $itemId);
$stmt->execute();
$itemFila = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$itemFila) {
    echo json_encode(['success' => false, 'message' => 'No se encontró el contenido.']);
    exit;
}
// Con lección, el foro_url que cuenta es el de la LECCIÓN (más específico),
// no el del curso/evento contenedor — mismo criterio que content/leccion.php.
$foroUrlLegacy = $itemFila['foro_url'];

$leccionTitulo = null;
if ($leccionId) {
    $columnaPadre = $tipo === 'curso' ? 'curso_id' : 'evento_id';
    $stmt = $conn->prepare("SELECT id, titulo, foro_url FROM lecciones WHERE id = ? AND {$columnaPadre} = ? LIMIT 1");
    $stmt->bind_param('ii', $leccionId, $itemId);
    $stmt->execute();
    $leccionFila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$leccionFila) {
        $leccionId = null;
    } else {
        $leccionTitulo = $leccionFila['titulo'];
        $foroUrlLegacy = $leccionFila['foro_url'];
    }
}

$contenidoHtml = foro_sanitizar_html_editor($contenido);
if (foro_contenido_html_vacio($contenidoHtml)) {
    echo json_encode(['success' => false, 'message' => 'Escribe tu pregunta.']);
    exit;
}

$cursoId = $tipo === 'curso' ? $itemId : null;
$eventoId = $tipo === 'evento' ? $itemId : null;

// El caso normal: el tema ancla ya existe (nació con el curso/evento/lección
// en contenido_form.php/leccion_form.php) — se activa si seguía oculto y el
// contenido se guarda como su PRIMERA RESPUESTA (nunca reemplaza el tema).
$temaId = foro_tema_ancla_de($cursoId, $eventoId, $leccionId, $foroUrlLegacy);
$temaEraNuevo = false;

if (!$temaId) {
    // Fallback legacy: contenido creado antes de que existiera el tema
    // ancla y que un admin todavía no migró a mano desde tema.php
    // ("Reasignar", ver la nota de arriba) — se crea uno ahora mismo, ya
    // visible de entrada (a diferencia del ancla, que nace oculta), para no
    // dejar a quien está comentando sin ningún lugar donde publicar.
    $titulo = $leccionTitulo ?: ($tipo === 'curso' ? 'Preguntas del curso' : 'Preguntas del evento');
    $categoriaLibreId = foro_resolver_o_crear_categoria_libre('General', (int) $usuario['id']);
    if (!$categoriaLibreId) {
        echo json_encode(['success' => false, 'message' => 'No se pudo publicar, intenta de nuevo.']);
        exit;
    }
    $slug = foro_slug_unico(foro_slugify($titulo));
    $stmt = $conn->prepare(
        'INSERT INTO foro_temas (curso_id, evento_id, categoria_libre_id, leccion_id, usuario_id, titulo, slug, contenido, visibilidad, oculto, ultima_respuesta_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, "", "publico", 0, NOW())'
    );
    $stmt->bind_param('iiiiiss', $cursoId, $eventoId, $categoriaLibreId, $leccionId, $usuario['id'], $titulo, $slug);
    $stmt->execute();
    $temaId = $stmt->insert_id;
    $stmt->close();
    $temaEraNuevo = true;
}

foro_activar_tema_ancla($temaId);

$stmt = $conn->prepare('INSERT INTO foro_respuestas (tema_id, usuario_id, contenido) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $temaId, $usuario['id'], $contenidoHtml);
$stmt->execute();
$respuestaId = $stmt->insert_id;
$stmt->close();

$conn->query('UPDATE foro_temas SET respuestas_count = respuestas_count + 1, ultima_respuesta_at = NOW() WHERE id = ' . $temaId);

foreach (foro_detectar_menciones($contenido, (int) $usuario['id']) as $mencionadoId) {
    $stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE id = ?');
    $stmt->bind_param('i', $mencionadoId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        foro_crear_notificacion($mencionadoId, 'mencion', $temaId, $respuestaId, (int) $usuario['id']);
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'tema_id' => $temaId, 'ya_existia' => !$temaEraNuevo]);
