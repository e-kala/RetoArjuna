<?php
// Retirada — las notificaciones ahora viven unificadas en un solo lugar para
// toda la plataforma (ver backend/notificaciones.php), ya no solo del foro.
// Se deja este redirect por si alguien tiene la URL vieja guardada.
require_once __DIR__ . '/backend/foro_helpers.php';
require_login('index.php');

$usuario = current_user();
$destino = $usuario['rol'] === 'admin' ? 'index.php' : 'dashboard.php';
header('Location: ' . BASE_URL . '/panel/' . $destino . '?action=notificaciones');
exit;
