<?php
// Edita un tema ya publicado (autor o admin, sin límite de tiempo) — guarda la
// versión anterior en foro_temas_historial antes de sobrescribir. Fase C:
// todavía sobre el pipeline de texto plano existente (convertir_contenido_foro),
// el editor de texto enriquecido llega en una fase aparte.
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

$usuario = current_user();
$temaId = (int) ($_POST['tema_id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$contenidoNuevo = trim($_POST['contenido'] ?? '');

if ($titulo === '' || $contenidoNuevo === '') {
    echo json_encode(['success' => false, 'message' => 'Completa el título y el contenido.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, usuario_id, titulo, contenido, cerrado, curso_id, evento_id, leccion_id FROM foro_temas WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $temaId);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tema) {
    echo json_encode(['success' => false, 'message' => 'El tema no existe.']);
    exit;
}

$esAdmin = $usuario['rol'] === 'admin';
if ((int) $tema['usuario_id'] !== (int) $usuario['id'] && !$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar este tema.']);
    exit;
}

$contenidoConvertido = foro_sanitizar_html_editor($contenidoNuevo);
if (foro_contenido_html_vacio($contenidoConvertido)) {
    echo json_encode(['success' => false, 'message' => 'Escribe un mensaje.']);
    exit;
}

// Categoría libre (tercera taxonomía) — obligatoria, igual que en
// crear_tema.php; se valida ANTES de escribir nada (el título/contenido de
// más abajo ya se habría guardado si esto se validara después, dejando un
// guardado a medias). A diferencia de curso/evento/etiquetas, esto SÍ lo
// puede cambiar el autor normal, no solo un admin: es contenido de bajo
// riesgo que el propio usuario inventó, sin aprobación de por medio. Una
// nueva escrita a mano gana sobre lo elegido en el select.
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
if (!$categoriaLibreId) {
    echo json_encode(['success' => false, 'message' => 'Elige una categoría de la lista o escribe una nueva.']);
    exit;
}

// Sin cambios reales en título/contenido: no ensucia el historial con
// reenvíos idénticos — pero la reasignación de curso/evento/etiquetas (más
// abajo, solo admin) se procesa de todos modos, independiente de esto.
$huboCambioContenido = !($titulo === $tema['titulo'] && $contenidoConvertido === $tema['contenido']);

if ($huboCambioContenido) {
    $stmt = $conn->prepare(
        'INSERT INTO foro_temas_historial (tema_id, titulo_anterior, contenido_anterior, editado_por) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('issi', $temaId, $tema['titulo'], $tema['contenido'], $usuario['id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'UPDATE foro_temas SET titulo = ?, contenido = ?, editado_en = NOW(), editado_por = ? WHERE id = ?'
    );
    $stmt->bind_param('ssii', $titulo, $contenidoConvertido, $usuario['id'], $temaId);
    $stmt->execute();
    $stmt->close();
}

// Reasignar curso/evento/lección/etiquetas — solo admin, y solo si el
// formulario mandó esos campos (el editor de un autor normal no los envía).
// Misma validación que crear_tema.php, duplicada aquí a propósito (ese
// archivo no se toca).
if ($esAdmin && isset($_POST['curso_id'])) {
    $cursoId = (int) ($_POST['curso_id'] ?? 0) ?: null;
    $eventoId = (int) ($_POST['evento_id'] ?? 0) ?: null;
    $leccionId = (int) ($_POST['leccion_id'] ?? 0) ?: null;
    if ($cursoId) {
        $eventoId = null;
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

    $stmt = $conn->prepare('UPDATE foro_temas SET curso_id = ?, evento_id = ?, leccion_id = ? WHERE id = ?');
    $stmt->bind_param('iiii', $cursoId, $eventoId, $leccionId, $temaId);
    $stmt->execute();
    $stmt->close();

    $etiquetaIds = array_map('intval', (array) ($_POST['etiquetas'] ?? []));
    $etiquetaIds = array_unique(array_filter($etiquetaIds, fn($id) => $id > 0));
    $conn->query('DELETE FROM foro_tema_etiquetas WHERE tema_id = ' . $temaId);
    foreach ($etiquetaIds as $etiquetaId) {
        $stmt = $conn->prepare('INSERT IGNORE INTO foro_tema_etiquetas (tema_id, categoria_id, creado_por) VALUES (?, ?, ?)');
        $stmt->bind_param('iii', $temaId, $etiquetaId, $usuario['id']);
        $stmt->execute();
        $stmt->close();
    }
}

// $categoriaLibreId ya se resolvió y validó arriba, antes de tocar la BD.
$stmt = $conn->prepare('UPDATE foro_temas SET categoria_libre_id = ? WHERE id = ?');
$stmt->bind_param('ii', $categoriaLibreId, $temaId);
$stmt->execute();
$stmt->close();

$tituloFinal = $huboCambioContenido ? $titulo : $tema['titulo'];
$contenidoFinal = $huboCambioContenido ? $contenidoConvertido : $tema['contenido'];
echo json_encode(['success' => true, 'titulo' => $tituloFinal, 'contenido_html' => $contenidoFinal]);
