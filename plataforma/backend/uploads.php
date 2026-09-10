<?php
// Subida de imágenes para tarjetas de cursos/productos. Valida tipo real (no solo
// la extensión que mande el navegador) vía finfo, genera un nombre único (evita
// colisiones y que alguien adivine/sobreescriba archivos ajenos), y guarda dentro
// de plataforma/uploads/<subdir>/ (bloqueado a ejecución de PHP, ver .htaccess ahí).

/**
 * Procesa un campo de subida de imagen opcional. Devuelve la ruta relativa nueva
 * (para guardar en la DB) si se subió un archivo válido, null si no se subió nada
 * (el llamador debe entonces conservar la imagen ya existente), o lanza una
 * excepción con un mensaje amigable si el archivo no es válido.
 */
function procesar_subida_imagen(string $campo, string $subdir): ?string
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $archivo = $_FILES[$campo];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen (código de error ' . $archivo['error'] . ').');
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($archivo['size'] > $maxBytes) {
        throw new RuntimeException('La imagen no puede pesar más de 5 MB.');
    }

    $extensionesPermitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!isset($extensionesPermitidas[$mime])) {
        throw new RuntimeException('Formato de imagen no soportado. Usa JPG, PNG, WEBP o GIF.');
    }

    $nombreNuevo = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$mime];
    $dirDestino = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }
    $rutaDestino = $dirDestino . '/' . $nombreNuevo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }

    return 'uploads/' . $subdir . '/' . $nombreNuevo;
}

/**
 * Valida que una ruta elegida desde el selector "elegir de las ya subidas"
 * (ver _imagen_picker.php) sea realmente una imagen que ya existe dentro de
 * uploads/<subdir>/ — nunca confiar en la ruta que mande el navegador sin
 * comprobarla contra el disco, aunque el selector solo la venga de un
 * <select>/JS controlado por nosotros.
 */
function validar_imagen_existente(string $ruta, string $subdir): ?string
{
    $prefijo = 'uploads/' . $subdir . '/';
    if (!str_starts_with($ruta, $prefijo)) {
        return null;
    }
    $nombreArchivo = substr($ruta, strlen($prefijo));
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif)$/i', $nombreArchivo)) {
        return null;
    }
    $rutaAbsoluta = __DIR__ . '/../' . $ruta;
    return is_file($rutaAbsoluta) ? $ruta : null;
}

/**
 * Lista las imágenes ya subidas dentro de uploads/<subdir>/, más recientes
 * primero — para el selector "elegir de las ya subidas" (_imagen_picker.php).
 */
function listar_imagenes_subidas(string $subdir): array
{
    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir)) {
        return [];
    }
    $extensionesImagen = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $archivos = [];
    foreach (scandir($dir) ?: [] as $nombre) {
        $ruta = $dir . '/' . $nombre;
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (is_file($ruta) && in_array($ext, $extensionesImagen, true)) {
            $archivos[$nombre] = filemtime($ruta);
        }
    }
    arsort($archivos);
    return array_map(fn ($nombre) => 'uploads/' . $subdir . '/' . $nombre, array_keys($archivos));
}

/**
 * Combina las tres formas en que puede llegar la imagen de un form con
 * _imagen_picker.php: un archivo nuevo (arrastrado/subido), una ya subida
 * antes que se reutiliza (elegida de la galería), o ninguna de las dos (se
 * conserva la que ya estaba guardada). Centraliza este orden para no
 * repetirlo en cada _form.php. Puede lanzar RuntimeException (ver
 * procesar_subida_imagen) si se intentó subir un archivo inválido.
 */
function procesar_imagen_form(string $campoFile, string $subdir, string $actual): string
{
    $subida = procesar_subida_imagen($campoFile, $subdir);
    if ($subida !== null) {
        return $subida;
    }
    $existente = validar_imagen_existente(trim($_POST[$campoFile . '_existente'] ?? ''), $subdir);
    if ($existente !== null) {
        return $existente;
    }
    return $actual;
}

