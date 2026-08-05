<?php
// Gestiona identidad (usuario/correo/rol/contraseña) de usuarios_perfil, ahora la
// única fuente de verdad de credenciales de toda la plataforma.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$miId = (int) $_SESSION['usuario_perfil_id'];
$passwordGenerada = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id !== $miId) {
        if (($_POST['accion'] ?? '') === 'cambiar_rol') {
            $rol = $_POST['rol'] ?? 'estudiante';
            if (in_array($rol, ['estudiante', 'instructor', 'admin'], true)) {
                $stmt = $conn->prepare('UPDATE usuarios_perfil SET rol = ? WHERE id = ?');
                $stmt->bind_param('si', $rol, $id);
                $stmt->execute();
                $stmt->close();
            }
        } elseif (($_POST['accion'] ?? '') === 'toggle_activo') {
            $stmt = $conn->prepare('UPDATE usuarios_perfil SET activo = 1 - activo WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        } elseif (($_POST['accion'] ?? '') === 'eliminar_usuario') {
            $stmt = $conn->prepare('DELETE FROM usuarios_perfil WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    if (($_POST['accion'] ?? '') === 'resetear_password' && $id > 0) {
        // Se permite resetear la propia contraseña también, por eso esta va fuera del bloque de arriba.
        $temporal = bin2hex(random_bytes(5));
        $hash = password_hash($temporal, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE usuarios_perfil SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['usuarios_admin_password_temporal'] = ['id' => $id, 'password' => $temporal];
    }
    header('Location: usuarios.php');
    exit;
}

if (!empty($_SESSION['usuarios_admin_password_temporal'])) {
    $passwordGenerada = $_SESSION['usuarios_admin_password_temporal'];
    unset($_SESSION['usuarios_admin_password_temporal']);
}

$usuarios = $conn->query('SELECT * FROM usuarios_perfil ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);
$usuariosPorId = [];
foreach ($usuarios as $u) {
    $usuariosPorId[(int) $u['id']] = $u;
}

$pageTitle = 'Usuarios';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Usuarios</h1>
<p class="text-muted small">Usuario, correo, rol y contraseña se administran aquí directamente.</p>

<?php if ($passwordGenerada && isset($usuariosPorId[$passwordGenerada['id']])): ?>
  <div class="alert alert-warning">
    Contraseña temporal para <strong><?= htmlspecialchars($usuariosPorId[$passwordGenerada['id']]['username_cache']) ?></strong>:
    <code><?= htmlspecialchars($passwordGenerada['password']) ?></code>
    — cópiala ahora, no se volverá a mostrar. Pídele a la persona que la cambie desde su perfil.
  </div>
<?php endif; ?>

<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Correo</th><th>Registrado</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><?= htmlspecialchars((string) $u['username_cache']) ?></td>
        <td><?= htmlspecialchars((string) $u['email_cache']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($u['created_at']))) ?></td>
        <td>
          <?php if ((int) $u['id'] === $miId): ?>
            <?= htmlspecialchars($u['rol']) ?> <span class="text-muted small">(tú)</span>
          <?php else: ?>
            <form method="post" class="d-flex gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="accion" value="cambiar_rol">
              <select name="rol" class="form-select form-select-sm">
                <?php foreach (['estudiante', 'instructor', 'admin'] as $r): ?>
                  <option value="<?= $r ?>" <?= $u['rol'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-primary">Guardar</button>
            </form>
          <?php endif; ?>
        </td>
        <td><?= (int) $u['activo'] === 1 ? 'Activo' : 'Deshabilitado' ?></td>
        <td class="d-flex gap-2">
          <?php if ((int) $u['id'] !== $miId): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="accion" value="toggle_activo">
              <button class="btn btn-sm btn-outline-secondary"><?= (int) $u['activo'] === 1 ? 'Deshabilitar' : 'Habilitar' ?></button>
            </form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('¿Generar una contraseña temporal para este usuario?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="hidden" name="accion" value="resetear_password">
            <button class="btn btn-sm btn-outline-dark">Resetear contraseña</button>
          </form>
          <?php if ((int) $u['id'] !== $miId): ?>
            <form method="post" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars($u['username_cache'], ENT_QUOTES) ?> permanentemente? Se borrarán también sus pagos, progreso, certificados y publicaciones del foro. Esto no se puede deshacer.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="accion" value="eliminar_usuario">
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
