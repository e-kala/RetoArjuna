<?php
// Funciones compartidas por todas las páginas del foro propio.
require_once __DIR__ . '/../../plataforma/backend/auth.php';

/**
 * Convierte contenido de un post a HTML seguro para guardar/mostrar.
 *
 * El contenido migrado de Flarum no es texto plano: Flarum usa la librería
 * s9e/TextFormatter para parsear cada post, y lo que queda guardado es la
 * representación XML intermedia de esa librería (raíz <r> si tiene formato,
 * <t> si es puro texto) — <H3>, <STRONG>, <LIST>/<LI>, <URL>, <IMG>, etc. como
 * tags semánticos, y <s>/<e> como "decoración" que solo repite la sintaxis
 * cruda (ej. "### " antes de un <H3>) y se descarta. Si en el futuro se pega
 * contenido con ese mismo formato (otro export de Flarum) en el editor del
 * foro, esta misma función lo reconoce y lo convierte igual.
 *
 * Si el texto NO tiene ese formato (un post normal escrito en el foro nuevo),
 * se trata como texto plano y solo se escapa + preservan saltos de línea.
 */
function convertir_contenido_foro(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }
    if (!preg_match('/^<[rt]>/', $texto)) {
        return nl2br(htmlspecialchars($texto));
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $cargo = $dom->loadXML($texto);
    libxml_clear_errors();
    if (!$cargo || !$dom->documentElement) {
        return nl2br(htmlspecialchars($texto));
    }

    return trim(foro_flarum_nodo_a_html($dom->documentElement));
}

