<?php
// Resultados en vivo para el buscador del foro (dropdown mientras se escribe).
// Búsqueda por LIKE en el título, no FULLTEXT — MATCH AGAINST en modo natural
// no encuentra palabras a medio escribir (ft_min_word_len, sin prefijos), así
// que no sirve para "ir viendo resultados conforme se escribe". La búsqueda
// completa (título + contenido, temas + respuestas) sigue siendo buscar.php.
require_once __DIR__ . '/foro_helpers.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['temas' => []]);
    exit;
}

$visSql = foro_visibilidad_sql();
[$uid1, $uid2, $esAdmin, $esAdmin2] = foro_visibilidad_binds(current_user());

$etiquetasResumenSql = foro_etiquetas_resumen_sql('t');
$like = '%' . $q . '%';
$stmt = $conn->prepare(
    "SELECT t.id, t.titulo, $etiquetasResumenSql AS etiquetas_nombres
     FROM foro_temas t
     WHERE t.titulo LIKE ? AND $visSql
     ORDER BY t.ultima_respuesta_at DESC, t.created_at DESC
     LIMIT 6"
);
$stmt->bind_param('siiii', $like, $uid1, $uid2, $esAdmin, $esAdmin2);
$stmt->execute();
$temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['temas' => $temas]);
