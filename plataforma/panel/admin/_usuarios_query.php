<?php
// Compartido por usuarios.php (render inicial) y usuarios_buscar.php (AJAX en
// vivo) — arma la lista de usuarios según $_GET['q']/$_GET['tipo']. Deja
// $busqueda, $tipo, $usuarios y $membresiasDisponibles listos para quien lo
// incluya.
$busqueda = trim($_GET['q'] ?? '');
$tipo = $_GET['tipo'] ?? 'todos';
if (!in_array($tipo, ['todos', 'reales', 'prueba'], true)) {
    $tipo = 'todos';
}

$condiciones = [];
$valores = [];
$tiposBind = '';
if ($busqueda !== '') {
    $condiciones[] = '(u.username_cache LIKE ? OR u.email_cache LIKE ?)';
    $like = '%' . $busqueda . '%';
    $valores[] = $like;
    $valores[] = $like;
    $tiposBind .= 'ss';
}
if ($tipo === 'reales') {
    $condiciones[] = 'u.es_prueba = 0';
} elseif ($tipo === 'prueba') {
    $condiciones[] = 'u.es_prueba = 1';
}
$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$sql = "SELECT u.*,
          ms.id AS membresia_suscripcion_id, ms.membresia_id AS membresia_activa_id,
          ms.metodo AS membresia_metodo, ms.fecha_inicio AS membresia_fecha_inicio,
          ms.periodo_actual_fin AS membresia_periodo_actual_fin,
          ms.renovacion_automatica AS membresia_renovacion_automatica,
          mb.nombre AS membresia_nombre
        FROM usuarios_perfil u
        LEFT JOIN (
          SELECT s1.* FROM membresia_suscripciones s1
          INNER JOIN (SELECT usuario_id, MAX(id) AS max_id FROM membresia_suscripciones WHERE estado = 'activa' GROUP BY usuario_id) s2
            ON s2.max_id = s1.id
        ) ms ON ms.usuario_id = u.id
        LEFT JOIN membresias mb ON mb.id = ms.membresia_id
        $where ORDER BY u.created_at DESC";
$stmt = $conn->prepare($sql);
if ($valores) {
    $stmt->bind_param($tiposBind, ...$valores);
}
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$membresiasDisponibles = $conn->query('SELECT id, nombre FROM membresias WHERE activo = 1 ORDER BY orden ASC')->fetch_all(MYSQLI_ASSOC);
