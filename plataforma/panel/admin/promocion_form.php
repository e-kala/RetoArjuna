<?php
// Promoción pública temporal (checklist.txt P01/OF07/ADM02) — a diferencia
// de contenido_form.php (que separa curso/evento porque esos campos SÍ
// difieren), aquí los campos son idénticos sin importar a qué tipo de ítem
// aplique, así que un solo <select> con "tipo:id" (poblado por UNION de los
// 4 catálogos) es más simple que 4 sub-formularios.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$promo = [
    'curso_id' => null, 'evento_id' => null, 'producto_id' => null, 'membresia_id' => null,
    'nombre' => '', 'modalidad' => 'promocion', 'tipo_descuento' => 'porcentaje', 'valor' => '',
    'fecha_inicio' => '', 'fecha_fin' => '', 'combinable' => 0, 'activo' => 1,
];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM promociones WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc() ?: $promo;
    $stmt->close();
}

// Ítems disponibles para el <select> — solo activos, cada uno con su tipo
// codificado en el value ("curso:5") para partirlo al guardar.
$opciones = [];
foreach ($conn->query('SELECT id, titulo AS nombre FROM cursos WHERE activo = 1 ORDER BY titulo') as $r) {
    $opciones[] = ['valor' => 'curso:' . $r['id'], 'texto' => '🎓 ' . $r['nombre']];
}
foreach ($conn->query('SELECT id, titulo AS nombre FROM eventos WHERE activo = 1 ORDER BY titulo') as $r) {
    $opciones[] = ['valor' => 'evento:' . $r['id'], 'texto' => '📅 ' . $r['nombre']];
}
foreach ($conn->query('SELECT id, nombre FROM productos WHERE activo = 1 ORDER BY nombre') as $r) {
    $opciones[] = ['valor' => 'producto:' . $r['id'], 'texto' => '🛍️ ' . $r['nombre']];
}
foreach ($conn->query('SELECT id, nombre FROM membresias WHERE activo = 1 ORDER BY nombre') as $r) {
    $opciones[] = ['valor' => 'membresia:' . $r['id'], 'texto' => '⭐ ' . $r['nombre']];
}

