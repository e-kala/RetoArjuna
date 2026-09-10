<?php
// Landing pages subidas por el admin como archivo .html — el admin sube lo
// que sea que le haya exportado su herramienta de diseño (Elementor,
// Webflow, Canva, etc.), y esto se queda solo con lo que de verdad importa
// (estilos + contenido de <body>) para envolverlo en el navbar/footer de
// siempre al servirlo (ver content/landing.php). No se sanitiza el HTML/JS
// subido — solo un admin puede llegar aquí, es contenido de confianza igual
// que ya lo es el HTML libre que un admin escribe en "contenido" de un curso
// o noticia.

/**
 * Procesa el campo de subida del archivo .html. Devuelve el contenido ya
 * extraído (ver landing_extraer_contenido()) si se subió un archivo válido,
 * o null si no se subió nada (el llamador conserva el contenido existente
 * al editar). Lanza una excepción con mensaje amigable si el archivo no es
 * válido.
 */
function procesar_subida_html_landing(string $campo): ?string
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $archivo = $_FILES[$campo];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el archivo (código de error ' . $archivo['error'] . ').');
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($archivo['size'] > $maxBytes) {
        throw new RuntimeException('El archivo no puede pesar más de 5 MB.');
    }

    $extension = strtolower((string) pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['html', 'htm'], true)) {
        throw new RuntimeException('Sube un archivo .html o .htm.');
    }

    $contenido = file_get_contents($archivo['tmp_name']);
    if ($contenido === false || trim($contenido) === '') {
        throw new RuntimeException('El archivo está vacío o no se pudo leer.');
    }

    return landing_extraer_contenido($contenido);
}

/**
 * Se queda con lo que hace falta de un documento HTML completo (con
 * <html>/<head>/<body>) para insertarlo dentro del layout de la plataforma:
 * los <style>/<link rel=stylesheet> del <head> (ahí es donde casi siempre
 * vive el diseño de estas herramientas, no dentro de <body>) más el
 * contenido de <body> tal cual, y los <script> del <head> movidos al final
 * (para que corran después de que el contenido ya exista en el DOM). Si lo
 * subido ya viene como fragmento (sin <html>/<body>), se usa tal cual.
 */
function landing_extraer_contenido(string $htmlSubido): string
{
    $tieneEstructura = stripos($htmlSubido, '<html') !== false || stripos($htmlSubido, '<body') !== false;
    if (!$tieneEstructura) {
        return $htmlSubido;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // El truco de anteponer una declaración xml con el encoding es necesario
    // porque loadHTML() interpreta el string como Latin-1 por defecto si no
    // se le dice otra cosa — sin esto, acentos/ñ se corrompen. Ojo: no se
    // escribe aquí arriba tal cual (con los dos caracteres de cierre juntos)
    // porque dentro de un comentario "//" de PHP eso cierra el bloque de
    // código sin querer.
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlSubido, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $partes = [];
    $scriptsDelHead = [];

    $head = $dom->getElementsByTagName('head')->item(0);
    if ($head) {
        foreach ($head->childNodes as $nodo) {
            if ($nodo->nodeName === 'style') {
                $partes[] = $dom->saveHTML($nodo);
            } elseif ($nodo->nodeName === 'link' && $nodo instanceof DOMElement && strtolower($nodo->getAttribute('rel')) === 'stylesheet') {
                $partes[] = $dom->saveHTML($nodo);
            } elseif ($nodo->nodeName === 'script') {
                $scriptsDelHead[] = $dom->saveHTML($nodo);
            }
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $nodo) {
            $partes[] = $dom->saveHTML($nodo);
        }
    }

    return implode("\n", array_merge($partes, $scriptsDelHead));
}