function foro_flarum_nodo_a_html(DOMNode $nodo): string
{
    $salida = '';
    foreach ($nodo->childNodes as $hijo) {
        if ($hijo->nodeType === XML_TEXT_NODE) {
            $salida .= htmlspecialchars($hijo->textContent);
            continue;
        }
        if ($hijo->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tag = $hijo->nodeName;

        // Sintaxis cruda (### , **, - , [ ]( )...): el tag semántico ya
        // representa el formato, este contenido se descarta siempre.
        if (in_array($tag, ['s', 'e', 'i'], true)) {
            continue;
        }
        if ($tag === 'br') {
            $salida .= '<br>';
            continue;
        }

        $interior = foro_flarum_nodo_a_html($hijo);

        switch ($tag) {
            case 'p':
                $salida .= "<p>{$interior}</p>";
                break;
            case 'B':
            case 'STRONG':
                $salida .= "<strong>{$interior}</strong>";
                break;
            case 'I':
            case 'EM':
                $salida .= "<em>{$interior}</em>";
                break;
            case 'H1':
            case 'H2':
                $salida .= "<h4>{$interior}</h4>";
                break;
            case 'H3':
                $salida .= "<h5>{$interior}</h5>";
                break;
            case 'HR':
                $salida .= '<hr>';
                break;
            case 'URL':
                $href = $hijo->getAttribute('url');
                $salida .= '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener">' . $interior . '</a>';
                break;
            case 'IMG':
                $src = $hijo->getAttribute('src');
                $salida .= '<img src="' . htmlspecialchars($src) . '" alt="" style="max-width:100%;border-radius:8px;">';
                break;
            case 'LIST':
                $etiqueta = $hijo->getAttribute('type') === 'decimal' ? 'ol' : 'ul';
                $salida .= "<{$etiqueta}>{$interior}</{$etiqueta}>";
                break;
            case 'LI':
                $salida .= "<li>{$interior}</li>";
                break;
            case 'QUOTE':
                $salida .= "<blockquote>{$interior}</blockquote>";
                break;
            case 'CODE':
                $salida .= "<code>{$interior}</code>";
                break;
            case 'E':
                $salida .= $interior; // emoticon (ej. ":)"): se deja el texto tal cual
                break;
            case 'POSTMENTION':
            case 'USERMENTION':
                $nombre = $hijo->getAttribute('displayname') ?: $hijo->getAttribute('username');
                $salida .= '<strong>@' . htmlspecialchars($nombre) . '</strong>';
                break;
            case 'SPOILER':
                $salida .= '<span class="pf-forum-spoiler">' . $interior . '</span>';
                break;
            default:
                // r, t, o cualquier tag no reconocido: se conserva solo el contenido.
                $salida .= $interior;
        }
    }
    return $salida;
}

/**
 * Convierte el HTML que manda el editor de texto enriquecido a HTML seguro
 * para guardar/mostrar — primera vez que este proyecto acepta HTML real de
 * un usuario normal (antes, todo post nuevo era texto plano, escapado por
 * completo vía convertir_contenido_foro()). No hay librería de saneamiento
 * en el proyecto (ni gestor de dependencias) — se construye a mano con
 * DOMDocument y una lista blanca estricta de tags/atributos/esquemas de URL.
 *
 * Debe ser IDEMPOTENTE (sanitizar dos veces da el mismo resultado) — se usa
 * tanto al guardar (crear/editar) como, por defensa en profundidad, al
 * mostrar, ya que este es el primer punto de entrada de HTML de usuario en
 * todo el proyecto.
 */
function foro_sanitizar_html_editor(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    // Límite de tamaño defensivo antes de parsear (evita abuso).
    if (mb_strlen($html) > 50000) {
        $html = mb_substr($html, 0, 50000);
    }

    $tagsPermitidos = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'img', 'span'];
    $tagsAEliminar = ['script', 'style', 'template', 'svg', 'math', 'iframe', 'object', 'embed', 'form', 'input', 'button'];
    $esquemasEnlace = ['http', 'https', 'mailto'];
    $esquemasImagen = ['http', 'https'];
    $profundidadMaxima = 40;

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    // El PI de encoding fuerza UTF-8 — sin esto DOMDocument asume Latin-1 y
    // rompe acentos/emoji. NOIMPLIED+NODEFDTD evita que agregue html/head/body.
    $cargo = $dom->loadHTML(
        '<?xml encoding="utf-8"?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    if (!$cargo) {
        return '';
    }

    $raiz = null;
    foreach ($dom->childNodes as $nodo) {
        if ($nodo->nodeType === XML_ELEMENT_NODE) {
            $raiz = $nodo;
            break;
        }
    }
    if (!$raiz) {
        return '';
    }

    $limpiarNodo = function (DOMNode $nodo, int $profundidad) use (&$limpiarNodo, $tagsPermitidos, $tagsAEliminar, $esquemasEnlace, $esquemasImagen, $profundidadMaxima): void {
        // Copia de la lista de hijos: se modifica el árbol mientras se recorre.
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            if ($hijo->nodeType === XML_TEXT_NODE) {
                continue;
            }
            // Comentarios (ahí se esconden los trucos clásicos de mXSS) y
            // cualquier otro tipo de nodo que no sea texto/elemento: fuera.
            if ($hijo->nodeType !== XML_ELEMENT_NODE) {
                $nodo->removeChild($hijo);
                continue;
            }

            $tag = strtolower($hijo->nodeName);

            if (in_array($tag, $tagsAEliminar, true) || $hijo->namespaceURI !== null || $profundidad >= $profundidadMaxima) {
                $nodo->removeChild($hijo);
                continue;
            }

            $limpiarNodo($hijo, $profundidad + 1);

            if (!in_array($tag, $tagsPermitidos, true)) {
                // Tag no reconocido: se desenvuelve (se conservan los hijos,
                // se quita solo la etiqueta) — nunca se borra el subárbol
                // completo, o el editor pierde párrafos enteros por un <div> de más.
                while ($hijo->firstChild) {
                    $nodo->insertBefore($hijo->firstChild, $hijo);
                }
                $nodo->removeChild($hijo);
                continue;
            }

            foreach (iterator_to_array($hijo->attributes) as $atributo) {
                $nombre = strtolower($atributo->nodeName);
                $mantener = false;
                if ($tag === 'a' && $nombre === 'href') {
                    $mantener = foro_url_esquema_permitido($atributo->nodeValue, $esquemasEnlace);
                } elseif ($tag === 'img' && $nombre === 'src') {
                    $mantener = foro_url_esquema_permitido($atributo->nodeValue, $esquemasImagen);
                }
                if (!$mantener) {
                    $hijo->removeAttribute($atributo->nodeName);
                }
            }
            if ($tag === 'a' && $hijo->hasAttribute('href')) {
                $hijo->setAttribute('target', '_blank');
                $hijo->setAttribute('rel', 'noopener nofollow ugc');
            }
        }
    };

    $limpiarNodo($raiz, 0);

    $salida = '';
    foreach ($raiz->childNodes as $hijo) {
        $salida .= $dom->saveHTML($hijo);
    }
    return trim($salida);
}

