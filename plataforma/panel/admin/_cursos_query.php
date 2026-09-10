<?php
// Compartido por cursos.php (render inicial) y cursos_buscar.php (Ajax en
// vivo) — arma $cursos según $_GET['q']. Deja $busqueda y $cursos listos.
$busqueda = trim($_GET['q'] ?? '');

if ($busqueda !== '') {
    $like = '%' . $busqueda . '%';
    $stmt = $conn->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM lecciones WHERE curso_id = c.id) AS total_lecciones
         FROM cursos c WHERE c.titulo LIKE ? ORDER BY created_at DESC"
    );
    $stmt->bind_param('s', $like);
} else {
    $stmt = $conn->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM lecciones WHERE curso_id = c.id) AS total_lecciones
         FROM cursos c ORDER BY created_at DESC"
    );
}
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
