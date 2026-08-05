<?php
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT p.*, c.titulo AS curso_titulo
     FROM pagos p JOIN cursos c ON c.id = p.curso_id
     WHERE p.usuario_id = ? ORDER BY p.created_at DESC"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$compras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$estadoBadge = ['pendiente' => 'bg-warning', 'confirmado' => 'bg-success', 'rechazado' => 'bg-danger'];
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Mis compras</h1>
</div>
<table class="table table-bordered bg-white">
    <thead>
        <tr><th>Curso</th><th>Monto</th><th>Método</th><th>Estado</th><th>Fecha</th></tr>
    </thead>
    <tbody>
        <?php foreach ($compras as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['curso_titulo']) ?></td>
                <td>$<?= number_format((float) $c['monto'] - (float) $c['descuento'], 2) ?> MXN</td>
                <td><?= htmlspecialchars($c['metodo_pago']) ?></td>
                <td><span class="badge <?= $estadoBadge[$c['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($c['estado']) ?></span></td>
                <td><?= htmlspecialchars($c['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$compras): ?>
            <tr><td colspan="5" class="text-muted">Aún no tienes compras registradas.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
