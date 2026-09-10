<?php
// Filas de la tabla de usuarios — compartido por usuarios.php (render inicial,
// dentro de <tbody>) y usuarios_buscar.php (respuesta AJAX del buscador en
// vivo, que reemplaza el <tbody> completo). Espera $usuarios,
// $membresiasDisponibles, $miId, $busqueda, $tipo ya definidos.
require_once __DIR__ . '/_usuarios_fila_render.php';
foreach ($usuarios as $u) {
    echo pf_render_fila_usuario($u, $miId, $busqueda, $tipo, $membresiasDisponibles);
}
if (!$usuarios) {
    echo '<tr><td colspan="8" class="text-muted">No hay usuarios que coincidan.</td></tr>';
}
