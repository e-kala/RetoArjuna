<?php
// Miniatura social optimizada para WhatsApp/Facebook. Se genera y cachea en
// el servidor a 1200x630 JPEG para evitar que los crawlers descarten portadas
// PNG grandes (varias superan 1 MB).
require_once __DIR__ . '/conexion.php';

$tipo = (string) ($_GET['tipo'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$config = [
    'curso' => ['tabla' => 'cursos', 'imagen' => 'imagen_portada'],
    'evento' => ['tabla' => 'eventos', 'imagen' => 'imagen_portada'],
    'producto' => ['tabla' => 'productos', 'imagen' => 'imagen'],
    'noticia' => ['tabla' => 'noticias', 'imagen' => 'imagen'],
][$tipo] ?? null;

if (!$config || $id < 1) { http_response_code(404); exit; }
$stmt = $conn->prepare("SELECT {$config['imagen']} imagen FROM {$config['tabla']} WHERE id=? AND activo=1 LIMIT 1");
$stmt->bind_param('i', $id); $stmt->execute();
$fila = $stmt->get_result()->fetch_assoc(); $stmt->close();
$ruta = trim((string) ($fila['imagen'] ?? ''));
if ($ruta === '' || preg_match('~^https?://~i', $ruta)) { http_response_code(404); exit; }
$ruta = ltrim($ruta, '/');
if (str_starts_with($ruta, 'plataforma/')) $ruta = substr($ruta, 11);
$origen = realpath(__DIR__ . '/../' . $ruta);
$raiz = realpath(__DIR__ . '/../uploads');
if (!$origen || !$raiz || !str_starts_with($origen, $raiz . DIRECTORY_SEPARATOR) || !is_file($origen)) { http_response_code(404); exit; }

$cacheDir = __DIR__ . '/../uploads/og';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cache = $cacheDir . '/' . $tipo . '-' . $id . '-' . filemtime($origen) . '.jpg';
if (!is_file($cache)) {
    $datos = @file_get_contents($origen);
    $src = $datos !== false ? @imagecreatefromstring($datos) : false;
    if (!$src) { http_response_code(415); exit; }
    $sw = imagesx($src); $sh = imagesy($src); $tw = 1200; $th = 630;
    $escala = max($tw / $sw, $th / $sh);
    $cw = (int) round($tw / $escala); $ch = (int) round($th / $escala);
    $sx = max(0, (int) floor(($sw - $cw) / 2)); $sy = max(0, (int) floor(($sh - $ch) / 2));
    $dst = imagecreatetruecolor($tw, $th);
    $blanco = imagecolorallocate($dst, 255, 255, 255); imagefill($dst, 0, 0, $blanco);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tw, $th, $cw, $ch);
    if (!@imagejpeg($dst, $cache, 80)) { imagedestroy($src); imagedestroy($dst); http_response_code(500); exit; }
    imagedestroy($src); imagedestroy($dst);
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($cache));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($cache);
