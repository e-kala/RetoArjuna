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
