<?php
// Funciones compartidas por todas las páginas del foro propio.
require_once __DIR__ . '/../../backend/auth.php';

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
        'SELECT c.*, (SELECT COUNT(*) FROM foro_tema_etiquetas te WHERE te.categoria_id = c.id) AS temas_count
         FROM foro_categorias c ORDER BY orden ASC, nombre ASC'
    )->fetch_all(MYSQLI_ASSOC);
}

/**
 * Fragmento SQL reutilizable para filtrar foro_temas por visibilidad — un
 * tema es visible si es público, si el usuario actual es su autor, si está
 * compartido específicamente con él, o si el usuario actual es admin
 * (moderación/seguridad — ver foro_tema_es_visible(), misma regla). Usar
 * junto con foro_visibilidad_binds() para los 4 parámetros que espera.
 */
function foro_visibilidad_sql(string $aliasTema = 't'): string
{
    // "oculto" (2026-08-31) es un candado de moderación aparte de
    // visibilidad/privacidad de arriba — un admin lo oculta del foro por
    // completo, ni siquiera su propio autor lo ve (a diferencia de
    // privado/compartido, que sí respetan al autor). Solo un admin lo pasa
    // por alto, mismo bind de $esAdmin ya usado arriba, reutilizado aquí.
    return "({$aliasTema}.visibilidad = 'publico' OR {$aliasTema}.usuario_id = ? OR ({$aliasTema}.visibilidad = 'compartido' AND {$aliasTema}.compartido_con_usuario_id = ?) OR ?) AND ({$aliasTema}.oculto = 0 OR ?)";
}

/** Valores a enlazar (en orden) para foro_visibilidad_sql(), tipos 'iiii'. */
function foro_visibilidad_binds(?array $usuarioActual): array
{
    $uid = $usuarioActual ? (int) $usuarioActual['id'] : 0;
    $esAdmin = ($usuarioActual && $usuarioActual['rol'] === 'admin') ? 1 : 0;
    return [$uid, $uid, $esAdmin, $esAdmin];
}

/**
 * Filtro de "oculto" para las páginas normales del foro (index/categoría/
 * etiqueta/curso/evento/búsqueda) — a diferencia del panel/admin/foro.php
 * (que es una herramienta de moderación aparte), aquí es solo para que un
 * admin, navegando el foro como cualquiera, pueda separar lo oculto de lo
 * publicado en vez de verlo todo mezclado (que es lo que ya hacía
 * foro_visibilidad_sql() para admins desde antes). Nunca aplica a un no-admin
 * — para ellos foro_visibilidad_sql() ya excluye lo oculto por completo, así
 * que este filtro no tendría nada que filtrar.
 */
function foro_oculto_filtro_admin(?array $usuarioActual): string
{
    if (!$usuarioActual || $usuarioActual['rol'] !== 'admin') {
        return '';
    }
    $valor = $_GET['oculto'] ?? '';
    return in_array($valor, ['ocultos', 'publicados'], true) ? $valor : '';
}

/**
 * Fragmento SQL (para concatenar, no un bind) del filtro de arriba. Se
 * arma como texto y no como parámetro enlazado porque el valor ya salió de
 * una lista blanca fija (nunca texto crudo de $_GET) — así no hay que tocar
 * el bind_param de cada página que ya usa foro_visibilidad_sql() con su
 * propia lista fija de tipos/parámetros.
 */
function foro_oculto_filtro_sql(string $filtro, string $aliasTema = 't'): string
{
    if ($filtro === 'ocultos') {
        return " AND {$aliasTema}.oculto = 1";
    }
    if ($filtro === 'publicados') {
        return " AND {$aliasTema}.oculto = 0";
    }
    return '';
}

/**
 * true si $usuarioActual puede ver este tema — público siempre; privado o
 * compartido solo para el autor, el destinatario elegido, o un admin (que
 * puede ver cualquier tema por motivos de moderación/seguridad, aunque no
 * sea el destinatario — el autor recibe aviso de esto al elegir la opción).
 * "Oculto" es un candado de moderación distinto y más fuerte: ni siquiera el
 * autor ve su propio tema oculto, solo un admin — por eso se revisa antes
 * que cualquier otra cosa, sin las excepciones de privado/compartido de abajo.
 */
function foro_tema_es_visible(array $tema, ?array $usuarioActual): bool
{
    $esAdmin = $usuarioActual && $usuarioActual['rol'] === 'admin';
    if (!empty($tema['oculto']) && !$esAdmin) {
        return false;
    }

    $vis = $tema['visibilidad'] ?? 'publico';
    if ($vis === 'publico') {
        return true;
    }
    if (!$usuarioActual) {
        return false;
    }
    if ($esAdmin || (int) $usuarioActual['id'] === (int) $tema['usuario_id']) {
        return true;
    }
    return $vis === 'compartido' && (int) ($tema['compartido_con_usuario_id'] ?? 0) === (int) $usuarioActual['id'];
}

