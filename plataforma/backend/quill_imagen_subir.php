<?php
// Subida de imágenes insertadas DENTRO del contenido de un editor Quill
// (panel/admin/contenido_form.php) — reusa la misma validación de
// procesar_subida_imagen() (uploads.php), solo cambia el subdir. Devuelve la
// URL absoluta para que Quill la inserte en el editor de inmediato.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';
require_role('admin');
header('Content-Type: application/json');
requerir_csrf_json();

try {
    $ruta = procesar_subida_imagen('imagen', 'contenido');
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if ($ruta === null) {
    echo json_encode(['success' => false, 'message' => 'No se subió ninguna imagen.']);
    exit;
}

echo json_encode(['success' => true, 'url' => BASE_URL . '/' . $ruta]);
