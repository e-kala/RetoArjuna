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
    } elseif (($_POST['accion'] ?? '') === 'toggle_prueba' && $id > 0) {
        // Marca/desmarca una cuenta como de prueba — solo etiqueta, no afecta acceso.
        $stmt = $conn->prepare('UPDATE usuarios_perfil SET es_prueba = 1 - es_prueba WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif (($_POST['accion'] ?? '') === 'quitar_membresia' && $id > 0) {
        $suscripcionId = (int) ($_POST['suscripcion_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE membresia_suscripciones SET estado = 'cancelada' WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param('ii', $suscripcionId, $id);
        $stmt->execute();
        $stmt->close();
    }
    $qRedirect = trim($_POST['q'] ?? '');
    $tipoRedirect = trim($_POST['tipo'] ?? '');
    $params = [];
    if ($qRedirect !== '') {
        $params[] = 'q=' . urlencode($qRedirect);
    }
    if ($tipoRedirect !== '' && $tipoRedirect !== 'todos') {
        $params[] = 'tipo=' . urlencode($tipoRedirect);
    }
    header('Location: usuarios.php' . ($params ? '?' . implode('&', $params) : ''));
    exit;
}

if (!empty($_SESSION['usuarios_admin_password_temporal'])) {
    $passwordGenerada = $_SESSION['usuarios_admin_password_temporal'];
    unset($_SESSION['usuarios_admin_password_temporal']);
}

require __DIR__ . '/_usuarios_query.php';
$listaMembresiasModal = $membresiasDisponibles;

// El aviso de contraseña temporal necesita poder buscar por id sin importar
// el filtro de búsqueda actual, por eso se resuelve aparte de $usuarios.
$usuariosPorId = [];
if (!empty($_SESSION['usuarios_admin_password_temporal'])) {
    foreach ($conn->query('SELECT id, username_cache FROM usuarios_perfil')->fetch_all(MYSQLI_ASSOC) as $u) {
        $usuariosPorId[(int) $u['id']] = $u;
    }
}

$pageTitle = 'Usuarios';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <h1 class="h4">Usuarios</h1>
  <a href="usuario_form.php" class="btn btn-success btn-sm">+ Nuevo usuario</a>
</div>
<form method="get" id="usuariosBuscarForm" class="mb-3 d-flex gap-2 flex-wrap" style="max-width:560px;">
  <input type="search" name="q" id="usuariosBuscarQ" class="form-control" placeholder="Buscar por usuario o correo" value="<?= htmlspecialchars($busqueda) ?>" style="max-width:280px;" autocomplete="off">
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
  <button class="btn btn-outline-secondary">Buscar</button>
  <?php if ($busqueda !== ''): ?><a href="usuarios.php<?= $tipo !== 'todos' ? '?tipo=' . urlencode($tipo) : '' ?>" class="btn btn-outline-secondary">Limpiar</a><?php endif; ?>
  <span id="usuariosBuscarCargando" class="spinner-border spinner-border-sm text-secondary align-self-center" style="display:none;" aria-hidden="true"></span>
</form>
<div class="btn-group mb-3" role="group">
  <a href="?tipo=todos<?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>" class="btn btn-sm <?= $tipo === 'todos' ? 'btn-dark' : 'btn-outline-dark' ?>">Todos</a>
  <a href="?tipo=reales<?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>" class="btn btn-sm <?= $tipo === 'reales' ? 'btn-dark' : 'btn-outline-dark' ?>">Reales</a>
  <a href="?tipo=prueba<?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>" class="btn btn-sm <?= $tipo === 'prueba' ? 'btn-dark' : 'btn-outline-dark' ?>">Prueba</a>
</div>
<p class="text-muted small">Usuario, correo, rol, membresía y contraseña se administran aquí directamente. Marca "Prueba" en cuentas dummy/testing para separarlas de las reales.</p>

<?php if ($passwordGenerada && isset($usuariosPorId[$passwordGenerada['id']])): ?>
  <div class="alert alert-warning">
    Contraseña temporal para <strong><?= htmlspecialchars($usuariosPorId[$passwordGenerada['id']]['username_cache']) ?></strong>:
    <code><?= htmlspecialchars($passwordGenerada['password']) ?></code>
    — cópiala ahora, no se volverá a mostrar. Pídele a la persona que la cambie desde su perfil.
  </div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Correo</th><th>Registrado</th><th>Tipo</th><th>Rol</th><th>Membresía</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody id="usuariosTbody">
    <?php include __DIR__ . '/_usuarios_filas.php'; ?>
  </tbody>
</table>
</div>
<script>
(function () {
  var form = document.getElementById('usuariosBuscarForm');
  var input = document.getElementById('usuariosBuscarQ');
  var tbody = document.getElementById('usuariosTbody');
  var cargando = document.getElementById('usuariosBuscarCargando');
  var timer = null;
  var ultimaPeticion = 0;

  form.addEventListener('submit', function (e) { e.preventDefault(); });

  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(buscar, 250);
  });

  function buscar() {
    var idPeticion = ++ultimaPeticion;
    cargando.style.display = 'inline-block';
    var params = new URLSearchParams(new FormData(form));
    fetch('usuarios_buscar.php?' + params.toString())
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (idPeticion !== ultimaPeticion) return; // respuesta obsoleta, llegó otra después
        tbody.innerHTML = html;
      })
      .finally(function () {
        if (idPeticion === ultimaPeticion) cargando.style.display = 'none';
      });
  }
})();
</script>
<?php include __DIR__ . '/_membresia_modal.php'; ?>
<?php
// Llega aquí después de crear un usuario en usuario_form.php con "asignar
// membresía" marcado — abre el mismo modal compartido ya con ese usuario
// elegido, en vez de duplicar los campos de membresía en ese formulario.
$otorgarMembresiaId = (int) ($_GET['otorgar_membresia_id'] ?? 0);
if ($otorgarMembresiaId > 0):
    $volverQueryStr = http_build_query(array_filter([
        'q' => $busqueda !== '' ? $busqueda : null,
        'tipo' => $tipo !== 'todos' ? $tipo : null,
    ]));
    ?>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    abrirMembresiaModal({
      titulo: 'Otorgar membresía',
      usuarioId: <?= $otorgarMembresiaId ?>,
      usuarioLabel: <?= json_encode($_GET['otorgar_membresia_label'] ?? '') ?>,
      volver: 'usuarios.php',
      volverQuery: <?= json_encode($volverQueryStr) ?>
    });
    if (window.history.replaceState) {
      window.history.replaceState(null, '', 'usuarios.php<?= $volverQueryStr !== '' ? '?' . $volverQueryStr : '' ?>');
    }
  });
  </script>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
