<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$cupon = ['codigo' => '', 'tipo' => 'porcentaje', 'valor' => 0, 'vigencia_desde' => '', 'vigencia_hasta' => '', 'uso_maximo' => '', 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM cupones WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $cupon = $stmt->get_result()->fetch_assoc() ?: $cupon;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $tipo = $_POST['tipo'] ?? 'porcentaje';
    $valor = (float) ($_POST['valor'] ?? 0);
    $desde = $_POST['vigencia_desde'] !== '' ? $_POST['vigencia_desde'] : null;
    $hasta = $_POST['vigencia_hasta'] !== '' ? $_POST['vigencia_hasta'] : null;
    $usoMaximo = $_POST['uso_maximo'] !== '' ? (int) $_POST['uso_maximo'] : null;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($codigo === '') {
        $error = 'El código es obligatorio.';
    } else {
        if ($id) {
            $stmt = $conn->prepare('UPDATE cupones SET codigo=?, tipo=?, valor=?, vigencia_desde=?, vigencia_hasta=?, uso_maximo=?, activo=? WHERE id=?');
            $stmt->bind_param('ssdssiii', $codigo, $tipo, $valor, $desde, $hasta, $usoMaximo, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO cupones (codigo, tipo, valor, vigencia_desde, vigencia_hasta, uso_maximo, activo) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('ssdssii', $codigo, $tipo, $valor, $desde, $hasta, $usoMaximo, $activo);
        }
        if ($stmt->execute()) {
            header('Location: cupones.php');
            exit;
        }
        $error = '¿El código ya existe?';
        $stmt->close();
    }
}

$pageTitle = $id ? 'Editar cupón' : 'Nuevo cupón';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-6"><label class="form-label">Código</label><input class="form-control text-uppercase" name="codigo" value="<?= htmlspecialchars($cupon['codigo']) ?>" required></div>
  <div class="col-md-6">
    <label class="form-label">Tipo</label>
    <select class="form-select" name="tipo">
      <option value="porcentaje" <?= $cupon['tipo'] === 'porcentaje' ? 'selected' : '' ?>>Porcentaje</option>
      <option value="monto_fijo" <?= $cupon['tipo'] === 'monto_fijo' ? 'selected' : '' ?>>Monto fijo</option>
    </select>
  </div>
  <div class="col-md-4"><label class="form-label">Valor</label><input type="number" step="0.01" class="form-control" name="valor" value="<?= htmlspecialchars((string) $cupon['valor']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Vigente desde</label><input type="date" class="form-control" name="vigencia_desde" value="<?= htmlspecialchars((string) $cupon['vigencia_desde']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Vigente hasta</label><input type="date" class="form-control" name="vigencia_hasta" value="<?= htmlspecialchars((string) $cupon['vigencia_hasta']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Usos máximos (vacío = sin límite)</label><input type="number" class="form-control" name="uso_maximo" value="<?= htmlspecialchars((string) $cupon['uso_maximo']) ?>"></div>
  <div class="col-md-6 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $cupon['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Activo</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