/**
 * Valida que una URL (href/src) tenga un esquema explícito dentro de la
 * lista permitida — rechaza `javascript:`, `data:`, URLs relativas sin
 * esquema, y cualquier cosa ambigua. Se prefiere ser estricto: en el editor
 * del foro no hay subida de imágenes propia, así que una URL relativa no
 * tiene un destino válido de todos modos.
 */
function foro_url_esquema_permitido(string $url, array $esquemasPermitidos): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    $partes = parse_url($url);
    if ($partes === false || !isset($partes['scheme'])) {
        return false;
    }
    return in_array(strtolower($partes['scheme']), $esquemasPermitidos, true);
}

/**
 * Un editor de texto enriquecido vacío (Quill incluido) no manda una cadena
 * vacía — manda algo como "<p><br></p>". Esto detecta ese caso (sin texto
 * real ni imagen) para poder rechazarlo igual que un post realmente vacío.
 */
function foro_contenido_html_vacio(string $html): bool
{
    return trim(strip_tags($html)) === '' && stripos($html, '<img') === false;
}

function foro_categorias(): array
{
    global $conn;
    return $conn->query(
        'SELECT c.*, (SELECT COUNT(*) FROM foro_temas t WHERE t.categoria_id = c.id) AS temas_count
         FROM foro_categorias c ORDER BY orden ASC, nombre ASC'
    )->fetch_all(MYSQLI_ASSOC);
}

function foro_categoria_por_slug(string $slug): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM foro_categorias WHERE slug = ? LIMIT 1');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Categorías organizadas en árbol (categoría → subcategoría → sub-subcategoría,
 * hasta 3 niveles — el tope se valida al crear/editar, no aquí). Cada nodo trae
 * 'hijos' con sus categorías directas.
 */
function foro_categorias_arbol(): array
{
    global $conn;
    $todas = $conn->query(
        'SELECT c.*, (SELECT COUNT(*) FROM foro_temas t WHERE t.categoria_id = c.id) AS temas_count
         FROM foro_categorias c ORDER BY orden ASC, nombre ASC'
    )->fetch_all(MYSQLI_ASSOC);

    $porPadre = [];
    foreach ($todas as $c) {
        $padreId = $c['parent_id'] !== null ? (int) $c['parent_id'] : 0;
        $porPadre[$padreId][] = $c;
    }

    $construir = function (int $padreId) use (&$construir, $porPadre): array {
        $resultado = [];
        foreach ($porPadre[$padreId] ?? [] as $c) {
            $c['hijos'] = $construir((int) $c['id']);
            $resultado[] = $c;
        }
        return $resultado;
    };

    return $construir(0);
}

/**
 * Aplana un árbol de foro_categorias_arbol() en una lista con 'nivel' (0, 1, 2)
 * — para pintar un <select> indentado o un listado tipo árbol.
 */
function foro_categorias_arbol_plano(array $arbol, int $nivel = 0): array
{
    $resultado = [];
    foreach ($arbol as $nodo) {
        $hijos = $nodo['hijos'] ?? [];
        unset($nodo['hijos']);
        $nodo['nivel'] = $nivel;
        $resultado[] = $nodo;
        $resultado = array_merge($resultado, foro_categorias_arbol_plano($hijos, $nivel + 1));
    }
    return $resultado;
}

/**
 * Nivel de una categoría YA existente (0 = raíz, 1 = subcategoría, 2 =
 * sub-subcategoría — el máximo permitido). Sirve para validar que un nuevo
 * hijo no exceda los 3 niveles: rechazar si foro_categoria_nivel($padreId) >= 2.
 */
function foro_categoria_nivel(int $categoriaId): int
{
    global $conn;
    $nivel = 0;
    $actual = $categoriaId;
    $visitados = [];
    while ($actual !== null && !isset($visitados[$actual])) {
        $visitados[$actual] = true;
        $stmt = $conn->prepare('SELECT parent_id FROM foro_categorias WHERE id = ?');
        $stmt->bind_param('i', $actual);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$fila || $fila['parent_id'] === null) {
            break;
        }
        $actual = (int) $fila['parent_id'];
        $nivel++;
    }
    return $nivel;
}

/**
 * IDs de todas las categorías descendientes (hijas, nietas...) de una
 * categoría — para no poder elegirla a sí misma ni a sus propios
 * descendientes como "categoría padre" al editarla (evita ciclos).
 */
