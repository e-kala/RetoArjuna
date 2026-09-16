<?php
// Filtro compartido "Todos / Reales / Prueba" para cualquier tabla del panel
// admin que liste usuarios (usuarios.php, pagos.php, membresias.php,
// certificados.php, etc.) — un solo criterio para que "prueba" signifique
// siempre lo mismo en todo el panel, sin que cada tabla lo redefina a su
// manera.
//
// "Prueba" = la cuenta está marcada usuarios_perfil.es_prueba=1, O (si la
// tabla en cuestión tiene su propia columna modo de pago/suscripción, ver
// $columnaModo) ese registro se hizo en modo='prueba' de Stripe — cualquiera
// de las dos cuenta como prueba, un usuario real nunca genera un pago de
// prueba.
// "Reales" = todo lo demás, excluyendo TAMBIÉN cuentas con rol='admin' —
// esa vista es solo para estudiantes/clientes de verdad, un admin (de
// prueba o no) nunca debe aparecer ahí.
// "Todos" = sin filtro.

/**
 * Lee y normaliza $_GET[$param] — el nombre del query param es configurable
 * porque algunas páginas (ej. certificados.php) ya usan "tipo" para su
 * propio filtro (curso/evento) y necesitan uno distinto para este.
 */
function pf_tipo_usuario_actual(string $param = 'tipo'): string
{
    $valor = $_GET[$param] ?? 'todos';
    return in_array($valor, ['todos', 'reales', 'prueba'], true) ? $valor : 'todos';
}

/**
 * Condición SQL (sin "WHERE"/"AND" inicial) para el tipo actual, más el
 * bind opcional que necesite (hoy ninguno — todo es literal en el enum, no
 * requiere parámetro). $aliasUsuario es el alias de usuarios_perfil en la
 * query (ej. 'u'); $aliasModo, si se pasa, es "alias.columna" de la tabla
 * con modo live/prueba propio de ESE listado (ej. 's.modo' en
 * membresia_suscripciones, 'p.modo' en pagos) — se combina con OR porque
 * cualquiera de los dos ya basta para contar como "prueba". Cuando el
 * listado no tiene su propia columna modo (ej. certificados, lista de
 * espera), se omite y el filtro cae solo en es_prueba.
 */
function pf_filtro_tipo_usuario_sql(string $tipo, string $aliasUsuario = 'u', ?string $aliasModo = null): ?string
{
    if ($tipo === 'reales') {
        $cond = "{$aliasUsuario}.es_prueba = 0 AND {$aliasUsuario}.rol <> 'admin'";
        if ($aliasModo) {
            $cond .= " AND ({$aliasModo} = 'live')";
        }
        return $cond;
    }
    if ($tipo === 'prueba') {
        $cond = "{$aliasUsuario}.es_prueba = 1";
        if ($aliasModo) {
            $cond = "({$cond} OR {$aliasModo} = 'prueba')";
        }
        return $cond;
    }
    return null;
}

/**
 * Los 3 botones Todos/Reales/Prueba, listos para imprimir — $qsExtra es
 * cualquier otro query string a preservar (ej. "q=" . urlencode($busqueda)),
 * sin el "?"/"&" inicial. $param es el nombre del query param (ver
 * pf_tipo_usuario_actual) para páginas que ya usan "tipo" para otra cosa.
 */
function pf_filtro_tipo_usuario_botones(string $tipo, string $urlBase, string $qsExtra = '', string $param = 'tipo'): string
{
    $sufijo = $qsExtra !== '' ? '&' . $qsExtra : '';
    $etiquetas = ['todos' => 'Todos', 'reales' => 'Reales', 'prueba' => 'Prueba'];
    $html = '<div class="btn-group btn-group-sm mb-3" role="group">';
    foreach ($etiquetas as $valor => $texto) {
        $activo = $tipo === $valor;
        $html .= '<a href="' . htmlspecialchars($urlBase) . '?' . $param . '=' . $valor . $sufijo . '" class="btn ' . ($activo ? 'btn-dark' : 'btn-outline-dark') . '">' . $texto . '</a>';
    }
    $html .= '</div>';
    return $html;
}
