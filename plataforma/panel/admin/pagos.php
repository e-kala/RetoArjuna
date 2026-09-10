<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/certificados.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_role('admin');
requerir_csrf_form();

$estadoBadge = [
    'pendiente' => 'bg-warning', 'confirmado' => 'bg-success', 'rechazado' => 'bg-danger',
    // Estados propios de membresia_suscripciones (ver membresias.php) — se
    // combinan en el mismo mapa porque ambas tablas se muestran juntas aquí.
    'activa' => 'bg-success', 'cancelada' => 'bg-secondary', 'vencida' => 'bg-danger',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'validar_pago') {
    $esAjax = es_peticion_ajax();
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
        if ($esAjax) {
            if ($afectados > 0) {
                $badge = $estadoBadge[$nuevoEstado] ?? 'bg-secondary';
                echo json_encode([
                    'success' => true,
                    'mensaje' => $nuevoEstado === 'confirmado' ? 'Pago confirmado.' : 'Pago rechazado.',
                    'estado_html' => '<span class="badge ' . $badge . '">' . htmlspecialchars($nuevoEstado) . '</span>',
                    'quitar_grupo' => true,
                ]);
            } else {
                echo json_encode(['success' => false, 'mensaje' => 'Este pago ya fue procesado por alguien más.']);
            }
            exit;
        }
    }
    header('Location: pagos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_pago') {
    $esAjax = es_peticion_ajax();
    $pagoId = (int) ($_POST['pago_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM pagos WHERE id = ?');
    $stmt->bind_param('i', $pagoId);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) {
        echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Pago eliminado.']);
        exit;
    }
    header('Location: pagos.php');
    exit;
}

// Filtro live/prueba/todos — por defecto solo "live" (lo que de verdad importa
// el día a día); "prueba" y "todos" son vistas explícitas para cuando alguien
// quiere revisar sus propias pruebas de pago (ver modo prueba de Stripe en
// stripe_helper.php), sin que se mezclen con las ventas reales por defecto.
$modoFiltro = $_GET['modo'] ?? 'live';
if (!in_array($modoFiltro, ['live', 'prueba', 'todos'], true)) {
    $modoFiltro = 'live';
}
$filtroModoSql = $modoFiltro === 'todos' ? '' : 'WHERE p.modo = ?';

$sqlPagos = "SELECT p.*, u.username_cache, u.email_cache,
            COALESCE(c.titulo, e.titulo, pr.nombre) AS articulo,
            c.slug AS curso_slug, e.slug AS evento_slug, pr.slug AS producto_slug,
            CASE WHEN p.curso_id IS NOT NULL THEN 'Curso' WHEN p.evento_id IS NOT NULL THEN 'Evento' ELSE 'Producto' END AS tipo
     FROM pagos p
     JOIN usuarios_perfil u ON u.id = p.usuario_id
     LEFT JOIN cursos c ON c.id = p.curso_id
     LEFT JOIN eventos e ON e.id = p.evento_id
     LEFT JOIN productos pr ON pr.id = p.producto_id
     {$filtroModoSql}
     ORDER BY (p.estado = 'pendiente') DESC, p.created_at DESC";
