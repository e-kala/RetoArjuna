<?php
// Compartido por eventos.php (render inicial) y eventos_buscar.php (Ajax en
// vivo) — arma $eventos según $_GET['q']. Deja $busqueda y $eventos listos.
$busqueda = trim($_GET['q'] ?? '');

if ($busqueda !== '') {
    $like = '%' . $busqueda . '%';
    $stmt = $conn->prepare(
        "SELECT ev.*, (SELECT COUNT(*) FROM evento_inscripciones WHERE evento_id = ev.id AND estado <> 'cancelado') AS total_inscritos,
                (SELECT COUNT(*) FROM lecciones WHERE evento_id = ev.id) AS total_lecciones
         FROM eventos ev WHERE ev.titulo LIKE ? ORDER BY fecha_inicio DESC"
    );
    $stmt->bind_param('s', $like);
} else {
    $stmt = $conn->prepare(
        "SELECT ev.*, (SELECT COUNT(*) FROM evento_inscripciones WHERE evento_id = ev.id AND estado <> 'cancelado') AS total_inscritos,
                (SELECT COUNT(*) FROM lecciones WHERE evento_id = ev.id) AS total_lecciones
         FROM eventos ev ORDER BY fecha_inicio DESC"
    );
}
$stmt->execute();
$eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
