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
