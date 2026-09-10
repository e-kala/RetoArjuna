<?php
// Buscador en vivo de productos.php — responde solo las filas <tr>
// (reemplazan el <tbody> por fetch mientras se escribe), sin recargar la página.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');

require __DIR__ . '/_productos_query.php';
require __DIR__ . '/_productos_filas.php';
