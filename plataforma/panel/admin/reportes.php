<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');

// Filtro live/prueba — nunca "todos" aquí (a diferencia de pagos.php): sumar
// dinero real con montos de prueba en el mismo total sería engañoso. Por
// defecto "live" (lo que de verdad importa); "prueba" sirve para revisar tus
// propias pruebas de pago sin ensuciar el reporte real.
$modoReporte = ($_GET['modo'] ?? 'live') === 'prueba' ? 'prueba' : 'live';

$porMes = $conn->prepare(
    "SELECT DATE_FORMAT(fecha_pago, '%Y-%m') AS mes, SUM(monto) AS total
     FROM pagos WHERE estado = 'confirmado' AND modo = ? AND fecha_pago >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes"
);
$porMes->bind_param('s', $modoReporte);
$porMes->execute();
$porMes = $porMes->get_result()->fetch_all(MYSQLI_ASSOC);

$porMetodo = $conn->prepare(
    "SELECT metodo_pago, COUNT(*) AS n, SUM(monto) AS total
     FROM pagos WHERE estado = 'confirmado' AND modo = ? GROUP BY metodo_pago"
);
$porMetodo->bind_param('s', $modoReporte);
$porMetodo->execute();
$porMetodo = $porMetodo->get_result()->fetch_all(MYSQLI_ASSOC);

$porEstado = $conn->prepare("SELECT estado, COUNT(*) AS n FROM pagos WHERE modo = ? GROUP BY estado");
$porEstado->bind_param('s', $modoReporte);
$porEstado->execute();
$porEstado = $porEstado->get_result()->fetch_all(MYSQLI_ASSOC);

$porCurso = $conn->prepare(
    "SELECT c.titulo, COUNT(p.id) AS ventas, COALESCE(SUM(p.monto), 0) AS total
     FROM cursos c LEFT JOIN pagos p ON p.curso_id = c.id AND p.estado = 'confirmado' AND p.modo = ?
     GROUP BY c.id ORDER BY total DESC"
);
$porCurso->bind_param('s', $modoReporte);
$porCurso->execute();
$porCurso = $porCurso->get_result()->fetch_all(MYSQLI_ASSOC);

$certificados = (int) $conn->query('SELECT COUNT(*) t FROM certificados')->fetch_assoc()['t'];
$temasForo = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas')->fetch_assoc()['t'];
$respuestasForo = (int) $conn->query('SELECT COUNT(*) t FROM foro_respuestas')->fetch_assoc()['t'];
$activos = (int) $conn->query('SELECT COUNT(*) t FROM usuarios_perfil WHERE activo = 1')->fetch_assoc()['t'];
$inactivos = (int) $conn->query('SELECT COUNT(*) t FROM usuarios_perfil WHERE activo = 0')->fetch_assoc()['t'];

$pageTitle = 'Reportes';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Reportes</h1>
  <div class="btn-group btn-group-sm" role="group">
    <a href="?modo=live" class="btn <?= $modoReporte === 'live' ? 'btn-primary' : 'btn-outline-primary' ?>">Live</a>
    <a href="?modo=prueba" class="btn <?= $modoReporte === 'prueba' ? 'btn-primary' : 'btn-outline-primary' ?>">🧪 Prueba</a>
  </div>
</div>
<?php if ($modoReporte === 'prueba'): ?><div class="alert alert-warning py-2">Viendo solo pagos de prueba de Stripe — esto no es dinero real.</div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Certificados emitidos</div><div class="h4"><?= $certificados ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Temas del foro</div><div class="h4"><?= $temasForo ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Respuestas del foro</div><div class="h4"><?= $respuestasForo ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Usuarios activos</div><div class="h4"><?= $activos ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Usuarios inactivos</div><div class="h4"><?= $inactivos ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Ingresos por mes</h5>
      <div class="table-responsive">
      <table class="table table-sm">
        <?php foreach ($porMes as $m): ?>
          <tr><td><?= htmlspecialchars($m['mes']) ?></td><td>$<?= number_format((float) $m['total'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Por método de pago</h5>
      <div class="table-responsive">
      <table class="table table-sm">
        <?php foreach ($porMetodo as $m): ?>
          <tr><td><?= htmlspecialchars($m['metodo_pago']) ?></td><td><?= (int) $m['n'] ?> pagos</td><td>$<?= number_format((float) $m['total'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Pagos por estado</h5>
      <div class="table-responsive">
      <table class="table table-sm">
        <?php foreach ($porEstado as $e): ?>
          <tr><td><?= htmlspecialchars($e['estado']) ?></td><td><?= (int) $e['n'] ?></td></tr>
        <?php endforeach; ?>
      </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Ventas por curso</h5>
      <div class="table-responsive">
      <table class="table table-sm">
        <?php foreach ($porCurso as $c): ?>
          <tr><td><?= htmlspecialchars($c['titulo']) ?></td><td><?= (int) $c['ventas'] ?></td><td>$<?= number_format((float) $c['total'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
