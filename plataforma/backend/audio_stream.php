<?php
// Sirve un audio protegido de leccion_audios. El archivo vive fuera de
// alcance directo (uploads/audios_protegidos/.htaccess deniega todo) —
// esta es la única puerta, y revalida acceso en cada request (mismo
// criterio que leccion.php: inscripción real, no solo sesión iniciada).
// La lección puede ser de curso O evento (nunca ambos) — se revalida según
// cuál sea. Soporta Range para que el reproductor pueda buscar/adelantar.
require_once __DIR__ . '/auth.php';

$audioId = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT a.ruta_archivo, l.curso_id, l.evento_id, l.vista_previa
     FROM leccion_audios a
     JOIN lecciones l ON l.id = a.leccion_id
     WHERE a.id = ?'
);
$stmt->bind_param('i', $audioId);
$stmt->execute();
$audio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$audio || ($audio['curso_id'] === null && $audio['evento_id'] === null)) {
    http_response_code(404);
    exit('Audio no encontrado.');
}

$usuario = current_user();
$esDemo = (int) $audio['vista_previa'] === 1;
$tieneAcceso = $usuario && ($audio['curso_id'] !== null
    ? usuario_tiene_acceso_curso((int) $usuario['id'], (int) $audio['curso_id'])
    : usuario_esta_inscrito_evento((int) $usuario['id'], (int) $audio['evento_id']));

// Mismo bypass que content/leccion.php: un admin viendo su propio borrador
// en "Vista previa" no tiene por qué estar inscrito para escuchar lo que
// él mismo insertó — sin esto, el reproductor fallaba en cualquier
// borrador donde el admin de prueba no se hubiera inscrito a sí mismo.
$esPreview = $usuario && $usuario['rol'] === 'admin' && ($_GET['preview'] ?? '') === '1';
$tieneAcceso = $tieneAcceso || $esPreview;

if (!$tieneAcceso && !$esDemo) {
    http_response_code(403);
    exit('No tienes acceso a este audio.');
}

$rutaAbsoluta = __DIR__ . '/../' . $audio['ruta_archivo'];
if (!is_file($rutaAbsoluta)) {
    http_response_code(404);
    exit('El archivo ya no está disponible en el servidor.');
}

$extension = strtolower((string) pathinfo($rutaAbsoluta, PATHINFO_EXTENSION));
$tiposMime = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];
$mime = $tiposMime[$extension] ?? 'application/octet-stream';

$tamano = filesize($rutaAbsoluta);
$inicio = 0;
$fin = $tamano - 1;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') {
        $inicio = (int) $m[1];
    }
    if ($m[2] !== '') {
        $fin = (int) $m[2];
    }
    $fin = min($fin, $tamano - 1);
    if ($inicio > $fin) {
        http_response_code(416);
        header('Content-Range: bytes */' . $tamano);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $inicio . '-' . $fin . '/' . $tamano);
}

header('Content-Length: ' . ($fin - $inicio + 1));

$flujo = fopen($rutaAbsoluta, 'rb');
fseek($flujo, $inicio);
$restante = $fin - $inicio + 1;
$bloque = 8192;
while ($restante > 0 && !feof($flujo)) {
    $leer = (int) min($bloque, $restante);
    echo fread($flujo, $leer);
    $restante -= $leer;
    flush();
}
fclose($flujo);
