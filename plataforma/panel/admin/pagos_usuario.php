<?php
// Todo lo que un usuario ha adquirido (pagos de curso/evento/producto +
// suscripciones de membresía) en un solo lugar — enlazado desde la pestaña
// "Usuarios" de pagos.php, para no tener que buscar entre todas las filas
// de la tabla general cuando alguien ya compró varias cosas.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/certificados.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_role('admin');
requerir_csrf_form();

$estadoBadge = [
    'pendiente' => 'bg-warning', 'confirmado' => 'bg-success', 'rechazado' => 'bg-danger',
    'activa' => 'bg-success', 'cancelada' => 'bg-secondary', 'vencida' => 'bg-danger',
];

require __DIR__ . '/_pagos_acciones.php';
require __DIR__ . '/_pagos_fila.php';

$usuarioId = (int) ($_GET['usuario_id'] ?? 0);
$stmt = $conn->prepare('SELECT id, username_cache, email_cache FROM usuarios_perfil WHERE id = ?');
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header('Location: pagos.php?tab=usuarios');
    exit;
}

$modoFiltro = $_GET['modo'] ?? 'reales';
if (!in_array($modoFiltro, ['reales', 'prueba', 'todos'], true)) {
    $modoFiltro = 'reales';
}
$modoFiltroSqlValor = $modoFiltro === 'reales' ? 'live' : $modoFiltro;
$filtroModoSql = $modoFiltro === 'todos' ? '' : 'AND p.modo = ?';

$sqlPagos = "SELECT p.*, u.username_cache, u.email_cache,
            COALESCE(c.titulo, e.titulo, pr.nombre) AS articulo,
            c.slug AS curso_slug, e.slug AS evento_slug, pr.slug AS producto_slug,
            CASE WHEN p.curso_id IS NOT NULL THEN 'Curso' WHEN p.evento_id IS NOT NULL THEN 'Evento' ELSE 'Producto' END AS tipo
     FROM pagos p
     JOIN usuarios_perfil u ON u.id = p.usuario_id
     LEFT JOIN cursos c ON c.id = p.curso_id
     LEFT JOIN eventos e ON e.id = p.evento_id
     LEFT JOIN productos pr ON pr.id = p.producto_id
     WHERE p.usuario_id = ? {$filtroModoSql}
     ORDER BY (p.estado = 'pendiente') DESC, p.created_at DESC";
$stmt = $conn->prepare($sqlPagos);
if ($filtroModoSql) {
    $stmt->bind_param('is', $usuarioId, $modoFiltroSqlValor);
} else {
    $stmt->bind_param('i', $usuarioId);
}
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$filtroModoSqlMembresias = $modoFiltro === 'todos' ? '' : 'AND s.modo = ?';
$sqlMembresias = "SELECT s.id, s.usuario_id, s.metodo, s.modo, s.estado, s.comprobante_url, s.created_at,
            u.username_cache, u.email_cache, m.nombre AS articulo, m.precio AS monto
     FROM membresia_suscripciones s
     JOIN usuarios_perfil u ON u.id = s.usuario_id
     JOIN membresias m ON m.id = s.membresia_id
     WHERE s.usuario_id = ? {$filtroModoSqlMembresias}
     ORDER BY (s.estado = 'pendiente') DESC, s.created_at DESC";
$stmt = $conn->prepare($sqlMembresias);
if ($filtroModoSqlMembresias) {
    $stmt->bind_param('is', $usuarioId, $modoFiltroSqlValor);
} else {
    $stmt->bind_param('i', $usuarioId);
}
$stmt->execute();
$membresiasPagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$movimientos = [];
foreach ($pagos as $p) {
    $p['origen'] = 'pago';
    $movimientos[] = $p;
}
foreach ($membresiasPagos as $s) {
    $s['origen'] = 'membresia';
    $s['tipo'] = 'Membresía';
    $s['cantidad'] = null;
    $s['metodo_pago'] = $s['metodo'];
    $s['direccion_envio'] = null;
    $s['curso_id'] = null;
    $s['evento_id'] = null;
    $s['producto_id'] = null;
    $movimientos[] = $s;
}
usort($movimientos, function ($a, $b) {
    $aPendiente = $a['estado'] === 'pendiente';
    $bPendiente = $b['estado'] === 'pendiente';
    if ($aPendiente !== $bPendiente) {
        return $bPendiente <=> $aPendiente;
    }
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$qsVolver = 'usuario_id=' . $usuarioId . '&modo=' . urlencode($modoFiltro);
$pageTitle = 'Pagos de ' . $usuario['username_cache'];
include __DIR__ . '/_header.php';
?>
<a href="pagos.php?tab=usuarios&modo=<?= urlencode($modoFiltro) ?>" class="d-inline-block mb-3">&larr; Volver a Usuarios</a>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">
    Pagos de <a href="../../index.php?action=perfil_publico&usuario=<?= (int) $usuario['id'] ?>" target="_blank"><?= htmlspecialchars($usuario['username_cache']) ?></a>
    <span class="text-muted small"><?= htmlspecialchars($usuario['email_cache']) ?></span>
  </h1>
  <div class="btn-group btn-group-sm" role="group">
    <a href="?usuario_id=<?= $usuarioId ?>&modo=reales" class="btn <?= $modoFiltro === 'reales' ? 'btn-primary' : 'btn-outline-primary' ?>">Reales</a>
    <a href="?usuario_id=<?= $usuarioId ?>&modo=prueba" class="btn <?= $modoFiltro === 'prueba' ? 'btn-primary' : 'btn-outline-primary' ?>">🧪 Prueba</a>
    <a href="?usuario_id=<?= $usuarioId ?>&modo=todos" class="btn <?= $modoFiltro === 'todos' ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
  </div>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Tipo</th><th>Artículo</th><th>Cant.</th><th>Monto</th><th>Método</th><th>Fecha</th><th>Estado</th><th>Comprobante/Envío</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($movimientos as $p): ?>
      <?php pf_pintar_fila_pago($p, $estadoBadge, $qsVolver); ?>
    <?php endforeach; ?>
    <?php if (!$movimientos): ?><tr><td colspan="10" class="text-muted">Este usuario no tiene pagos <?= $modoFiltro === 'reales' ? 'reales' : ($modoFiltro === 'prueba' ? 'de prueba' : '') ?> todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