/**
 * Única fuente de verdad de las opciones de orden para listados de temas —
 * la usan index.php, etiqueta.php, curso.php y evento.php (antes cada una
 * traía su propia copia parcial del arreglo, y curso.php/evento.php ni
 * siquiera tenían opción de orden). Agregar una opción nueva aquí la
 * habilita automáticamente en las 4 páginas via inc/orden_selector.php.
 */
function foro_ordenes_disponibles(): array
{
    return [
        'recientes' => ['sql' => 't.ultima_respuesta_at DESC, t.created_at DESC', 'etiqueta' => 'Actividad reciente'],
        'respondidas' => ['sql' => 't.respuestas_count DESC, t.ultima_respuesta_at DESC', 'etiqueta' => 'Más respondidas'],
        'vistas' => ['sql' => 't.vistas DESC, t.ultima_respuesta_at DESC', 'etiqueta' => 'Más vistas'],
        'fecha_desc' => ['sql' => 't.created_at DESC', 'etiqueta' => 'Fecha: más nuevos primero'],
        'fecha_asc' => ['sql' => 't.created_at ASC', 'etiqueta' => 'Fecha: más antiguos primero'],
    ];
}

/** Clave de orden validada contra foro_ordenes_disponibles() — 'recientes' si no existe. */
function foro_orden_valido(?string $orden): string
{
    return isset(foro_ordenes_disponibles()[$orden]) ? $orden : 'recientes';
}

/** Fragmento SQL (sin "ORDER BY", sin el fijado DESC que cada página antepone) para una clave de orden ya validada. */
function foro_orden_sql(string $orden): string
{
    return foro_ordenes_disponibles()[foro_orden_valido($orden)]['sql'];
}

/**
 * Resuelve un usuario por username o correo (a diferencia de
 * foro_detectar_menciones(), que solo resuelve por username) — usado para
 * elegir con quién se comparte un tema privado.
 */
