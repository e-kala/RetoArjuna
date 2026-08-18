<?php
// Alta manual de usuarios desde el admin (p. ej. gente que se inscribe sin pasar
// por el registro público). La contraseña la define el admin aquí mismo; el
// usuario puede cambiarla después desde su perfil (backend/perfil_password.php).
//
// La membresía NO se pide aquí con campos propios — eso sería la misma forma
// duplicada por segunda vez (ver _membresia_modal.php). En vez de eso, si se
// marca "asignar membresía", se redirige a usuarios.php que abre el modal
// compartido ya con este usuario elegido.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$hayMembresias = (bool) $conn->query('SELECT id FROM membresias WHERE activo = 1 LIMIT 1')->fetch_assoc();

$error = '';
$valores = ['username' => '', 'email' => '', 'rol' => 'estudiante', 'es_prueba' => 0, 'asignar_membresia' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = $_POST['rol'] ?? 'estudiante';
    $password = (string) ($_POST['password'] ?? '');
    $esPrueba = isset($_POST['es_prueba']) ? 1 : 0;
    $asignarMembresia = isset($_POST['asignar_membresia']);
    $valores = ['username' => $username, 'email' => $email, 'rol' => $rol, 'es_prueba' => $esPrueba, 'asignar_membresia' => $asignarMembresia ? 1 : 0];

    if (!in_array($rol, ['estudiante', 'instructor', 'admin'], true)) {
        $rol = 'estudiante';
    }

    if ($username === '' || $email === '' || $password === '') {
        $error = 'Completa todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no es válido.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'La contraseña debe tener al menos 8 caracteres, con letras y números.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM usuarios_perfil WHERE username_cache = ? OR email_cache = ? LIMIT 1');
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existente) {
            $error = 'Ese usuario o correo ya está registrado.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'INSERT INTO usuarios_perfil (username_cache, email_cache, password_hash, rol, es_prueba, activo) VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->bind_param('ssssi', $username, $email, $hash, $rol, $esPrueba);
            $stmt->execute();
            $nuevoUsuarioId = $stmt->insert_id;
            $stmt->close();

            if ($asignarMembresia && $hayMembresias) {
                header(
                    'Location: usuarios.php?otorgar_membresia_id=' . $nuevoUsuarioId
                    . '&otorgar_membresia_label=' . urlencode($username . ' — ' . $email)
                );
                exit;
            }

            header('Location: usuarios.php');
            exit;
        }
    }
}

$pageTitle = 'Nuevo usuario';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Nuevo usuario</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" style="max-width:560px;">
  <?= csrf_field() ?>
  <div class="col-12"><label class="form-label">Usuario</label><input class="form-control" name="username" value="<?= htmlspecialchars($valores['username']) ?>" required></div>
  <div class="col-12"><label class="form-label">Correo</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($valores['email']) ?>" required></div>
  <div class="col-md-6">
    <label class="form-label">Rol</label>
    <select class="form-select" name="rol">
      <?php foreach (['estudiante', 'instructor', 'admin'] as $r): ?>
        <option value="<?= $r ?>" <?= $valores['rol'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="es_prueba" id="es_prueba" <?= $valores['es_prueba'] ? 'checked' : '' ?>>
    <label class="form-check-label" for="es_prueba">Cuenta de prueba (no es un usuario real)</label>
  </div>
  <div class="col-12">
    <label class="form-label">Contraseña inicial</label>
    <input type="text" class="form-control" name="password" placeholder="Al menos 8 caracteres, con letras y números" required>
    <div class="form-text">Compártela con la persona — podrá cambiarla después desde su perfil.</div>
  </div>
  <?php if ($hayMembresias): ?>
    <div class="col-12 form-check">
      <input type="checkbox" class="form-check-input" name="asignar_membresia" id="asignar_membresia" <?= $valores['asignar_membresia'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="asignar_membresia">👑 Asignar membresía a este usuario (se abre el formulario de membresía justo después de crearlo)</label>
    </div>
  <?php endif; ?>
  <div class="col-12">
    <button class="btn btn-success">Crear usuario</button>
    <a href="usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
