<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');

$totalUsuarios = (int) $conn->query('SELECT COUNT(*) t FROM usuarios_perfil')->fetch_assoc()['t'];
$totalCursos = (int) $conn->query('SELECT COUNT(*) t FROM cursos WHERE activo = 1')->fetch_assoc()['t'];
$ingresos = (float) $conn->query("SELECT COALESCE(SUM(monto),0) t FROM pagos WHERE estado = 'confirmado'")->fetch_assoc()['t'];
$pendientes = (int) $conn->query("SELECT COUNT(*) t FROM pagos WHERE estado = 'pendiente'")->fetch_assoc()['t'];

$topCursos = $conn->query(
    "SELECT c.titulo, COUNT(p.id) AS ventas
     FROM cursos c LEFT JOIN pagos p ON p.curso_id = c.id AND p.estado = 'confirmado'
     GROUP BY c.id ORDER BY ventas DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
include __DIR__ . '/_header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Usuarios</div><div class="h3"><?= $totalUsuarios ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Cursos activos</div><div class="h3"><?= $totalCursos ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Ingresos confirmados</div><div class="h3">$<?= number_format($ingresos, 2) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Pagos pendientes</div><div class="h3"><?= $pendientes ?></div></div></div>
</div>

<div class="card p-3">
  <h5>Cursos más vendidos</h5>
  <ul class="list-group list-group-flush">
    <?php foreach ($topCursos as $c): ?>
      <li class="list-group-item d-flex justify-content-between">
        <span><?= htmlspecialchars($c['titulo']) ?></span>
        <span class="badge bg-dark"><?= (int) $c['ventas'] ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
