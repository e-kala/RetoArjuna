<?php
// Cupón interno (checklist.txt P02/OF08/ADM03/ADM04) — calculado en la
// plataforma, no vía Stripe Promotion Codes. "Usar como incentivo por
// creación de cuenta" (P04/ADM04) reusa este mismo sistema: es un cupón
// normal con origen='incentivo_cuenta_nueva', no un mecanismo aparte.
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$cupon = [
    'codigo' => '', 'tipo_descuento' => 'porcentaje', 'valor' => '',
    'fecha_inicio' => '', 'fecha_fin' => '', 'usos_totales' => '',
    'vigencia_tipo' => 'siempre', 'vigencia_meses' => '',
    'combinable' => 0, 'origen' => 'admin', 'activo' => 1,
];
$alcanceActual = [];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM cupones WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $cupon = $stmt->get_result()->fetch_assoc() ?: $cupon;
    $stmt->close();

    $stmt = $conn->prepare('SELECT curso_id, evento_id, producto_id, membresia_id FROM cupon_alcance WHERE cupon_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    foreach ($stmt->get_result() as $fila) {
        foreach (['curso_id' => 'curso', 'evento_id' => 'evento', 'producto_id' => 'producto', 'membresia_id' => 'membresia'] as $col => $tipo) {
            if (!empty($fila[$col])) {
                $alcanceActual[] = $tipo . ':' . $fila[$col];
            }
        }
    }
    $stmt->close();
}

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