function foro_resolver_usuario_por_handle(string $handle): ?int
{
    global $conn;
    $handle = trim($handle);
    if ($handle === '') {
        return null;
    }
    $stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE username_cache = ? OR email_cache = ? LIMIT 1');
    $stmt->bind_param('ss', $handle, $handle);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ? (int) $fila['id'] : null;
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
 *
 * Desde que `foro_categorias` pasó de ser la categoría única de un tema a ser
 * el vocabulario de ETIQUETAS (relación muchos-a-muchos vía
 * `foro_tema_etiquetas`), esta función sigue sirviendo para pintar el árbol
 * admin (`foro_categoria_form.php`) y, aplanada, la lista de etiquetas
 * disponibles (sidebar, checkboxes de nuevo_tema.php). `$soloAprobadas=true`
 * (default) oculta las etiquetas propuestas por usuarios que un admin aún no
 * aprobó — pásalo en `false` solo en contextos de administración donde
 * también hace falta verlas (ej. moderación, edición admin de un tema).
 */
function foro_categorias_arbol(?array $usuarioActual = null, bool $soloAprobadas = true): array
{
    global $conn;
    $visSql = foro_visibilidad_sql();
    [$uid1, $uid2, $esAdmin, $esAdmin2] = foro_visibilidad_binds($usuarioActual);
    $filtroAprobada = $soloAprobadas ? 'AND c.aprobada = 1' : '';
    $stmt = $conn->prepare(
        "SELECT c.*, cp.username_cache AS creado_por_username,
                (SELECT COUNT(*) FROM foro_tema_etiquetas te JOIN foro_temas t ON t.id = te.tema_id WHERE te.categoria_id = c.id AND $visSql) AS temas_count
         FROM foro_categorias c LEFT JOIN usuarios_perfil cp ON cp.id = c.creado_por
         WHERE 1=1 $filtroAprobada ORDER BY orden ASC, nombre ASC"
    );
    $stmt->bind_param('iiii', $uid1, $uid2, $esAdmin, $esAdmin2);
    $stmt->execute();
    $todas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

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

/**
 * Etiquetas de un tema (join foro_tema_etiquetas -> foro_categorias). Por
 * defecto solo trae las aprobadas — pásalo en `false` para la propia página
 * del tema (su autor puede ver sus etiquetas pendientes) o en contextos admin.
 */
function foro_tema_etiquetas(int $temaId, bool $soloAprobadas = true): array
{
    global $conn;
    $filtro = $soloAprobadas ? 'AND c.aprobada = 1' : '';
    $stmt = $conn->prepare(
        "SELECT c.id, c.nombre, c.slug, c.aprobada
         FROM foro_tema_etiquetas te JOIN foro_categorias c ON c.id = te.categoria_id
         WHERE te.tema_id = ? $filtro ORDER BY c.nombre ASC"
    );
    $stmt->bind_param('i', $temaId);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/**
 * Fragmento SQL reutilizable para listados (index.php, categoria.php,
 * buscar.php, etc.) — un texto ya armado con las etiquetas aprobadas de cada
 * tema, para no repetir el JOIN en cada archivo. Usar como columna del SELECT
 * principal (alias `$aliasTema` = alias de foro_temas en esa consulta).
 */
function foro_etiquetas_resumen_sql(string $aliasTema = 't'): string
{
    return "(SELECT GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', ')
              FROM foro_tema_etiquetas te JOIN foro_categorias c ON c.id = te.categoria_id
              WHERE te.tema_id = {$aliasTema}.id AND c.aprobada = 1)";
}

/**
 * Busca una etiqueta por nombre (sin distinguir mayúsculas/minúsculas). Si no
 * existe, crea una nueva con aprobada=0 (pendiente de revisión de un admin —
 * ver panel/admin/foro.php (pestaña "Etiquetas")) y devuelve su id. Usada tanto al crear
 * un tema como al reasignar sus etiquetas desde la edición admin.
 */
function foro_resolver_o_crear_etiqueta(string $nombre, int $usuarioId): ?int
{
    global $conn;
    $nombre = trim($nombre);
    if ($nombre === '' || mb_strlen($nombre) > 100) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id FROM foro_categorias WHERE nombre = ? LIMIT 1');
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($fila) {
        return (int) $fila['id'];
    }

    $slugBase = foro_slugify($nombre);
    $slug = $slugBase;
    $sufijo = 1;
    while (true) {
        $stmt = $conn->prepare('SELECT id FROM foro_categorias WHERE slug = ? LIMIT 1');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) {
            break;
        }
        $sufijo++;
        $slug = $slugBase . '-' . $sufijo;
    }

    $stmt = $conn->prepare(
        'INSERT INTO foro_categorias (nombre, slug, aprobada, creado_por, orden) VALUES (?, ?, 0, ?, 0)'
    );
    $stmt->bind_param('ssi', $nombre, $slug, $usuarioId);
    $stmt->execute();
    $nuevoId = $stmt->insert_id;
    $stmt->close();
    return $nuevoId;
}

function foro_slugify(string $titulo): string
{
    $slug = strtolower(trim($titulo));
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug) ?: $slug;
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'tema';
}

/**
 * "Categoría libre" — tercera taxonomía de un tema, aparte de curso/evento y
 * de las etiquetas (foro_categorias): cualquier usuario elige una existente
 * o escribe una nueva al publicar, SIN aprobación de admin (a diferencia de
 * foro_resolver_o_crear_etiqueta()) — una sola por tema, no N:M. Un admin
 * puede moderar/renombrar/eliminar después desde
 * panel/admin/foro.php (pestaña "Categorías libres") si hace falta limpiar duplicados o
 * spam, pero nada bloquea su uso inmediato al crearla.
 */
function foro_resolver_o_crear_categoria_libre(string $nombre, int $usuarioId): ?int
{
    global $conn;
    $nombre = trim($nombre);
    if ($nombre === '' || mb_strlen($nombre) > 100) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id FROM foro_categorias_libres WHERE nombre = ? LIMIT 1');
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($fila) {
        return (int) $fila['id'];
    }

    $slugBase = foro_slugify($nombre);
    $slug = $slugBase;
    $sufijo = 1;
    while (true) {
        $stmt = $conn->prepare('SELECT id FROM foro_categorias_libres WHERE slug = ? LIMIT 1');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) {
            break;
        }
        $sufijo++;
        $slug = $slugBase . '-' . $sufijo;
    }

    $stmt = $conn->prepare('INSERT INTO foro_categorias_libres (nombre, slug, creado_por) VALUES (?, ?, ?)');
    $stmt->bind_param('ssi', $nombre, $slug, $usuarioId);
    $stmt->execute();
    $nuevoId = $stmt->insert_id;
    $stmt->close();
    return $nuevoId;
}

