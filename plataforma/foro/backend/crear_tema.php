<?php
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$cursoId = (int) ($_POST['curso_id'] ?? 0) ?: null;
$eventoId = (int) ($_POST['evento_id'] ?? 0) ?: null;
$leccionId = (int) ($_POST['leccion_id'] ?? 0) ?: null;
$titulo = trim($_POST['titulo'] ?? '');
$contenido = trim($_POST['contenido'] ?? '');
// curso_id y evento_id son mutuamente excluyentes — si por algún motivo
// llegan ambos, se prioriza curso_id y se ignora evento_id.
if ($cursoId) {
    $eventoId = null;
}

if ($titulo === '' || $contenido === '') {
    echo json_encode(['success' => false, 'message' => 'Completa el título y el mensaje.']);
    exit;
}

if ($cursoId) {
    $stmt = $conn->prepare('SELECT id FROM cursos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $cursoId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $cursoId = null;
        $leccionId = null;
    }
    $stmt->close();
}
if ($cursoId && $leccionId) {
    $stmt = $conn->prepare('SELECT id FROM lecciones WHERE id = ? AND curso_id = ? LIMIT 1');
    $stmt->bind_param('ii', $leccionId, $cursoId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $leccionId = null;
    }
    $stmt->close();
}
if ($eventoId) {
    $stmt = $conn->prepare('SELECT id FROM eventos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $eventoId = null;
        $leccionId = null;
    }
    $stmt->close();
}
if ($eventoId && $leccionId) {
    $stmt = $conn->prepare('SELECT id FROM lecciones WHERE id = ? AND evento_id = ? LIMIT 1');
    $stmt->bind_param('ii', $leccionId, $eventoId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $leccionId = null;
    }
    $stmt->close();
}

// visibilidad/compartido_con solo se honran cuando el tema está ligado a una
// lección — nunca en un tema de categoría normal, sin importar lo que mande
// el cliente (defensa del lado del servidor, no solo ocultar el campo en la UI).
$visibilidad = 'publico';
$compartidoConUsuarioId = null;
if ($leccionId) {
    $visibilidadPost = $_POST['visibilidad'] ?? 'publico';
    if (in_array($visibilidadPost, ['publico', 'privado', 'compartido'], true)) {
        $visibilidad = $visibilidadPost;
    }
    if ($visibilidad === 'compartido') {
        $compartidoConHandle = trim($_POST['compartido_con'] ?? '');
        $compartidoConUsuarioId = $compartidoConHandle !== '' ? foro_resolver_usuario_por_handle($compartidoConHandle) : null;
        if (!$compartidoConUsuarioId || $compartidoConUsuarioId === (int) $usuario['id']) {
            echo json_encode(['success' => false, 'message' => 'Escribe el usuario o correo de una persona válida para compartir esta entrada.']);
            exit;
        }
    }
}

$contenidoHtml = foro_sanitizar_html_editor($contenido);
if (foro_contenido_html_vacio($contenidoHtml)) {
    echo json_encode(['success' => false, 'message' => 'Escribe un mensaje.']);
    exit;
}

$slug = foro_slug_unico(foro_slugify($titulo));

// Categoría libre (tercera taxonomía, aparte de curso/evento y etiquetas) —
// una nueva escrita a mano gana sobre una elegida en el select, igual que ya
// hace el "escribe una nueva" de las etiquetas más abajo. Sin aprobación de
// admin: se crea (o se reutiliza si ya existe con ese nombre) al vuelo.
$categoriaLibreNuevaTexto = trim($_POST['categoria_libre_nueva'] ?? '');
if ($categoriaLibreNuevaTexto !== '') {
    $categoriaLibreId = foro_resolver_o_crear_categoria_libre($categoriaLibreNuevaTexto, (int) $usuario['id']);
} else {
    $categoriaLibreId = (int) ($_POST['categoria_libre_id'] ?? 0) ?: null;
    if ($categoriaLibreId) {
        $stmt = $conn->prepare('SELECT id FROM foro_categorias_libres WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $categoriaLibreId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $categoriaLibreId = null;
        }
        $stmt->close();
    }
}
// Obligatoria — nunca solo del lado del cliente: sin esto, cualquiera podría
// publicar sin categoría con una petición armada a mano.
if (!$categoriaLibreId) {
    echo json_encode(['success' => false, 'message' => 'Elige una categoría de la lista o escribe una nueva.']);
    exit;
}

