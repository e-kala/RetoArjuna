<?php
// Compartido por productos.php (render inicial) y productos_buscar.php (Ajax
// en vivo) — arma $productos según $_GET['q']. Deja $busqueda y $productos listos.
$busqueda = trim($_GET['q'] ?? '');

if ($busqueda !== '') {
    $like = '%' . $busqueda . '%';
    $stmt = $conn->prepare('SELECT * FROM productos WHERE nombre LIKE ? ORDER BY created_at DESC');
    $stmt->bind_param('s', $like);
} else {
    $stmt = $conn->prepare('SELECT * FROM productos ORDER BY created_at DESC');
}
$stmt->execute();
$productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
