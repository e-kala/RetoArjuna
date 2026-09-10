<?php
// Monitoreo de inactividad de cuentas — fecha de registro, última sesión
// iniciada (ver login_user() en auth.php, que llena `ultimo_login` en cada
// login nativo/Google) y tiempo de inactividad calculado. Ordenado por más
// inactivo primero, para que sea fácil detectar cuentas dormidas.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');

$tipo = $_GET['tipo'] ?? 'todos';
if (!in_array($tipo, ['todos', 'reales', 'prueba'], true)) {
    $tipo = 'todos';
}
$condicion = $tipo === 'reales' ? 'WHERE es_prueba = 0' : ($tipo === 'prueba' ? 'WHERE es_prueba = 1' : '');

$usuarios = $conn->query(
    "SELECT id, username_cache, email_cache, rol, es_prueba, created_at, ultimo_login
     FROM usuarios_perfil
     {$condicion}
     ORDER BY COALESCE(ultimo_login, created_at) ASC"
)->fetch_all(MYSQLI_ASSOC);

$ahora = time();
foreach ($usuarios as &$u) {
    // Sin ultimo_login todavía: puede ser una cuenta genuinamente nueva que
    // nunca ha iniciado sesión, O una cuenta vieja de antes de que existiera
    // esta columna — no hay forma de distinguir cuál es cuál con los datos
    // que hay, así que el aviso de abajo cubre ambos casos a la vez.
    $referencia = $u['ultimo_login'] ?? $u['created_at'];
    $u['dias_inactivo'] = (int) floor(($ahora - strtotime($referencia)) / 86400);
    $u['nunca_registrado_login'] = $u['ultimo_login'] === null;
}
unset($u);

$pageTitle = 'Inactividad de usuarios';
include __DIR__ . '/_header.php';
?>
<p class="text-muted small">
  Ordenado del más inactivo al más reciente. "Última sesión" viene de un contador que se agregó recientemente —
  cualquier cuenta que diga "Nunca" pudo haber usado la plataforma antes, solo que fue antes de que existiera este
  contador; a partir de ahora sí queda registrado en cada inicio de sesión.
</p>
<div class="btn-group mb-3" role="group">
  <a href="?tipo=todos" class="btn btn-sm <?= $tipo === 'todos' ? 'btn-dark' : 'btn-outline-dark' ?>">Todos</a>
  <a href="?tipo=reales" class="btn btn-sm <?= $tipo === 'reales' ? 'btn-dark' : 'btn-outline-dark' ?>">Reales</a>
  <a href="?tipo=prueba" class="btn btn-sm <?= $tipo === 'prueba' ? 'btn-dark' : 'btn-outline-dark' ?>">Prueba</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Correo</th><th>Rol</th><th>Registrado</th><th>Última sesión</th><th>Inactividad</th></tr></thead>
  <tbody>
    <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><?= htmlspecialchars((string) $u['username_cache']) ?><?= (int) $u['es_prueba'] === 1 ? ' <span class="badge bg-warning">Prueba</span>' : '' ?></td>
        <td><?= htmlspecialchars((string) $u['email_cache']) ?></td>
        <td><?= htmlspecialchars($u['rol']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($u['created_at']))) ?></td>
        <td>
          <?php if ($u['nunca_registrado_login']): ?>
            <span class="text-muted">Nunca</span>
          <?php else: ?>
            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($u['ultimo_login']))) ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($u['dias_inactivo'] >= 30): ?>
            <span class="badge bg-danger"><?= $u['dias_inactivo'] ?> días</span>
          <?php elseif ($u['dias_inactivo'] >= 7): ?>
            <span class="badge bg-warning text-dark"><?= $u['dias_inactivo'] ?> días</span>
          <?php else: ?>
            <span class="badge bg-success"><?= $u['dias_inactivo'] ?> días</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$usuarios): ?><tr><td colspan="6" class="text-muted">No hay usuarios que coincidan.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
