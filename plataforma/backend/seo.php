<?php
// Metadatos para vistas previas al compartir enlaces (WhatsApp, Facebook,
// Telegram, iMessage, etc.). Deben imprimirse dentro de <head> y usar URLs
// absolutas: los crawlers sociales no ejecutan JavaScript.

function seo_url_absoluta(string $ruta): string
{
    if (preg_match('~^https?://~i', $ruta)) return $ruta;
    if (str_starts_with($ruta, '//')) return 'https:' . $ruta;
    $origen = preg_replace('~' . preg_quote(BASE_URL, '~') . '$~', '', SITE_URL);
    if (str_starts_with($ruta, '/')) return rtrim($origen, '/') . $ruta;
    return rtrim(SITE_URL, '/') . '/' . ltrim($ruta, '/');
}

function seo_resumen(string $html, int $limite = 190): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return mb_strimwidth($texto, 0, $limite, '…', 'UTF-8');
}

function seo_datos_pagina(mysqli $conn): array
{
    $datos = [
        'titulo' => 'Reto Arjuna — claridad, práctica y acompañamiento',
        'descripcion' => 'Cursos, eventos y una comunidad para transformar claridad en pasos posibles.',
        'imagen' => seo_url_absoluta('/banner.png'),
        'url' => seo_url_absoluta((string) ($_SERVER['REQUEST_URI'] ?? BASE_URL . '/')),
        'tipo' => 'website',
    ];
    $action = (string) ($_GET['action'] ?? 'inicio');
    $slug = trim((string) ($_GET['slug'] ?? ''));
    if ($slug === '') return $datos;

    $config = match ($action) {
        'curso' => ['tabla' => 'cursos', 'titulo' => 'titulo', 'descripcion' => 'descripcion', 'imagen' => 'imagen_portada'],
        'evento' => ['tabla' => 'eventos', 'titulo' => 'titulo', 'descripcion' => 'descripcion', 'imagen' => 'imagen_portada'],
        'producto' => ['tabla' => 'productos', 'titulo' => 'nombre', 'descripcion' => 'descripcion', 'imagen' => 'imagen'],
        'noticia' => ['tabla' => 'noticias', 'titulo' => 'titulo', 'descripcion' => 'contenido', 'imagen' => 'imagen'],
        default => null,
    };
    if ($config) {
        $sql = "SELECT id, {$config['titulo']} AS titulo, {$config['descripcion']} AS descripcion, {$config['imagen']} AS imagen FROM {$config['tabla']} WHERE slug = ? AND activo = 1 LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fila) {
            $datos['titulo'] = trim((string) $fila['titulo']) . ' — Reto Arjuna';
            $resumen = seo_resumen((string) $fila['descripcion']);
            if ($resumen !== '') $datos['descripcion'] = $resumen;
            if (!empty($fila['imagen'])) {
                $version = substr(sha1((string) $fila['imagen']), 0, 10);
                $datos['imagen'] = SITE_URL . '/backend/og_imagen.php?tipo=' . urlencode($action) . '&id=' . (int) $fila['id'] . '&v=' . $version;
                $datos['imagen_optimizada'] = true;
            }
            $datos['tipo'] = $action === 'producto' ? 'product' : 'article';
        }
    } elseif ($action === 'landing') {
        if ($slug === 'membresia-camino-arjuna') {
            $datos['titulo'] = 'Membresía Camino Arjuna';
            $datos['descripcion'] = 'Acompañamiento, práctica continua y comunidad para sostener tu camino.';
            $datos['imagen'] = seo_url_absoluta('img/logo-membresia-camino-arjuna.png');
        } else {
            $stmt = $conn->prepare('SELECT id, titulo, contenido_html FROM landing_pages WHERE slug = ? AND activo = 1 LIMIT 1');
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($fila) {
                $datos['titulo'] = trim((string) $fila['titulo']) . ' — Reto Arjuna';
                $resumen = seo_resumen((string) $fila['contenido_html']);
                if ($resumen !== '') $datos['descripcion'] = $resumen;

                // Una landing comercial vinculada a un curso/evento (ver
                // curso_detalle.php/evento_detalle.php, landing_page_id) no
                // tiene imagen propia — se usa la portada de ESE curso/evento
                // para que el link que de verdad se comparte para vender
                // (sin ?ver=1, que es el que redirige aquí) muestre su imagen
                // real en WhatsApp en vez del banner genérico del sitio.
                // cursos.landing_page_id es UNIQUE en la práctica (una
                // landing por producto), así que un LEFT JOIN+COALESCE basta.
                $stmtImgLanding = $conn->prepare(
                    "SELECT COALESCE(
                        (SELECT imagen_portada FROM cursos WHERE landing_page_id = ? AND activo = 1 LIMIT 1),
                        (SELECT imagen_portada FROM eventos WHERE landing_page_id = ? AND activo = 1 LIMIT 1)
                    ) AS imagen"
                );
                $stmtImgLanding->bind_param('ii', $fila['id'], $fila['id']);
                $stmtImgLanding->execute();
                $imagenLanding = $stmtImgLanding->get_result()->fetch_assoc()['imagen'] ?? null;
                $stmtImgLanding->close();
                if (!empty($imagenLanding)) {
                    $datos['imagen'] = seo_url_absoluta((string) $imagenLanding);
                }
            }
        }
    }
    return $datos;
}

function seo_render_meta(array $datos): void
{
    $e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    echo '<title>' . $e($datos['titulo']) . '</title>' . "\n";
    echo '<meta name="description" content="' . $e($datos['descripcion']) . '">' . "\n";
    echo '<meta property="og:locale" content="es_MX">' . "\n";
    echo '<meta property="og:site_name" content="Reto Arjuna">' . "\n";
    echo '<meta property="og:type" content="' . $e($datos['tipo'] ?? 'website') . '">' . "\n";
    echo '<meta property="og:title" content="' . $e($datos['titulo']) . '">' . "\n";
    echo '<meta property="og:description" content="' . $e($datos['descripcion']) . '">' . "\n";
    echo '<meta property="og:url" content="' . $e($datos['url']) . '">' . "\n";
    echo '<meta property="og:image" content="' . $e($datos['imagen']) . '">' . "\n";
    echo '<meta property="og:image:secure_url" content="' . $e($datos['imagen']) . '">' . "\n";
    if (!empty($datos['imagen_optimizada'])) {
        echo '<meta property="og:image:type" content="image/jpeg">' . "\n";
        echo '<meta property="og:image:width" content="1200">' . "\n";
        echo '<meta property="og:image:height" content="630">' . "\n";
    }
    echo '<meta property="og:image:alt" content="' . $e($datos['titulo']) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . $e($datos['titulo']) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . $e($datos['descripcion']) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . $e($datos['imagen']) . '">' . "\n";
}
