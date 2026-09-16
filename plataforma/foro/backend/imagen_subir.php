<?php
// Subida de imágenes insertadas dentro de un post del foro (tema o
// respuesta) — mismo mecanismo que panel/admin/*_form.php
// (backend/quill_imagen_subir.php), pero abierto a cualquier usuario con
// sesión (no solo admin): cualquiera puede publicar en el foro. Reusa la
// misma validación de procesar_subida_imagen() (uploads.php), solo cambia
// el subdir. Devuelve la URL absoluta para que Quill la inserte de inmediato.
require_once __DIR__ . '/foro_helpers.php';
require_once __DIR__ . '/../../backend/uploads.php';
header('Content-Type: application/json');
requerir_csrf_form();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

try {
    $ruta = procesar_subida_imagen('imagen', 'foro');
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if ($ruta === null) {
    echo json_encode(['success' => false, 'message' => 'No se subió ninguna imagen.']);
    exit;
}

// A diferencia de quill_imagen_subir.php (panel admin), aquí hace falta la
// URL completa (esquema+host, no solo la ruta) — foro_sanitizar_html_editor()
// exige que <img src> tenga un esquema http/https explícito (rechaza
// cualquier URL relativa, a propósito, para no dejar pasar data:/javascript:),
// y BASE_URL por sí solo es una ruta sin esquema.
echo json_encode(['success' => true, 'url' => SITE_URL . '/' . $ruta]);