/** Todas las categorías libres, con el número de temas que las usan — para el selector de nuevo_tema.php y la barra lateral. */
function foro_categorias_libres_todas(): array
{
    global $conn;
    $sql = "SELECT cl.id, cl.nombre, cl.slug, cl.created_at, COUNT(t.id) AS temas_count
            FROM foro_categorias_libres cl
            LEFT JOIN foro_temas t ON t.categoria_libre_id = cl.id
            GROUP BY cl.id, cl.nombre, cl.slug, cl.created_at
            ORDER BY cl.nombre ASC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function foro_categoria_libre_por_slug(string $slug): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT id, nombre, slug FROM foro_categorias_libres WHERE slug = ? LIMIT 1');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
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

// Escribe en la tabla unificada `notificaciones` (backend/notificaciones.php)
// en vez de foro_notificaciones (que ya no se usa) — mismos 3 call sites de
// siempre (responder.php, crear_tema.php), sin cambios en ellos, porque esta
// función resuelve aquí mismo el título/enlace que antes se armaba vía JOIN
// al leer en foro/notificaciones.php.
function foro_crear_notificacion(int $usuarioId, string $tipo, int $temaId, ?int $respuestaId, int $actorId): void
{
    global $conn;
    if ($usuarioId === $actorId) {
        return;
    }
    $stmt = $conn->prepare(
        'SELECT t.titulo AS tema_titulo, a.username_cache AS actor_username
         FROM foro_temas t, usuarios_perfil a
         WHERE t.id = ? AND a.id = ?'
    );
    $stmt->bind_param('ii', $temaId, $actorId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) {
        return;
    }

    $titulo = $tipo === 'mencion'
        ? $fila['actor_username'] . ' te mencionó en «' . $fila['tema_titulo'] . '»'
        : $fila['actor_username'] . ' respondió tu tema «' . $fila['tema_titulo'] . '»';
    $enlace = 'foro/tema.php?id=' . $temaId . ($respuestaId ? '#respuesta-' . $respuestaId : '');

    notificacion_crear($usuarioId, $tipo, $titulo, null, $enlace, $actorId);
}

function foro_notificaciones_no_leidas(int $usuarioId): int
{
    return notificaciones_no_leidas($usuarioId);
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

/** Lista de quién dio like a un tema o respuesta, más reciente primero. */
function foro_listar_likes(?int $temaId, ?int $respuestaId): array
{
    global $conn;
    if ($temaId !== null) {
        $stmt = $conn->prepare(
            'SELECT u.username_cache, u.avatar_cache, l.created_at
             FROM foro_likes l JOIN usuarios_perfil u ON u.id = l.usuario_id
             WHERE l.tema_id = ? ORDER BY l.created_at DESC'
        );
        $stmt->bind_param('i', $temaId);
    } else {
        $stmt = $conn->prepare(
            'SELECT u.username_cache, u.avatar_cache, l.created_at
             FROM foro_likes l JOIN usuarios_perfil u ON u.id = l.usuario_id
             WHERE l.respuesta_id = ? ORDER BY l.created_at DESC'
        );
        $stmt->bind_param('i', $respuestaId);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/**
 * Borra definitivamente las respuestas que llevan más de 15 días en la
 * papelera (`eliminado_en` no nulo — ver backend/moderar.php) sin que un
 * admin las haya revisado. El proyecto no tiene ninguna tarea programada
 * (cron) real — se llama desde foro/index.php una vez por sesión de
 * navegador, así se dispara con el tráfico normal del foro en vez de
 * depender de que un admin entre a moderar.
 */
function foro_purgar_papelera_vencida(): void
{
    global $conn;
    $conn->query(
        "DELETE FROM foro_respuestas WHERE eliminado_en IS NOT NULL AND eliminado_en < NOW() - INTERVAL 15 DAY"
    );
}

/** Cursos activos, para el selector de reasignación admin en tema.php. */
function foro_lista_cursos_activos(): array
{
    global $conn;
    return $conn->query('SELECT id, titulo FROM cursos WHERE activo = 1 ORDER BY titulo ASC')->fetch_all(MYSQLI_ASSOC);
}

/** Eventos activos, para el selector de reasignación admin en tema.php. */
function foro_lista_eventos_activos(): array
{
    global $conn;
    return $conn->query('SELECT id, titulo FROM eventos WHERE activo = 1 ORDER BY titulo ASC')->fetch_all(MYSQLI_ASSOC);
}

/** Lecciones de un curso o evento (mutuamente excluyentes), para el selector de lección. */
function foro_lecciones_de(?int $cursoId, ?int $eventoId): array
{
    global $conn;
    if ($cursoId) {
        $stmt = $conn->prepare('SELECT id, titulo FROM lecciones WHERE curso_id = ? ORDER BY orden');
        $stmt->bind_param('i', $cursoId);
    } elseif ($eventoId) {
        $stmt = $conn->prepare('SELECT id, titulo FROM lecciones WHERE evento_id = ? ORDER BY orden');
        $stmt->bind_param('i', $eventoId);
    } else {
        return [];
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
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