$stmt = $conn->prepare($sqlPagos);
if ($filtroModoSql) {
    $stmt->bind_param('s', $modoFiltro);
}
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Las suscripciones de membresía viven en su propia tabla (estructura muy
// distinta: recurrente, sin cantidad, sin curso/evento/producto) pero
// también son dinero entrando — se combinan aquí mismo con $pagos para que
// "Pagos" sea el ledger completo, en vez de tener que revisar dos pantallas.
// Gestionar (confirmar/editar/cancelar) se sigue haciendo en membresias.php
// — aquí solo se listan, con un enlace directo a esa pantalla.
$filtroModoSqlMembresias = $modoFiltro === 'todos' ? '' : 'WHERE s.modo = ?';
$sqlMembresias = "SELECT s.id, s.usuario_id, s.metodo, s.modo, s.estado, s.comprobante_url, s.created_at,
            u.username_cache, u.email_cache, m.nombre AS articulo, m.precio AS monto
     FROM membresia_suscripciones s
     JOIN usuarios_perfil u ON u.id = s.usuario_id
     JOIN membresias m ON m.id = s.membresia_id
     {$filtroModoSqlMembresias}
     ORDER BY (s.estado = 'pendiente') DESC, s.created_at DESC";
$stmt = $conn->prepare($sqlMembresias);
if ($filtroModoSqlMembresias) {
    $stmt->bind_param('s', $modoFiltro);
}
$stmt->execute();
$membresiasPagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Normaliza ambas fuentes a la misma forma para poder recorrerlas juntas en
// una sola tabla, ordenadas por igual (pendientes primero, luego más
// recientes) sin importar de qué tabla vino cada fila.
$movimientos = [];
foreach ($pagos as $p) {
    $p['origen'] = 'pago';
    $p['metodo_pago'] = $p['metodo_pago'];
    $movimientos[] = $p;
}
foreach ($membresiasPagos as $s) {
    $s['origen'] = 'membresia';
    $s['tipo'] = 'Membresía';
    $s['cantidad'] = null;
    $s['metodo_pago'] = $s['metodo'];
    $s['direccion_envio'] = null;
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

$pageTitle = 'Pagos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Pagos</h1>
  <div class="btn-group btn-group-sm" role="group">
    <a href="?modo=live" class="btn <?= $modoFiltro === 'live' ? 'btn-primary' : 'btn-outline-primary' ?>">Live</a>
    <a href="?modo=prueba" class="btn <?= $modoFiltro === 'prueba' ? 'btn-primary' : 'btn-outline-primary' ?>">🧪 Prueba</a>
    <a href="?modo=todos" class="btn <?= $modoFiltro === 'todos' ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
  </div>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Tipo</th><th>Artículo</th><th>Cant.</th><th>Monto</th><th>Método</th><th>Fecha</th><th>Estado</th><th>Comprobante/Envío</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($movimientos as $p): ?>
      <tr>
        <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $p['usuario_id'] ?>" target="_blank"><?= htmlspecialchars((string) $p['username_cache']) ?></a><br><span class="text-muted small"><?= htmlspecialchars((string) $p['email_cache']) ?></span></td>
        <td><?= htmlspecialchars($p['tipo']) ?></td>
        <td>
          <?php if ($p['origen'] === 'pago' && $p['curso_id']): ?>
            <a href="../../index.php?action=curso&slug=<?= urlencode($p['curso_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['articulo']) ?></a>
          <?php elseif ($p['origen'] === 'pago' && $p['evento_id']): ?>
            <a href="../../index.php?action=evento&slug=<?= urlencode($p['evento_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['articulo']) ?></a>
          <?php elseif ($p['origen'] === 'pago' && $p['producto_id']): ?>
            <a href="../../index.php?action=producto&slug=<?= urlencode($p['producto_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['articulo']) ?></a>
          <?php else: ?>
            <?= htmlspecialchars((string) $p['articulo']) ?>
          <?php endif; ?>
        </td>
        <td><?= $p['cantidad'] !== null ? (int) $p['cantidad'] : '—' ?></td>
        <td>$<?= number_format((float) $p['monto'], 2) ?></td>
        <td><?= htmlspecialchars($p['metodo_pago']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['created_at']))) ?></td>
        <td data-ajax-estado>
          <span class="badge <?= $estadoBadge[$p['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($p['estado']) ?></span>
          <?php if (($p['modo'] ?? 'live') === 'prueba'): ?><span class="badge bg-dark" title="Pago hecho en modo prueba de Stripe — no es dinero real">🧪 prueba</span><?php endif; ?>
        </td>
        <td>
          <?php if ($p['comprobante_url']): ?><a href="<?= htmlspecialchars($p['comprobante_url']) ?>" target="_blank">Comprobante</a><?php endif; ?>
          <?php if ($p['direccion_envio']): ?><div class="small text-muted"><?= nl2br(htmlspecialchars($p['direccion_envio'])) ?></div><?php endif; ?>
        </td>
        <td class="d-flex gap-2">
          <?php if ($p['origen'] === 'membresia'): ?>
            <a href="membresias.php" class="btn btn-sm btn-outline-primary">Gestionar</a>
          <?php else: ?>
            <?php if ($p['estado'] === 'pendiente'): ?>
              <form method="post" class="d-flex gap-2" data-ajax="accion">
                <?= csrf_field() ?>
                <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="accion" value="validar_pago">
                <button class="btn btn-sm btn-success" name="estado" value="confirmado">Confirmar</button>
                <button class="btn btn-sm btn-danger" name="estado" value="rechazado">Rechazar</button>
              </form>
            <?php endif; ?>
            <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar este pago? Esta acción no se puede deshacer.">
              <?= csrf_field() ?>
              <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="accion" value="eliminar_pago">
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$movimientos): ?><tr><td colspan="10" class="text-muted">No hay pagos <?= $modoFiltro === 'live' ? 'reales' : ($modoFiltro === 'prueba' ? 'de prueba' : '') ?> todavía.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
