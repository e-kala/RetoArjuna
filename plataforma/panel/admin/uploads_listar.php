<?php
// Endpoint para el selector "elegir de las ya subidas" (_imagen_picker.php) —
// devuelve las imágenes ya subidas en uploads/<subdir>/ para que el admin
// pueda reusar una en vez de subir un duplicado.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
header('Content-Type: application/json');

$subdirsPermitidos = ['cursos', 'eventos', 'productos', 'noticias'];
$subdir = $_GET['subdir'] ?? '';
if (!in_array($subdir, $subdirsPermitidos, true)) {
    echo json_encode(['imagenes' => []]);
    exit;
}

echo json_encode(['imagenes' => listar_imagenes_subidas($subdir)]);
