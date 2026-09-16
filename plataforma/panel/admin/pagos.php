<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/certificados.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_once __DIR__ . '/_filtro_tipo_usuario.php';
require_role('admin');
requerir_csrf_form();

$estadoBadge = [
    'pendiente' => 'bg-warning', 'confirmado' => 'bg-success', 'rechazado' => 'bg-danger',
    // Estados propios de membresia_suscripciones (ver membresias.php) — se
    // combinan en el mismo mapa porque ambas tablas se muestran juntas aquí.
    'activa' => 'bg-success', 'cancelada' => 'bg-secondary', 'vencida' => 'bg-danger',
];

require __DIR__ . '/_pagos_acciones.php';
require __DIR__ . '/_pagos_fila.php';

// Filtro reales/prueba/todos — combina es_prueba de la cuenta con el modo
// de Stripe del pago (ver _filtro_tipo_usuario.php: cualquiera de los dos
// ya cuenta como "prueba", un usuario real nunca genera un pago de
// prueba). Por defecto "reales" (lo que de verdad importa el día a día);
// "prueba" y "todos" son vistas explícitas para revisar pruebas propias.
$modoFiltro = $_GET['modo'] ?? 'reales';
if (!in_array($modoFiltro, ['reales', 'prueba', 'todos'], true)) {
    $modoFiltro = 'reales';
}
$filtroTipoPagos = pf_filtro_tipo_usuario_sql($modoFiltro, 'u', 'p.modo');
$filtroModoSql = $filtroTipoPagos ? "WHERE {$filtroTipoPagos}" : '';

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
$pagos = $conn->query($sqlPagos)->fetch_all(MYSQLI_ASSOC);

// Las suscripciones de membresía viven en su propia tabla (estructura muy
// distinta: recurrente, sin cantidad, sin curso/evento/producto) pero
// también son dinero entrando — se combinan aquí mismo con $pagos para que
// "Pagos" sea el ledger completo, en vez de tener que revisar dos pantallas.
// Gestionar (confirmar/editar/cancelar) se sigue haciendo en membresias.php
// — aquí solo se listan, con un enlace directo a esa pantalla.
$filtroTipoMembresias = pf_filtro_tipo_usuario_sql($modoFiltro, 'u', 's.modo');
$filtroModoSqlMembresias = $filtroTipoMembresias ? "WHERE {$filtroTipoMembresias}" : '';
$sqlMembresias = "SELECT s.id, s.usuario_id, s.metodo, s.modo, s.estado, s.comprobante_url, s.created_at,
            u.username_cache, u.email_cache, m.nombre AS articulo, m.precio AS monto
     FROM membresia_suscripciones s
     JOIN usuarios_perfil u ON u.id = s.usuario_id
     JOIN membresias m ON m.id = s.membresia_id
     {$filtroModoSqlMembresias}
     ORDER BY (s.estado = 'pendiente') DESC, s.created_at DESC";
$membresiasPagos = $conn->query($sqlMembresias)->fetch_all(MYSQLI_ASSOC);

// Normaliza ambas fuentes a la misma forma para poder recorrerlas juntas,
// ordenadas por igual (pendientes primero, luego más recientes) sin
// importar de qué tabla vino cada fila.
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

// Pendientes: solo transferencias — son las únicas que de verdad requieren
// una decisión del admin (revisar el comprobante a ojo). Un pago con
// tarjeta "pendiente" nunca necesita que alguien lo confirme a mano:
// Stripe ya lo resuelve solo (checkout.php lo confirma en cuanto el
// usuario vuelve del pago, y el webhook es la red de seguridad) — si sigue
// en pendiente es porque el usuario nunca terminó de pagar, no porque
// falte una decisión (ver pestaña "Incompletos" más abajo).
$pendientes = array_values(array_filter(
    $movimientos,
    fn($m) => $m['estado'] === 'pendiente' && $m['metodo_pago'] !== 'stripe'
));

// Incompletos: intentos de pago con tarjeta que nunca se completaron (el
// usuario cerró la pestaña, la tarjeta fue rechazada, etc.) — stripe_create_intent.php
// crea esta fila en 'pendiente' desde que se abre el formulario de pago,
// antes de que el usuario confirme nada. Se separan de "Pendientes" para
// que nunca se mezclen con transferencias reales esperando revisión.
$incompletos = array_values(array_filter(
    $movimientos,
    fn($m) => $m['estado'] === 'pendiente' && $m['metodo_pago'] === 'stripe'
));

// Usuarios: uno por cada persona con al menos un movimiento YA resuelto
// (nunca solo pendientes/incompletos — esos ya se ven en sus propias
// pestañas), con un resumen de cuántas compras/cuánto lleva gastado, para
// que la tabla de pagos no repita un usuario con varias compras en varias
// filas sueltas — el detalle completo vive en pagos_usuario.php.
$resueltos = array_values(array_filter($movimientos, fn($m) => $m['estado'] !== 'pendiente'));
$usuariosResumen = [];
foreach ($resueltos as $m) {
    $uid = (int) $m['usuario_id'];
    if (!isset($usuariosResumen[$uid])) {
        $usuariosResumen[$uid] = [
            'usuario_id' => $uid,
            'username_cache' => $m['username_cache'],
            'email_cache' => $m['email_cache'],
            'cantidad_movimientos' => 0,
            'confirmados' => 0,
            'monto_confirmado' => 0.0,
            'ultimo_movimiento' => $m['created_at'],
        ];
    }
    $usuariosResumen[$uid]['cantidad_movimientos']++;
    if (in_array($m['estado'], ['confirmado', 'activa'], true)) {
        $usuariosResumen[$uid]['confirmados']++;
        $usuariosResumen[$uid]['monto_confirmado'] += (float) $m['monto'];
    }
    if (strtotime($m['created_at']) > strtotime($usuariosResumen[$uid]['ultimo_movimiento'])) {
        $usuariosResumen[$uid]['ultimo_movimiento'] = $m['created_at'];
    }
}
usort($usuariosResumen, fn($a, $b) => strtotime($b['ultimo_movimiento']) <=> strtotime($a['ultimo_movimiento']));

