<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$membresia = ['nombre' => '', 'descripcion' => '', 'precio' => 0, 'intervalo' => 'mensual',
              'stripe_price_id' => '', 'orden' => 0, 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM membresias WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $membresia = $stmt->get_result()->fetch_assoc() ?: $membresia;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float) ($_POST['precio'] ?? 0);
    $intervalo = ($_POST['intervalo'] ?? 'mensual') === 'anual' ? 'anual' : 'mensual';
    $stripePriceId = trim($_POST['stripe_price_id'] ?? '') ?: null;
    $orden = (int) ($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        if ($id) {
            $stmt = $conn->prepare('UPDATE membresias SET nombre=?, descripcion=?, precio=?, intervalo=?, stripe_price_id=?, orden=?, activo=? WHERE id=?');
            $stmt->bind_param('ssdssiii', $nombre, $descripcion, $precio, $intervalo, $stripePriceId, $orden, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO membresias (nombre, descripcion, precio, intervalo, stripe_price_id, orden, activo) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('ssdssii', $nombre, $descripcion, $precio, $intervalo, $stripePriceId, $orden, $activo);
        }
        $stmt->execute();
        $stmt->close();
        header('Location: membresias.php');
        exit;
    }
}

$pageTitle = $id ? 'Editar membresía' : 'Nueva membresía';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-8"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= htmlspecialchars($membresia['nombre']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Orden</label><input type="number" class="form-control" name="orden" value="<?= (int) $membresia['orden'] ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $membresia['descripcion']) ?></textarea></div>
  <div class="col-md-4"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $membresia['precio']) ?>"></div>
  <div class="col-md-4">
    <label class="form-label">Intervalo</label>
    <select class="form-select" name="intervalo">
      <option value="mensual" <?= $membresia['intervalo'] === 'mensual' ? 'selected' : '' ?>>Mensual</option>
      <option value="anual" <?= $membresia['intervalo'] === 'anual' ? 'selected' : '' ?>>Anual</option>
    </select>
  </div>
  <div class="col-md-4 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $membresia['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Visible en la página de venta</label>
  </div>
  <div class="col-12">
    <label class="form-label">Stripe Price ID</label>
    <input class="form-control" name="stripe_price_id" value="<?= htmlspecialchars((string) $membresia['stripe_price_id']) ?>" placeholder="price_...">
    <div class="form-text">Créalo en tu <a href="https://dashboard.stripe.com/products" target="_blank">Dashboard de Stripe</a> (Producto recurrente → Price) y pega aquí su id. Sin esto, los usuarios no pueden suscribirse todavía.</div>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
