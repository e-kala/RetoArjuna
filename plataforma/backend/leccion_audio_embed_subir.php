<?php
// Sube un audio protegido para insertarlo DENTRO del editor de la lección
// (panel/admin/leccion_form.php) — a diferencia del viejo formulario con
// lista aparte, aquí el archivo se sube desde el propio botón de la barra
// del editor, antes incluso de guardar la lección. La fila de leccion_audios
// nace "huérfana" (leccion_id NULL) — se liga a la lección real hasta que
// se guarda el formulario (ver leccion_form.php: busca los data-audio-id
// presentes en el contenido y reclama esas filas).
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';
require_role('admin');
header('Content-Type: application/json');
requerir_csrf_json();

try {
    $ruta = procesar_subida_audio_protegido($_FILES['audio'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if ($ruta === null) {
    echo json_encode(['success' => false, 'message' => 'No se subió ningún audio.']);
    exit;
}

$nombre = (string) ($_FILES['audio']['name'] ?? 'audio');
$stmt = $conn->prepare('INSERT INTO leccion_audios (leccion_id, ruta_archivo, titulo, orden) VALUES (NULL, ?, ?, 0)');
$stmt->bind_param('ss', $ruta, $nombre);
$stmt->execute();
$id = $stmt->insert_id;
$stmt->close();

echo json_encode(['success' => true, 'id' => $id, 'nombre' => $nombre]);