$tab = $_GET['tab'] ?? 'usuarios';
if (!in_array($tab, ['pendientes', 'incompletos', 'usuarios'], true)) {
    $tab = 'usuarios';
}
$qsBase = 'modo=' . urlencode($modoFiltro);

$pageTitle = 'Pagos';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Pagos</h1>
  <div class="btn-group btn-group-sm" role="group">
    <a href="?tab=<?= $tab ?>&modo=reales" class="btn <?= $modoFiltro === 'reales' ? 'btn-primary' : 'btn-outline-primary' ?>">Reales</a>
    <a href="?tab=<?= $tab ?>&modo=prueba" class="btn <?= $modoFiltro === 'prueba' ? 'btn-primary' : 'btn-outline-primary' ?>">🧪 Prueba</a>
    <a href="?tab=<?= $tab ?>&modo=todos" class="btn <?= $modoFiltro === 'todos' ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
  </div>
</div>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'usuarios' ? 'active' : '' ?>" href="?tab=usuarios&<?= $qsBase ?>">
      <i class="bi bi-people"></i> Usuarios
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'pendientes' ? 'active' : '' ?>" href="?tab=pendientes&<?= $qsBase ?>">
      <i class="bi bi-hourglass-split"></i> Pendientes
      <?php if ($pendientes): ?><span class="badge bg-warning text-dark ms-1"><?= count($pendientes) ?></span><?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'incompletos' ? 'active' : '' ?>" href="?tab=incompletos&<?= $qsBase ?>">
      <i class="bi bi-x-circle"></i> Incompletos
      <?php if ($incompletos): ?><span class="badge bg-secondary ms-1"><?= count($incompletos) ?></span><?php endif; ?>
    </a>
  </li>
</ul>

<?php if ($tab === 'pendientes'): ?>
  <div class="table-responsive">
  <table class="table table-bordered bg-white">
    <thead><tr><th>Usuario</th><th>Tipo</th><th>Artículo</th><th>Cant.</th><th>Monto</th><th>Método</th><th>Fecha</th><th>Estado</th><th>Comprobante/Envío</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($pendientes as $p): ?>
        <?php pf_pintar_fila_pago($p, $estadoBadge, 'tab=pendientes&' . $qsBase); ?>
      <?php endforeach; ?>
      <?php if (!$pendientes): ?><tr><td colspan="10" class="text-muted">No hay transferencias pendientes de revisar <?= $modoFiltro === 'reales' ? 'reales' : ($modoFiltro === 'prueba' ? 'de prueba' : '') ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
<?php elseif ($tab === 'incompletos'): ?>
  <p class="text-muted small">Intentos de pago con tarjeta que el usuario nunca terminó (cerró la pestaña, la tarjeta fue rechazada, etc.). No requieren ninguna decisión — Stripe ya los habría confirmado solo si el cobro se hubiera completado. Puedes eliminarlos para limpiar el historial.</p>
  <div class="table-responsive">
  <table class="table table-bordered bg-white">
    <thead><tr><th>Usuario</th><th>Tipo</th><th>Artículo</th><th>Cant.</th><th>Monto</th><th>Método</th><th>Fecha</th><th>Estado</th><th>Comprobante/Envío</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($incompletos as $p): ?>
        <?php pf_pintar_fila_pago($p, $estadoBadge, 'tab=incompletos&' . $qsBase); ?>
      <?php endforeach; ?>
      <?php if (!$incompletos): ?><tr><td colspan="10" class="text-muted">No hay intentos de pago incompletos <?= $modoFiltro === 'reales' ? 'reales' : ($modoFiltro === 'prueba' ? 'de prueba' : '') ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
<?php else: ?>
  <div class="table-responsive">
  <table class="table table-bordered bg-white">
    <thead><tr><th>Usuario</th><th>Movimientos</th><th>Confirmados</th><th>Total confirmado</th><th>Último movimiento</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($usuariosResumen as $u): ?>
        <tr>
          <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $u['usuario_id'] ?>" target="_blank"><?= htmlspecialchars((string) $u['username_cache']) ?></a><br><span class="text-muted small"><?= htmlspecialchars((string) $u['email_cache']) ?></span></td>
          <td><?= (int) $u['cantidad_movimientos'] ?></td>
          <td><?= (int) $u['confirmados'] ?></td>
          <td>$<?= number_format($u['monto_confirmado'], 2) ?></td>
          <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($u['ultimo_movimiento']))) ?></td>
          <td><a href="pagos_usuario.php?usuario_id=<?= (int) $u['usuario_id'] ?>&<?= $qsBase ?>" class="btn btn-sm btn-outline-primary">Ver todo</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$usuariosResumen): ?><tr><td colspan="6" class="text-muted">No hay usuarios con pagos <?= $modoFiltro === 'reales' ? 'reales' : ($modoFiltro === 'prueba' ? 'de prueba' : '') ?> todavía.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