function foro_categoria_descendientes(int $categoriaId): array
{
    global $conn;
    $resultado = [];
    $pendientes = [$categoriaId];
    while ($pendientes) {
        $actual = array_shift($pendientes);
        $stmt = $conn->prepare('SELECT id FROM foro_categorias WHERE parent_id = ?');
        $stmt->bind_param('i', $actual);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $hijo) {
            $hijoId = (int) $hijo['id'];
            $resultado[] = $hijoId;
            $pendientes[] = $hijoId;
        }
        $stmt->close();
    }
    return $resultado;
}

/**
 * Ruta desde la raíz hasta esta categoría (para breadcrumbs): [raíz, ...,
 * $categoriaId]. Cada elemento trae al menos id/nombre/slug.
 */
function foro_categoria_ruta(int $categoriaId): array
{
    global $conn;
    $ruta = [];
    $actual = $categoriaId;
    $visitados = [];
    while ($actual !== null && !isset($visitados[$actual])) {
        $visitados[$actual] = true;
        $stmt = $conn->prepare('SELECT id, nombre, slug, parent_id FROM foro_categorias WHERE id = ?');
        $stmt->bind_param('i', $actual);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$fila) {
            break;
        }
        array_unshift($ruta, $fila);
        $actual = $fila['parent_id'] !== null ? (int) $fila['parent_id'] : null;
    }
    return $ruta;
}

function foro_slugify(string $titulo): string
{
    $slug = strtolower(trim($titulo));
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug) ?: $slug;
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'tema';
}

function foro_slug_unico(string $base): string
{
    global $conn;
    $slug = $base;
    $sufijo = 1;
    while (true) {
        $stmt = $conn->prepare('SELECT id FROM foro_temas WHERE slug = ? LIMIT 1');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) {
            return $slug;
        }
        $sufijo++;
        $slug = $base . '-' . $sufijo;
    }
}

/**
 * Detecta @usuario en el contenido y devuelve los usuarios_perfil.id válidos
 * (excluyendo al propio autor, que no necesita notificarse a sí mismo).
 */
function foro_detectar_menciones(string $contenido, int $autorId): array
{
    global $conn;
    if (!preg_match_all('/@([a-zA-Z0-9_]+)/', $contenido, $m)) {
        return [];
    }
    $usernames = array_unique($m[1]);
    if (!$usernames) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));
    $tipos = str_repeat('s', count($usernames));
    $stmt = $conn->prepare("SELECT id FROM usuarios_perfil WHERE username_cache IN ($placeholders)");
    $stmt->bind_param($tipos, ...$usernames);
    $stmt->execute();
    $ids = array_map(fn($r) => (int) $r['id'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
    return array_values(array_diff($ids, [$autorId]));
}

function foro_crear_notificacion(int $usuarioId, string $tipo, int $temaId, ?int $respuestaId, int $actorId): void
{
    global $conn;
    if ($usuarioId === $actorId) {
        return;
    }
    $stmt = $conn->prepare(
        'INSERT INTO foro_notificaciones (usuario_id, tipo, tema_id, respuesta_id, actor_usuario_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isiii', $usuarioId, $tipo, $temaId, $respuestaId, $actorId);
    $stmt->execute();
    $stmt->close();
}

function foro_notificaciones_no_leidas(int $usuarioId): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM foro_notificaciones WHERE usuario_id = ? AND leida = 0');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
}

function foro_usuario_dio_like(int $usuarioId, ?int $temaId, ?int $respuestaId): bool
{
    global $conn;
    if ($temaId !== null) {
        $stmt = $conn->prepare('SELECT id FROM foro_likes WHERE usuario_id = ? AND tema_id = ? LIMIT 1');
        $stmt->bind_param('ii', $usuarioId, $temaId);
    } else {
        $stmt = $conn->prepare('SELECT id FROM foro_likes WHERE usuario_id = ? AND respuesta_id = ? LIMIT 1');
        $stmt->bind_param('ii', $usuarioId, $respuestaId);
    }
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $existe;
}

function foro_contar_likes(?int $temaId, ?int $respuestaId): int
{
    global $conn;
    if ($temaId !== null) {
        $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM foro_likes WHERE tema_id = ?');
        $stmt->bind_param('i', $temaId);
    } else {
        $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM foro_likes WHERE respuesta_id = ?');
        $stmt->bind_param('i', $respuestaId);
    }
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
}

function foro_tiempo_relativo(string $fecha): string
{
    $diff = time() - strtotime($fecha);
    if ($diff < 60) return 'hace un momento';
    if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' h';
    if ($diff < 2592000) return 'hace ' . floor($diff / 86400) . ' días';
    return date('d/m/Y', strtotime($fecha));
}
