<?php $usuario = current_user(); ?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Perfil</h1>
</div>

<div class="container">
    <div id="perfilMsg" class="alert d-none"></div>
    <form id="perfilForm" class="row">
        <div class="col-sm-6 mb-3">
            <label class="form-label">Usuario</label>
            <input type="text" class="form-control" id="username" name="username"
                   value="<?= htmlspecialchars((string) $usuario['username']) ?>" required>
        </div>
        <div class="col-sm-6 mb-3">
            <label class="form-label">Correo</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= htmlspecialchars((string) $usuario['email']) ?>" required>
        </div>
        <div class="col-sm-6 mb-3">
            <label class="form-label">WhatsApp / teléfono</label>
            <input type="tel" class="form-control" id="telefono" name="telefono"
                   value="<?= htmlspecialchars((string) ($usuario['telefono'] ?? '')) ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-success">Guardar cambios</button>
        </div>
    </form>

    <hr class="my-4">
    <h2 class="h5">Cambiar contraseña</h2>
    <div id="passwordMsg" class="alert d-none"></div>
    <form id="passwordForm" class="row">
        <div class="col-sm-4 mb-3">
            <label class="form-label">Contraseña actual</label>
            <input type="password" class="form-control" id="password_actual" name="password_actual">
        </div>
        <div class="col-sm-4 mb-3">
            <label class="form-label">Contraseña nueva</label>
            <input type="password" class="form-control" id="password_nueva" name="password_nueva">
        </div>
        <div class="col-sm-4 mb-3">
            <label class="form-label">Confirma la nueva</label>
            <input type="password" class="form-control" id="password_nueva_confirm" name="password_nueva_confirm">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-outline-dark">Cambiar contraseña</button>
        </div>
    </form>
</div>

<script>
  document.getElementById('perfilForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    const username = document.getElementById('username').value;
    const email = document.getElementById('email').value;
    const telefono = document.getElementById('telefono').value;
    const res = await fetch('../backend/perfil_actualizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ username, email, telefono, csrf_token: <?= json_encode(csrf_token()) ?> }),
    });
    const data = await res.json();
    const msg = document.getElementById('perfilMsg');
    msg.textContent = data.success ? 'Datos actualizados.' : (data.message || 'No se pudo actualizar.');
    msg.className = data.success ? 'alert alert-success' : 'alert alert-danger';
  });

  document.getElementById('passwordForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    const password_actual = document.getElementById('password_actual').value;
    const password_nueva = document.getElementById('password_nueva').value;
    const password_nueva_confirm = document.getElementById('password_nueva_confirm').value;
    const res = await fetch('../backend/perfil_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ password_actual, password_nueva, password_nueva_confirm, csrf_token: <?= json_encode(csrf_token()) ?> }),
    });
    const data = await res.json();
    const msg = document.getElementById('passwordMsg');
    msg.textContent = data.success ? 'Contraseña actualizada.' : (data.message || 'No se pudo actualizar.');
    msg.className = data.success ? 'alert alert-success' : 'alert alert-danger';
    if (data.success) {
      document.getElementById('passwordForm').reset();
    }
  });
</script>
