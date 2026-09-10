<?php
// Embeds de curso/evento para landing pages (panel/admin/landing_pages.php):
// el admin sube HTML ya diseñado (por ejemplo, generado con ayuda de un
// asistente de IA por alguien que no programa) y ese HTML puede traer un
// placeholder de texto simple como "[curso:mi-slug]" o "[evento:mi-slug]" en
// cualquier parte — content/landing.php lo reemplaza en el servidor antes de
// mostrar la página. Un placeholder de texto plano (no un comentario HTML)
// sobrevive casi cualquier "limpieza"/reformateo que un generador de HTML le
// haga al contenido, a diferencia de un <!-- comentario -->.
//
// El bloque que se inserta es un temario: el título de cada LECCIÓN
// publicada del curso/evento, con un resumen corto de su contenido —
// deliberadamente SIN filtrar por "vista_previa" (el usuario confirmó que
// quiere ver un adelanto de todo el temario en la landing, no solo de las
// lecciones gratuitas) — sí se excluyen los borradores (estado_publicacion
// <> 'publicado'), porque esos ni siquiera están terminados de escribir.

// Extrae {titulo, resumen} de las lecciones publicadas de un curso/evento —
// strip_tags() sobre contenido_texto (HTML de Quill) porque aquí solo
// interesa un resumen de texto plano, no su formato.
function extraer_resumen_lecciones(mysqli $conn, string $tipo, int $id, int $longitudResumen = 160): array
{
    $columna = $tipo === 'evento' ? 'evento_id' : 'curso_id';
    $stmt = $conn->prepare(
        "SELECT titulo, contenido_texto FROM lecciones WHERE $columna = ? AND estado_publicacion = 'publicado' ORDER BY orden"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $resultado = [];
    foreach ($filas as $leccion) {
        $titulo = trim((string) $leccion['titulo']);
        if ($titulo === '') {
            continue;
        }
        $textoPlano = trim(preg_replace('/\s+/', ' ', strip_tags((string) $leccion['contenido_texto'])));
        $resumen = mb_strlen($textoPlano) > $longitudResumen
            ? mb_substr($textoPlano, 0, $longitudResumen) . '…'
            : $textoPlano;
        $resultado[] = ['titulo' => $titulo, 'resumen' => $resumen];
    }
    return $resultado;
}

// Caja de diagnóstico visible SOLO para admins logueados — un placeholder
// que no encuentra nada que mostrar simplemente desaparece para cualquier
// otra visita (nunca se quiere texto crudo "[evento:...]" ni un hueco raro
// en una landing pública), pero eso deja al admin sin ninguna pista de por
// qué. Con sesión de admin, en su lugar se ve exactamente qué se buscó y
// por qué no hubo resultado — nunca llega a producción para un visitante
// normal porque current_user() exige la cookie de sesión real.
function pf_embed_caja_debug(string $codigo, string $motivo): string
{
    if (!function_exists('current_user')) {
        return '';
    }
    $usuario = current_user();
    if (!$usuario || ($usuario['rol'] ?? '') !== 'admin') {
        return '';
    }
    return '<div style="border:2px dashed #c0392b;background:#fff5f5;color:#7a1f1f;'
        . 'padding:12px 16px;border-radius:8px;margin:12px 0;font-family:monospace;font-size:13px;">'
        . '<strong>[Solo admin] Código ' . htmlspecialchars($codigo) . ' no se pudo mostrar:</strong><br>'
        . htmlspecialchars($motivo)
        . '</div>';
}

// Arma el HTML final para un placeholder "[curso:slug]"/"[evento:slug]" —
// cadena vacía si el curso/evento no existe, está inactivo, o no tiene
// ninguna lección publicada (nada útil que mostrar) — salvo para un admin
// logueado, que ve la caja de diagnóstico de arriba en su lugar.
function renderizar_embed_curso_evento(mysqli $conn, string $tipo, string $slug): string
{
    $tabla = $tipo === 'evento' ? 'eventos' : 'cursos';
    $accion = $tipo === 'evento' ? 'evento' : 'curso';
    $codigo = '[' . $tipo . ':' . $slug . ']';

    // video_grabado_url solo existe en `eventos` — un evento de sesión única
    // normalmente no tiene ninguna lección (su grabación vive aquí, no en
    // una lección), así que sirve como señal de "esto sí tiene algo útil
    // que mostrar" aunque $items venga vacío (ver más abajo).
    $columnas = $tipo === 'evento' ? 'id, titulo, slug, activo, video_grabado_url' : 'id, titulo, slug, activo';
    $stmt = $conn->prepare("SELECT $columnas FROM $tabla WHERE slug = ?");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) {
        return pf_embed_caja_debug($codigo, "No existe ningún $accion con slug \"$slug\". Revisa el campo Slug en su editor.");
    }
    if ((int) $fila['activo'] !== 1) {
        return pf_embed_caja_debug($codigo, ucfirst($accion) . " \"{$fila['titulo']}\" existe pero está INACTIVO — actívalo en su editor para que se muestre.");
    }

    $items = extraer_resumen_lecciones($conn, $tipo, (int) $fila['id']);
    $tieneVideoGrabado = $tipo === 'evento' && !empty($fila['video_grabado_url']);
    // Sin lecciones Y sin grabación no hay nada útil que mostrar (curso/evento
    // recién creado, todavía sin contenido). Con grabación pero sin lecciones
    // (típico de un evento de sesión única, que no necesita "temario") sí se
    // muestra — antes esto también desaparecía del embed, ocultando el único
    // enlace hacia su video en la landing.
    if (!$items && !$tieneVideoGrabado) {
        return pf_embed_caja_debug($codigo, ucfirst($accion) . " \"{$fila['titulo']}\" está activo pero no tiene ninguna lección PUBLICADA" . ($tipo === 'evento' ? ' ni una grabación (video_grabado_url)' : '') . " (revisa panel/admin/lecciones.php — pueden estar todas en borrador, o no tener ninguna lección creada todavía).");
    }

    $enlace = BASE_URL . '/index.php?action=' . $accion . '&slug=' . urlencode((string) $fila['slug']);
    $html = '<div class="pf-embed-curso-evento">';
    $html .= '<h3 class="pf-embed-curso-evento-titulo">' . htmlspecialchars((string) $fila['titulo']) . '</h3>';
    if ($items) {
        // Acordeón de Bootstrap (data-bs-toggle="collapse") en vez del <details>
        // nativo que usa el editor — el bootstrap.bundle.js que la plataforma ya
        // carga en toda página se encarga de abrir/cerrar, no hace falta JS
        // propio. Los ids deben ser únicos en la página aunque haya más de un
        // embed en la misma landing, por eso se arman con el tipo+id de la fila.
        $idAcordeon = 'pfEmbed' . ucfirst($tipo) . (int) $fila['id'];
        $html .= '<div class="accordion" id="' . $idAcordeon . '">';
        foreach ($items as $indice => $item) {
            $idItem = $idAcordeon . 'Item' . $indice;
            $html .= '<div class="accordion-item">'
                . '<h2 class="accordion-header" id="' . $idItem . 'Header">'
                . '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $idItem . '" aria-expanded="false" aria-controls="' . $idItem . '">'
                . htmlspecialchars($item['titulo'])
                . '</button></h2>'
                . '<div id="' . $idItem . '" class="accordion-collapse collapse" aria-labelledby="' . $idItem . 'Header" data-bs-parent="#' . $idAcordeon . '">'
                . '<div class="accordion-body">' . htmlspecialchars($item['resumen']) . '</div>'
                . '</div></div>';
        }
        $html .= '</div>';
    }
    $html .= '<a class="pf-embed-curso-evento-link" href="' . htmlspecialchars($enlace) . '">Ver ' . $accion . ' completo →</a>';
    $html .= '</div>';
    return $html;
}

// Reemplaza todos los placeholders "[curso:slug]"/"[evento:slug]" que haya
// en un HTML de landing page. Un placeholder que no matchea nada (slug
// inválido, o sin lecciones publicadas) simplemente desaparece en vez de
// dejar el texto crudo "[curso:...]" visible en la página.
function reemplazar_embeds_curso_evento(mysqli $conn, string $html): string
{
    return preg_replace_callback(
        '/\[(curso|evento):([a-z0-9\-]+)\]/i',
        function (array $coincidencia) use ($conn): string {
            return renderizar_embed_curso_evento($conn, strtolower($coincidencia[1]), $coincidencia[2]);
        },
        $html
    );
}
