<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/certificados.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_role('admin');
requerir_csrf_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'validar_pago') {
    $pagoId = (int) $_POST['pago_id'];
    $nuevoEstado = $_POST['estado'] ?? '';
    if (in_array($nuevoEstado, ['confirmado', 'rechazado'], true)) {
        $stmt = $conn->prepare(
            'UPDATE pagos SET estado = ?, fecha_validacion = NOW(), validado_por = ?,
             fecha_pago = IF(? = "confirmado", NOW(), fecha_pago) WHERE id = ? AND estado = "pendiente"'
        );
        $miId = (int) $_SESSION['usuario_perfil_id'];
        $stmt->bind_param('sisi', $nuevoEstado, $miId, $nuevoEstado, $pagoId);
        $stmt->execute();
        $afectados = $stmt->affected_rows;
        $stmt->close();

        if ($afectados > 0 && $nuevoEstado === 'confirmado') {
            $stmt = $conn->prepare(
                "SELECT p.usuario_id, p.evento_id, u.email_cache AS email,
                        COALESCE(c.titulo, e.titulo, pr.nombre) AS titulo,
                        CASE WHEN p.curso_id IS NOT NULL THEN 'curso' WHEN p.evento_id IS NOT NULL THEN 'evento' ELSE 'producto' END AS tipo,
                        COALESCE(c.slug, e.slug, pr.slug) AS slug
                 FROM pagos p
                 JOIN usuarios_perfil u ON u.id = p.usuario_id
                 LEFT JOIN cursos c ON c.id = p.curso_id
                 LEFT JOIN eventos e ON e.id = p.evento_id
                 LEFT JOIN productos pr ON pr.id = p.producto_id
                 WHERE p.id = ?"
            );
            $stmt->bind_param('i', $pagoId);
            $stmt->execute();
            $info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($info && $info['email']) {
                $enlace = SITE_URL . '/index.php?action=' . $info['tipo'] . '&slug=' . urlencode($info['slug']);
                if ($info['tipo'] === 'curso') {
                    $enlace .= '&bienvenida=1';
                }
                enviar_email_inscripcion((int) $info['usuario_id'], $info['email'], $info['titulo'], $enlace);
            }
            // Si es un evento de pago, la confirmación también cuenta como inscripción.
            if ($info && $info['tipo'] === 'evento' && $info['evento_id']) {
                $stmt = $conn->prepare(
                    "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado) VALUES (?, ?, 'inscrito')
                     ON DUPLICATE KEY UPDATE estado = IF(estado = 'cancelado', 'inscrito', estado)"
                );
                $stmt->bind_param('ii', $info['usuario_id'], $info['evento_id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    header('Location: pagos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_pago') {
    $pagoId = (int) ($_POST['pago_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM pagos WHERE id = ?');
    $stmt->bind_param('i', $pagoId);
    $stmt->execute();
    $stmt->close();
    header('Location: pagos.php');
    exit;
}

$pagos = $conn->query(
    "SELECT p.*, u.username_cache, u.email_cache,
            COALESCE(c.titulo, e.titulo, pr.nombre) AS articulo,
            CASE WHEN p.curso_id IS NOT NULL THEN 'Curso' WHEN p.evento_id IS NOT NULL THEN 'Evento' ELSE 'Producto' END AS tipo
     FROM pagos p
     JOIN usuarios_perfil u ON u.id = p.usuario_id
     LEFT JOIN cursos c ON c.id = p.curso_id
     LEFT JOIN eventos e ON e.id = p.evento_id
     LEFT JOIN productos pr ON pr.id = p.producto_id
     ORDER BY (p.estado = 'pendiente') DESC, p.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$estadoBadge = ['pendiente' => 'bg-warning', 'confirmado' => 'bg-success', 'rechazado' => 'bg-danger'];
$pageTitle = 'Pagos';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Pagos</h1>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Tipo</th><th>Artículo</th><th>Cant.</th><th>Monto</th><th>Método</th><th>Estado</th><th>Comprobante/Envío</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($pagos as $p): ?>
      <tr>
        <td><?= htmlspecialchars((string) $p['username_cache']) ?><br><span class="text-muted small"><?= htmlspecialchars((string) $p['email_cache']) ?></span></td>
        <td><?= htmlspecialchars($p['tipo']) ?></td>
        <td><?= htmlspecialchars((string) $p['articulo']) ?></td>
        <td><?= (int) $p['cantidad'] ?></td>
        <td>$<?= number_format((float) $p['monto'], 2) ?></td>
        <td><?= htmlspecialchars($p['metodo_pago']) ?></td>
        <td><span class="badge <?= $estadoBadge[$p['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
        <td>
          <?php if ($p['comprobante_url']): ?><a href="<?= htmlspecialchars($p['comprobante_url']) ?>" target="_blank">Comprobante</a><?php endif; ?>
          <?php if ($p['direccion_envio']): ?><div class="small text-muted"><?= nl2br(htmlspecialchars($p['direccion_envio'])) ?></div><?php endif; ?>
        </td>
        <td class="d-flex gap-2">
          <?php if ($p['estado'] === 'pendiente'): ?>
            <form method="post" class="d-flex gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="accion" value="validar_pago">
              <button class="btn btn-sm btn-success" name="estado" value="confirmado">Confirmar</button>
              <button class="btn btn-sm btn-danger" name="estado" value="rechazado">Rechazar</button>
            </form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('¿Eliminar este pago? Esta acción no se puede deshacer.');">
            <?= csrf_field() ?>
            <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_pago">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