/**
 * Igual que procesar_subida_imagen() pero para el archivo entregable de un
 * producto digital (PDF, ZIP, audio, video, ebook). El nombre aleatorio es la
 * única protección de acceso — no hay endpoint de descarga con verificación de
 * compra, el enlace solo se muestra en "Mis compras" a quien ya pagó — mismo
 * modelo de seguridad que ya se usa para las imágenes subidas aquí.
 */
function procesar_subida_archivo_digital(string $campo, string $subdir): ?string
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $archivo = $_FILES[$campo];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el archivo (código de error ' . $archivo['error'] . ').');
    }

    $maxBytes = 100 * 1024 * 1024;
    if ($archivo['size'] > $maxBytes) {
        throw new RuntimeException('El archivo no puede pesar más de 100 MB.');
    }

    $extensionesPermitidas = [
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'application/epub+zip' => 'epub',
        'audio/mpeg' => 'mp3',
        'video/mp4' => 'mp4',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!isset($extensionesPermitidas[$mime])) {
        throw new RuntimeException('Formato no soportado. Usa PDF, ZIP, EPUB, MP3 o MP4.');
    }

    $nombreNuevo = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$mime];
    $dirDestino = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }
    $rutaDestino = $dirDestino . '/' . $nombreNuevo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    }

    return 'uploads/' . $subdir . '/' . $nombreNuevo;
}

/**
 * Audio protegido de una lección de curso. A diferencia de
 * procesar_subida_archivo_digital() (nombre aleatorio como única
 * protección, pensado para "mis compras"), este archivo se guarda en
 * uploads/audios_protegidos/, con acceso directo bloqueado por .htaccess —
 * solo se sirve vía audio_stream.php, que revalida acceso al curso en cada
 * request. Se puede llamar varias veces por request (uno por cada <input
 * type=file> del form de lección), por eso recibe el array de $_FILES ya
 * indexado en vez de leer $_FILES[$campo] directo.
 */
function procesar_subida_audio_protegido(array $archivo): ?string
{
    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el audio (código de error ' . $archivo['error'] . ').');
    }

    $maxBytes = 200 * 1024 * 1024;
    if ($archivo['size'] > $maxBytes) {
        throw new RuntimeException('El audio no puede pesar más de 200 MB.');
    }

    $extensionesPermitidas = [
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!isset($extensionesPermitidas[$mime])) {
        throw new RuntimeException('Formato de audio no soportado. Usa MP3, M4A, WAV o OGG.');
    }

    $nombreNuevo = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$mime];
    $dirDestino = __DIR__ . '/../uploads/audios_protegidos';
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }
    $rutaDestino = $dirDestino . '/' . $nombreNuevo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar el audio en el servidor.');
    }

    return 'uploads/audios_protegidos/' . $nombreNuevo;
}

/**
 * Comprobante de pago (transferencia bancaria) subido directo desde la
 * plataforma — foto o PDF del depósito. Mismo patrón de validación/nombre
 * aleatorio que procesar_subida_imagen(), aceptando también PDF.
 */
function procesar_subida_comprobante(string $campo, string $subdir): ?string
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $archivo = $_FILES[$campo];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el comprobante (código de error ' . $archivo['error'] . ').');
    }

    $maxBytes = 8 * 1024 * 1024;
    if ($archivo['size'] > $maxBytes) {
        throw new RuntimeException('El comprobante no puede pesar más de 8 MB.');
    }

    $extensionesPermitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!isset($extensionesPermitidas[$mime])) {
        throw new RuntimeException('Formato no soportado. Usa JPG, PNG, WEBP o PDF.');
    }

    $nombreNuevo = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$mime];
    $dirDestino = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }
    $rutaDestino = $dirDestino . '/' . $nombreNuevo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar el comprobante en el servidor.');
    }

    return 'uploads/' . $subdir . '/' . $nombreNuevo;
}
