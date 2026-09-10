<?php
// Sube un archivo (PDF u otro material de apoyo) para insertarlo como link
// dentro del editor de la lección — reusa procesar_subida_archivo_digital()
// (uploads.php), mismo modelo de seguridad que ya usa la tienda: nombre
// aleatorio como protección de acceso (a diferencia del audio, este
// material no necesita quedar restringido a quien esté inscrito).
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';
require_role('admin');
header('Content-Type: application/json');
requerir_csrf_json();

try {
    $ruta = procesar_subida_archivo_digital('archivo', 'lecciones_adjuntos');
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if ($ruta === null) {
    echo json_encode(['success' => false, 'message' => 'No se subió ningún archivo.']);
    exit;
}

$nombre = (string) ($_FILES['archivo']['name'] ?? 'archivo');
echo json_encode(['success' => true, 'url' => BASE_URL . '/' . $ruta, 'nombre' => $nombre]);
