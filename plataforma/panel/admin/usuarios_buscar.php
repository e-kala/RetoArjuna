<?php
// Buscador en vivo de usuarios.php — responde solo las filas <tr> (reemplazan
// el <tbody> por fetch mientras se escribe), sin recargar la página.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');

$miId = (int) $_SESSION['usuario_perfil_id'];

require __DIR__ . '/_usuarios_query.php';
require __DIR__ . '/_usuarios_filas.php';
