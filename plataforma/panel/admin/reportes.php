<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');

$porMes = $conn->query(
    "SELECT DATE_FORMAT(fecha_pago, '%Y-%m') AS mes, SUM(monto - descuento) AS total
     FROM pagos WHERE estado = 'confirmado' AND fecha_pago >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes"
)->fetch_all(MYSQLI_ASSOC);

$porMetodo = $conn->query(
    "SELECT metodo_pago, COUNT(*) AS n, SUM(monto - descuento) AS total
     FROM pagos WHERE estado = 'confirmado' GROUP BY metodo_pago"
)->fetch_all(MYSQLI_ASSOC);

$porEstado = $conn->query('SELECT estado, COUNT(*) AS n FROM pagos GROUP BY estado')->fetch_all(MYSQLI_ASSOC);

$porCurso = $conn->query(
    "SELECT c.titulo, COUNT(p.id) AS ventas, COALESCE(SUM(p.monto - p.descuento), 0) AS total
     FROM cursos c LEFT JOIN pagos p ON p.curso_id = c.id AND p.estado = 'confirmado'
     GROUP BY c.id ORDER BY total DESC"
)->fetch_all(MYSQLI_ASSOC);

$cuponesUso = $conn->query(
    "SELECT codigo, usos_actuales,
     (SELECT COALESCE(SUM(descuento),0) FROM pagos WHERE cupon_id = cupones.id) AS descontado
     FROM cupones ORDER BY usos_actuales DESC"
)->fetch_all(MYSQLI_ASSOC);

$certificados = (int) $conn->query('SELECT COUNT(*) t FROM certificados')->fetch_assoc()['t'];
$temasForo = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas')->fetch_assoc()['t'];
$respuestasForo = (int) $conn->query('SELECT COUNT(*) t FROM foro_respuestas')->fetch_assoc()['t'];
$activos = (int) $conn->query('SELECT COUNT(*) t FROM usuarios_perfil WHERE activo = 1')->fetch_assoc()['t'];
$inactivos = (int) $conn->query('SELECT COUNT(*) t FROM usuarios_perfil WHERE activo = 0')->fetch_assoc()['t'];

$pageTitle = 'Reportes';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Reportes</h1>

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
      <table class="table table-sm">
        <?php foreach ($porMes as $m): ?>
          <tr><td><?= htmlspecialchars($m['mes']) ?></td><td>$<?= number_format((float) $m['total'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Por método de pago</h5>
      <table class="table table-sm">
        <?php foreach ($porMetodo as $m): ?>
          <tr><td><?= htmlspecialchars($m['metodo_pago']) ?></td><td><?= (int) $m['n'] ?> pagos</td><td>$<?= number_format((float) $m['total'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Pagos por estado</h5>
      <table class="table table-sm">
        <?php foreach ($porEstado as $e): ?>
          <tr><td><?= htmlspecialchars($e['estado']) ?></td><td><?= (int) $e['n'] ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Ventas por curso</h5>
      <table class="table table-sm">
        <?php foreach ($porCurso as $c): ?>
          <tr><td><?= htmlspecialchars($c['titulo']) ?></td><td><?= (int) $c['ventas'] ?></td><td>$<?= number_format((float) $c['total'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-12">
    <div class="card p-3">
      <h5>Uso de cupones</h5>
      <table class="table table-sm">
        <?php foreach ($cuponesUso as $c): ?>
          <tr><td><?= htmlspecialchars($c['codigo']) ?></td><td><?= (int) $c['usos_actuales'] ?> usos</td><td>$<?= number_format((float) $c['descontado'], 2) ?> descontado</td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