$aplicaActual = '';
foreach (['curso_id', 'evento_id', 'producto_id', 'membresia_id'] as $col) {
    if (!empty($promo[$col])) {
        $aplicaActual = str_replace('_id', '', $col) . ':' . $promo[$col];
        break;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $aplica = trim($_POST['aplica'] ?? '');
    [$tipoAplica, $idAplica] = array_pad(explode(':', $aplica, 2), 2, null);
    $idAplica = $idAplica !== null ? (int) $idAplica : 0;

    $nombre = trim($_POST['nombre'] ?? '');
    $modalidad = in_array($_POST['modalidad'] ?? '', ['promocion', 'preventa'], true) ? $_POST['modalidad'] : 'promocion';
    $tipoDescuento = in_array($_POST['tipo_descuento'] ?? '', ['porcentaje', 'precio_fijo'], true) ? $_POST['tipo_descuento'] : 'porcentaje';
    $valor = (float) ($_POST['valor'] ?? 0);
    $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
    $fechaFin = trim($_POST['fecha_fin'] ?? '');
    $combinable = isset($_POST['combinable']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    $cursoId = $tipoAplica === 'curso' ? $idAplica : null;
    $eventoId = $tipoAplica === 'evento' ? $idAplica : null;
    $productoId = $tipoAplica === 'producto' ? $idAplica : null;
    $membresiaId = $tipoAplica === 'membresia' ? $idAplica : null;

    if ($nombre === '' || !$idAplica || $fechaInicio === '' || $fechaFin === '') {
        $error = 'Nombre, a qué aplica, y las fechas de inicio/fin son obligatorios.';
    } elseif ($fechaFin <= $fechaInicio) {
        $error = 'La fecha de fin debe ser posterior a la de inicio.';
    } else {
        if ($id) {
            $stmt = $conn->prepare('UPDATE promociones SET curso_id=?, evento_id=?, producto_id=?, membresia_id=?, nombre=?, modalidad=?, tipo_descuento=?, valor=?, fecha_inicio=?, fecha_fin=?, combinable=?, activo=? WHERE id=?');
            $stmt->bind_param('iiiisssdssiii', $cursoId, $eventoId, $productoId, $membresiaId, $nombre, $modalidad, $tipoDescuento, $valor, $fechaInicio, $fechaFin, $combinable, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO promociones (curso_id, evento_id, producto_id, membresia_id, nombre, modalidad, tipo_descuento, valor, fecha_inicio, fecha_fin, combinable, activo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iiiisssdssii', $cursoId, $eventoId, $productoId, $membresiaId, $nombre, $modalidad, $tipoDescuento, $valor, $fechaInicio, $fechaFin, $combinable, $activo);
        }
        if ($stmt->execute()) {
            if ($esAjax) {
                echo json_encode(['success' => true, 'redirect' => 'promociones.php']);
                exit;
            }
            header('Location: promociones.php');
            exit;
        }
        $error = 'No se pudo guardar — revisa los datos.';
        $stmt->close();
    }
    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$pageTitle = $id ? 'Editar promoción' : 'Nueva promoción';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" data-ajax-form>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-8">
    <label class="form-label">Aplica a</label>
    <select class="form-select" name="aplica" required>
      <option value="">— Elige un curso, evento, producto o membresía —</option>
      <?php foreach ($opciones as $op): ?>
        <option value="<?= htmlspecialchars($op['valor']) ?>" <?= $op['valor'] === $aplicaActual ? 'selected' : '' ?>><?= htmlspecialchars($op['texto']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Modalidad</label>
    <select class="form-select" name="modalidad">
      <option value="promocion" <?= $promo['modalidad'] === 'promocion' ? 'selected' : '' ?>>Promoción</option>
      <option value="preventa" <?= $promo['modalidad'] === 'preventa' ? 'selected' : '' ?>>Preventa</option>
    </select>
  </div>
  <div class="col-12"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= htmlspecialchars($promo['nombre']) ?>" placeholder="Ej. Promoción Reto Arjuna -40%" required>
    <div class="form-text">Se muestra tal cual en el desglose del checkout.</div>
  </div>
  <div class="col-md-4">
    <label class="form-label">Tipo de descuento</label>
    <select class="form-select" name="tipo_descuento" id="tipo_descuento">
      <option value="porcentaje" <?= $promo['tipo_descuento'] === 'porcentaje' ? 'selected' : '' ?>>Porcentaje (%)</option>
      <option value="precio_fijo" <?= $promo['tipo_descuento'] === 'precio_fijo' ? 'selected' : '' ?>>Precio final fijo (MXN)</option>
    </select>
  </div>
  <div class="col-md-4"><label class="form-label" id="label_valor"><?= $promo['tipo_descuento'] === 'precio_fijo' ? 'Precio final (MXN)' : 'Porcentaje de descuento' ?></label><input type="number" step="0.01" min="0" class="form-control" name="valor" id="valor" value="<?= htmlspecialchars((string) $promo['valor']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Fecha y hora de inicio</label><input type="datetime-local" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $promo['fecha_inicio'], 0, 16))) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Fecha y hora de fin</label><input type="datetime-local" class="form-control" name="fecha_fin" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $promo['fecha_fin'], 0, 16))) ?>" required></div>
  <div class="col-md-6 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="combinable" id="combinable" <?= (int) $promo['combinable'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="combinable">Se puede combinar con un cupón (si no, gana el mejor precio de los dos)</label>
  </div>
  <div class="col-md-6 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="activo" id="activo" <?= (int) $promo['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Activa</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<script>
  document.getElementById('tipo_descuento').addEventListener('change', function () {
    document.getElementById('label_valor').textContent = this.value === 'precio_fijo' ? 'Precio final (MXN)' : 'Porcentaje de descuento';
  });
</script>
<?php include __DIR__ . '/_footer.php'; ?>
