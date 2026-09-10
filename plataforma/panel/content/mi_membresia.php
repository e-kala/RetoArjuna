<?php
// "Mi membresía" — pestaña propia (2026-09-08), antes vivía como una tabla
// dentro de "Mis compras" (extraída de ahí, misma consulta/lógica).
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT ms.estado, ms.modo, ms.periodo_actual_fin, ms.created_at, m.nombre, m.precio, m.intervalo
     FROM membresia_suscripciones ms
     JOIN membresias m ON m.id = ms.membresia_id
     WHERE ms.usuario_id = ?
     ORDER BY ms.created_at DESC"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$suscripciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$estadoSuscripcionBadge = ['activa' => 'bg-success', 'cancelada' => 'bg-secondary', 'vencida' => 'bg-danger'];
?>
<h1 class="h3 fw-bold mb-4">Mi membresía</h1>

<?php if ($suscripciones): ?>
  <table class="table table-bordered bg-white">
    <thead>
      <tr><th>Membresía</th><th>Precio</th><th>Estado</th><th>Vigente hasta</th><th>Desde</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($suscripciones as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['nombre']) ?></td>
          <td>$<?= number_format((float) $s['precio'], 2) ?> MXN / <?= $s['intervalo'] === 'anual' ? 'año' : 'mes' ?></td>
          <td>
            <span class="badge <?= $estadoSuscripcionBadge[$s['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($s['estado']) ?></span>
            <?php if (($s['modo'] ?? 'live') === 'prueba'): ?><span class="badge bg-dark" title="Suscripción hecha en modo prueba de Stripe — no fue dinero real">🧪 prueba</span><?php endif; ?>
          </td>
          <td><?= $s['periodo_actual_fin'] ? htmlspecialchars($s['periodo_actual_fin']) : '—' ?></td>
          <td><?= htmlspecialchars($s['created_at']) ?></td>
          <td>
            <?php if ($s['estado'] === 'activa'): ?>
              <a href="<?= BASE_URL ?>/backend/pagos/membresia_portal.php" class="btn btn-sm btn-outline-primary">Gestionar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class="text-muted">Todavía no tienes una membresía. <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=membresia" style="color:#B8860B;">Conoce Camino Arjuna</a>.</p>
<?php endif; ?>