$stmt = $conn->prepare(
    // oculto=0 explícito: la columna trae DEFAULT 1 para que agregarla en
    // producción oculte de un jalón los temas que ya existían ("por ahora
    // oculta todas las que ya hay") — un tema publicado desde aquí en
    // adelante siempre nace visible, sin depender de ese default.
    'INSERT INTO foro_temas (curso_id, evento_id, categoria_libre_id, leccion_id, usuario_id, titulo, slug, contenido, visibilidad, compartido_con_usuario_id, oculto, ultima_respuesta_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())'
);
$stmt->bind_param('iiiiissssi', $cursoId, $eventoId, $categoriaLibreId, $leccionId, $usuario['id'], $titulo, $slug, $contenidoHtml, $visibilidad, $compartidoConUsuarioId);
$stmt->execute();
$temaId = $stmt->insert_id;
$stmt->close();

// Etiquetas: ids existentes elegidos por checkbox (solo aprobadas, para que
// nadie fuerce a mano un id pendiente/inválido) + nombres nuevos escritos a
// mano (separados por coma), que quedan pendientes de aprobación de un
// admin — ver foro_resolver_o_crear_etiqueta().
$etiquetaIds = array_map('intval', (array) ($_POST['etiquetas'] ?? []));
$etiquetaIds = array_filter($etiquetaIds, fn($id) => $id > 0);
if ($etiquetaIds) {
    $placeholders = implode(',', array_fill(0, count($etiquetaIds), '?'));
    $tipos = str_repeat('i', count($etiquetaIds));
    $stmt = $conn->prepare("SELECT id FROM foro_categorias WHERE aprobada = 1 AND id IN ($placeholders)");
    $stmt->bind_param($tipos, ...$etiquetaIds);
    $stmt->execute();
    $etiquetaIds = array_map(fn($r) => (int) $r['id'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}
$etiquetaNuevaTexto = trim($_POST['etiqueta_nueva'] ?? '');
if ($etiquetaNuevaTexto !== '') {
    $nombresNuevos = array_unique(array_filter(array_map('trim', explode(',', $etiquetaNuevaTexto))));
    foreach ($nombresNuevos as $nombre) {
        $nuevoId = foro_resolver_o_crear_etiqueta($nombre, (int) $usuario['id']);
        if ($nuevoId) {
            $etiquetaIds[] = $nuevoId;
        }
    }
}
$etiquetaIds = array_unique($etiquetaIds);
foreach ($etiquetaIds as $etiquetaId) {
    $stmt = $conn->prepare('INSERT IGNORE INTO foro_tema_etiquetas (tema_id, categoria_id, creado_por) VALUES (?, ?, ?)');
    $stmt->bind_param('iii', $temaId, $etiquetaId, $usuario['id']);
    $stmt->execute();
    $stmt->close();
}

$tema = ['usuario_id' => $usuario['id'], 'visibilidad' => $visibilidad, 'compartido_con_usuario_id' => $compartidoConUsuarioId];
foreach (foro_detectar_menciones($contenido, $usuario['id']) as $mencionadoId) {
    $stmt = $conn->prepare('SELECT id, rol FROM usuarios_perfil WHERE id = ?');
    $stmt->bind_param('i', $mencionadoId);
    $stmt->execute();
    $mencionado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($mencionado && foro_tema_es_visible($tema, $mencionado)) {
        foro_crear_notificacion($mencionadoId, 'mencion', $temaId, null, $usuario['id']);
    }
}

echo json_encode(['success' => true, 'redirect' => 'tema.php?id=' . $temaId]);