$error = '';
$aviso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $tipoDescuento = in_array($_POST['tipo_descuento'] ?? '', ['porcentaje', 'monto'], true) ? $_POST['tipo_descuento'] : 'porcentaje';
    $valor = (float) ($_POST['valor'] ?? 0);
    $combinable = isset($_POST['combinable']) ? 1 : 0;
    $origen = isset($_POST['es_incentivo_cuenta_nueva']) ? 'incentivo_cuenta_nueva' : 'admin';
    $activo = isset($_POST['activo']) ? 1 : 0;
    $alcanceElegido = $_POST['alcance'] ?? [];

    // Vigencia: el selector de 3 modalidades reemplaza la edición manual de
    // fecha_inicio/fecha_fin/usos_totales — 'siempre' y 'meses' dejan las
    // fechas del cupón sin restricción (para 'meses', la repetición mensual
    // la controla Stripe en la suscripción de cada usuario, no una fecha
    // fija del código, ver membresia_iniciar.php); 'una_vez' fuerza
    // usos_totales=1, mismo mecanismo que ya validaba ofertas.php.
    $vigenciaTipo = in_array($_POST['vigencia_tipo'] ?? '', ['siempre', 'meses', 'una_vez'], true) ? $_POST['vigencia_tipo'] : 'siempre';
    $vigenciaMeses = $vigenciaTipo === 'meses' ? max(1, (int) ($_POST['vigencia_meses'] ?? 0)) : null;
    $fechaInicio = null;
    $fechaFin = null;
    $usosTotales = $vigenciaTipo === 'una_vez' ? 1 : null;

    if ($codigo === '' || $valor <= 0) {
        $error = 'Código y valor del descuento son obligatorios.';
    } elseif ($vigenciaTipo === 'meses' && !$vigenciaMeses) {
        $error = 'Indica cuántos meses dura el descuento.';
    } else {
        if ($id) {
            $stmt = $conn->prepare('UPDATE cupones SET codigo=?, tipo_descuento=?, valor=?, fecha_inicio=?, fecha_fin=?, usos_totales=?, vigencia_tipo=?, vigencia_meses=?, combinable=?, origen=?, activo=? WHERE id=?');
            $stmt->bind_param('ssdssisiisii', $codigo, $tipoDescuento, $valor, $fechaInicio, $fechaFin, $usosTotales, $vigenciaTipo, $vigenciaMeses, $combinable, $origen, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO cupones (codigo, tipo_descuento, valor, fecha_inicio, fecha_fin, usos_totales, vigencia_tipo, vigencia_meses, combinable, origen, activo) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('ssdssisiisi', $codigo, $tipoDescuento, $valor, $fechaInicio, $fechaFin, $usosTotales, $vigenciaTipo, $vigenciaMeses, $combinable, $origen, $activo);
        }
        if ($stmt->execute()) {
            $id = $id ?: $stmt->insert_id;
            $stmt->close();

            // Alcance: reemplazo total (borrar todo, volver a insertar) —
            // más simple que calcular el diff, y esta tabla nunca es grande.
            $stmt = $conn->prepare('DELETE FROM cupon_alcance WHERE cupon_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            foreach ($alcanceElegido as $valorAlcance) {
                [$tipoAlcance, $idAlcance] = array_pad(explode(':', (string) $valorAlcance, 2), 2, null);
                if (!$idAlcance || !in_array($tipoAlcance, ['curso', 'evento', 'producto', 'membresia'], true)) {
                    continue;
                }
                $columna = $tipoAlcance . '_id';
                $stmt = $conn->prepare("INSERT INTO cupon_alcance (cupon_id, {$columna}) VALUES (?, ?)");
                $stmt->bind_param('ii', $id, $idAlcance);
                $stmt->execute();
                $stmt->close();
            }

            if ($esAjax) {
                echo json_encode(['success' => true, 'redirect' => 'cupones.php']);
                exit;
            }
            header('Location: cupones.php');
            exit;
        }
        $error = '¿El código ya existe? Prueba con otro.';
        $stmt->close();
    }
    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$pageTitle = $id ? 'Editar cupón' : 'Nuevo cupón';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="alert alert-success"><?= htmlspecialchars($aviso) ?></div><?php endif; ?>
<form method="post" class="row g-3" data-ajax-form>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-6">
    <label class="form-label">Código</label>
    <input class="form-control text-uppercase" name="codigo" id="codigo" value="<?= htmlspecialchars($cupon['codigo']) ?>" placeholder="BIENVENIDA20" required>
  </div>
  <div class="col-md-3">
    <label class="form-label">Tipo de descuento</label>
    <select class="form-select" name="tipo_descuento" id="tipo_descuento">
      <option value="porcentaje" <?= $cupon['tipo_descuento'] === 'porcentaje' ? 'selected' : '' ?>>Porcentaje (%)</option>
      <option value="monto" <?= $cupon['tipo_descuento'] === 'monto' ? 'selected' : '' ?>>Monto fijo (MXN)</option>
    </select>
  </div>
  <div class="col-md-3"><label class="form-label" id="label_valor"><?= $cupon['tipo_descuento'] === 'monto' ? 'Monto (MXN)' : 'Porcentaje' ?></label><input type="number" step="0.01" min="0" class="form-control" name="valor" id="valor" value="<?= htmlspecialchars((string) $cupon['valor']) ?>" required></div>

  <div class="col-md-6">
    <label class="form-label">Vigencia</label>
    <select class="form-select" name="vigencia_tipo" id="vigencia_tipo">
      <option value="siempre" <?= $cupon['vigencia_tipo'] === 'siempre' ? 'selected' : '' ?>>Para siempre</option>
      <option value="meses" <?= $cupon['vigencia_tipo'] === 'meses' ? 'selected' : '' ?>>Temporal — por X meses</option>
      <option value="una_vez" <?= $cupon['vigencia_tipo'] === 'una_vez' ? 'selected' : '' ?>>Un solo uso</option>
    </select>
  </div>
  <div class="col-md-6" id="campoVigenciaMeses" style="<?= $cupon['vigencia_tipo'] === 'meses' ? '' : 'display:none;' ?>">
    <label class="form-label">Duración (meses)</label>
    <input type="number" min="1" step="1" class="form-control" name="vigencia_meses" value="<?= htmlspecialchars((string) $cupon['vigencia_meses']) ?>" placeholder="Ej. 3">
  </div>
  <div class="form-text col-12 mt-0" id="ayudaVigencia">
    <span data-vigencia-ayuda="siempre" <?= $cupon['vigencia_tipo'] !== 'siempre' ? 'hidden' : '' ?>>Sin fecha de vencimiento ni límite de usos.</span>
    <span data-vigencia-ayuda="meses" <?= $cupon['vigencia_tipo'] !== 'meses' ? 'hidden' : '' ?>>En una membresía, el descuento se aplica a las primeras N mensualidades de cada quien se suscriba con este cupón (contadas desde su propia inscripción, no desde una fecha fija). En una compra única (curso/evento/producto) no hay mensualidades, así que ahí el cupón simplemente no tiene fecha de vencimiento.</span>
    <span data-vigencia-ayuda="una_vez" <?= $cupon['vigencia_tipo'] !== 'una_vez' ? 'hidden' : '' ?>>Se desactiva solo después de usarse una vez en total, sin importar quién lo use.</span>
  </div>

  <div class="col-md-6 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="combinable" id="combinable" <?= (int) $cupon['combinable'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="combinable">Se puede combinar con una promoción pública vigente</label>
  </div>
  <div class="col-md-6 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="es_incentivo_cuenta_nueva" id="es_incentivo_cuenta_nueva" <?= $cupon['origen'] === 'incentivo_cuenta_nueva' ? 'checked' : '' ?>>
    <label class="form-check-label" for="es_incentivo_cuenta_nueva">Usar como incentivo por creación de Cuenta Arjuna</label>
  </div>
  <div class="col-md-6 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="activo" id="activo" <?= (int) $cupon['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Activo</label>
  </div>

  <div class="col-12">
    <label class="form-label">Aplica a (deja todo sin marcar = cupón global, aplica a cualquier cosa)</label>
    <div class="row">
      <?php foreach ($opciones as $op): ?>
        <div class="col-md-4 form-check">
          <input type="checkbox" class="form-check-input" name="alcance[]" id="alcance_<?= htmlspecialchars($op['valor']) ?>" value="<?= htmlspecialchars($op['valor']) ?>" <?= in_array($op['valor'], $alcanceActual, true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="alcance_<?= htmlspecialchars($op['valor']) ?>"><?= htmlspecialchars($op['texto']) ?></label>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($id): ?>
    <div class="col-12">
      <div class="alert alert-info small mb-0">
        Enlaces promocionales (aplican el cupón automáticamente):
        <ul class="mb-0">
          <?php foreach ($alcanceActual as $valorAlcance): ?>
            <?php
            [$tipoAlcance, $idAlcance] = explode(':', $valorAlcance, 2);
            // membresías no tienen slug propio — solo hay una activa a la vez,
            // membresia.php no recibe ningún identificador en la URL.
            if ($tipoAlcance === 'membresia') {
                echo '<li><code>?action=membresia&amp;cupon=' . htmlspecialchars($cupon['codigo']) . '</code></li>';
                continue;
            }
            $tabla = $tipoAlcance === 'curso' ? 'cursos' : ($tipoAlcance === 'evento' ? 'eventos' : 'productos');
            $stmtSlug = $conn->prepare("SELECT slug FROM {$tabla} WHERE id = ?");
            $stmtSlug->bind_param('i', $idAlcance);
            $stmtSlug->execute();
            $slugFila = $stmtSlug->get_result()->fetch_assoc();
            $stmtSlug->close();
            ?>
            <?php if ($slugFila): ?>
              <li><code>?action=<?= htmlspecialchars($tipoAlcance) ?>&amp;slug=<?= htmlspecialchars($slugFila['slug']) ?>&amp;cupon=<?= htmlspecialchars($cupon['codigo']) ?></code></li>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (!$alcanceActual): ?><li>Cupón global — agrégalo manualmente a cualquier enlace: <code>&amp;cupon=<?= htmlspecialchars($cupon['codigo']) ?></code></li><?php endif; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<script>
  document.getElementById('codigo').addEventListener('blur', function () { this.value = this.value.toUpperCase().trim(); });
  document.getElementById('tipo_descuento').addEventListener('change', function () {
    document.getElementById('label_valor').textContent = this.value === 'monto' ? 'Monto (MXN)' : 'Porcentaje';
  });
  document.getElementById('vigencia_tipo').addEventListener('change', function () {
    document.getElementById('campoVigenciaMeses').style.display = this.value === 'meses' ? '' : 'none';
    document.querySelectorAll('[data-vigencia-ayuda]').forEach(function (el) {
      el.hidden = el.dataset.vigenciaAyuda !== document.getElementById('vigencia_tipo').value;
    });
  });
</script>
<?php include __DIR__ . '/_footer.php'; ?>
