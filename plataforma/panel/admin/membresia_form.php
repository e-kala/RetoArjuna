<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/pagos/stripe_helper.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$membresia = ['nombre' => '', 'descripcion' => '', 'precio' => 0, 'intervalo' => 'mensual',
              'stripe_price_id' => '', 'stripe_price_id_prueba' => '', 'mostrar_codigo_promocion' => 0, 'orden' => 0, 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM membresias WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $membresia = $stmt->get_result()->fetch_assoc() ?: $membresia;
    $stmt->close();
}

$error = '';
$aviso = '';

// Crea el Producto + Price directamente en Stripe en AMBOS modos a la vez (live
// y prueba) en vez de mandar al admin a crearlo a mano en el Dashboard (dos
// veces, alternando el toggle) y volver a pegar cada id — mismo dato
// (nombre/precio/intervalo) que ya está guardado en esta membresía, para que
// no se desincronice. No depende de stripe_modo_prueba_activo(): cada Price se
// crea con su propia llave explícita, sin importar el modo de quien lo pide.
// Solo aplica a una membresía ya guardada (necesita id). El modo prueba se
// omite en silencio si STRIPE_SECRET_KEY_PRUEBA no está configurada en este
// entorno (ej. local antes de definirla) — no es un error, solo no hay nada
// que crear ahí todavía.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_price_stripe' && $id && $membresia['nombre']) {
    $montoCentavos = (int) round(((float) $membresia['precio']) * 100);
    $intervaloStripe = $membresia['intervalo'] === 'anual' ? 'year' : 'month';

    $tareas = [['modo' => 'live', 'llave' => STRIPE_SECRET_KEY, 'columna' => 'stripe_price_id']];
    if (config_esta_lista(STRIPE_SECRET_KEY_PRUEBA)) {
        $tareas[] = ['modo' => 'prueba', 'llave' => STRIPE_SECRET_KEY_PRUEBA, 'columna' => 'stripe_price_id_prueba'];
    }

    $avisos = [];
    $errores = [];
    foreach ($tareas as $tarea) {
        $resProducto = stripe_api('POST', 'products', ['name' => $membresia['nombre']], $tarea['llave']);
        if (!$resProducto['ok'] || empty($resProducto['data']['id'])) {
            $errores[] = 'Producto (' . $tarea['modo'] . '): ' . ($resProducto['data']['error']['message'] ?? 'sin detalle');
            continue;
        }
        $resPrice = stripe_api('POST', 'prices', [
            'product' => $resProducto['data']['id'],
            'unit_amount' => $montoCentavos,
            'currency' => 'mxn',
            'recurring' => ['interval' => $intervaloStripe],
        ], $tarea['llave']);
        if (!$resPrice['ok'] || empty($resPrice['data']['id'])) {
            $errores[] = 'Price (' . $tarea['modo'] . '): ' . ($resPrice['data']['error']['message'] ?? 'sin detalle');
            continue;
        }
        $nuevoPriceId = $resPrice['data']['id'];
        $columna = $tarea['columna'];
        $stmt = $conn->prepare("UPDATE membresias SET {$columna} = ? WHERE id = ?");
        $stmt->bind_param('si', $nuevoPriceId, $id);
        $stmt->execute();
        $stmt->close();
        $membresia[$columna] = $nuevoPriceId;
        $avisos[] = $tarea['modo'] . ': ' . $nuevoPriceId;
    }
    if ($avisos) {
        $aviso = 'Se creó en Stripe y se guardó aquí — ' . implode(' | ', $avisos);
    }
    if ($errores) {
        $error = implode(' | ', $errores);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') !== 'crear_price_stripe') {
    $esAjax = es_peticion_ajax();
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float) ($_POST['precio'] ?? 0);
    $intervalo = ($_POST['intervalo'] ?? 'mensual') === 'anual' ? 'anual' : 'mensual';
    $stripePriceId = trim($_POST['stripe_price_id'] ?? '') ?: null;
    $stripePriceIdPrueba = trim($_POST['stripe_price_id_prueba'] ?? '') ?: null;
    $mostrarCodigoPromocion = isset($_POST['mostrar_codigo_promocion']) ? 1 : 0;
    $orden = (int) ($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        if ($id) {
            $stmt = $conn->prepare('UPDATE membresias SET nombre=?, descripcion=?, precio=?, intervalo=?, stripe_price_id=?, stripe_price_id_prueba=?, mostrar_codigo_promocion=?, orden=?, activo=? WHERE id=?');
            $stmt->bind_param('ssdsssiiii', $nombre, $descripcion, $precio, $intervalo, $stripePriceId, $stripePriceIdPrueba, $mostrarCodigoPromocion, $orden, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO membresias (nombre, descripcion, precio, intervalo, stripe_price_id, stripe_price_id_prueba, mostrar_codigo_promocion, orden, activo) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('ssdsssiii', $nombre, $descripcion, $precio, $intervalo, $stripePriceId, $stripePriceIdPrueba, $mostrarCodigoPromocion, $orden, $activo);
        }
        $stmt->execute();
        $stmt->close();
        if ($esAjax) {
            echo json_encode(['success' => true, 'redirect' => 'membresias.php']);
            exit;
        }
        header('Location: membresias.php');
        exit;
    }
    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$pageTitle = $id ? 'Editar membresía' : 'Nueva membresía';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="alert alert-success"><?= htmlspecialchars($aviso) ?></div><?php endif; ?>
<?php if ($id): ?>
  <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <strong>Crear en Stripe (live + prueba)</strong> — en vez de crearlo a mano en el Dashboard dos veces (alternando el toggle) y pegar cada id, esto crea el Producto + Price en Stripe <em>en ambos modos a la vez</em>, usando el nombre/precio/intervalo <em>ya guardados</em> (si cambiaste algo abajo sin darle "Guardar" todavía, usa lo último guardado, no lo que ves en el formulario). No depende del modo prueba que tengas activo tú ahora mismo.
      <?= config_esta_lista(STRIPE_SECRET_KEY_PRUEBA) ? '' : ' (Este entorno todavía no tiene llave TEST configurada — solo se creará el de live.)' ?>
    </div>
    <form method="post" onsubmit="return confirm('¿Crear un Producto + Price nuevo en Stripe en modo live<?= config_esta_lista(STRIPE_SECRET_KEY_PRUEBA) ? ' y en modo prueba' : '' ?> con el nombre/precio/intervalo ya guardados?<?= ($membresia['stripe_price_id'] || $membresia['stripe_price_id_prueba']) ? ' Ya hay un Price ID guardado — se reemplaza (el anterior queda huérfano en tu cuenta de Stripe, no se borra).' : '' ?>');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="accion" value="crear_price_stripe">
      <button type="submit" class="btn btn-sm btn-primary text-nowrap">Crear en Stripe</button>
    </form>
  </div>
<?php endif; ?>
<form method="post" class="row g-3" data-ajax-form>
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
  <div class="col-md-4 form-check form-switch mt-4">
    <input type="checkbox" class="form-check-input" role="switch" name="activo" id="activo" <?= (int) $membresia['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Visible en la página de venta</label>
  </div>
  <div class="col-12">
    <label class="form-label">Stripe Price ID</label>
    <input class="form-control" name="stripe_price_id" value="<?= htmlspecialchars((string) $membresia['stripe_price_id']) ?>" placeholder="price_...">
    <div class="form-text">Créalo en tu <a href="https://dashboard.stripe.com/products" target="_blank">Dashboard de Stripe</a> (Producto recurrente → Price) y pega aquí su id. Sin esto, los usuarios no pueden suscribirse todavía.</div>
  </div>
  <div class="col-12">
    <label class="form-label">Stripe Price ID (modo prueba)</label>
    <input class="form-control" name="stripe_price_id_prueba" value="<?= htmlspecialchars((string) $membresia['stripe_price_id_prueba']) ?>" placeholder="price_... (creado en modo TEST del Dashboard)">
    <div class="form-text">Solo lo usa un admin con el "modo prueba de Stripe" activado (menú de usuario). Créalo igual que el de arriba pero con el Dashboard en modo TEST. Sin esto, un admin no podrá probar la suscripción a esta membresía en modo prueba.</div>
  </div>
  <div class="col-12 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="mostrar_codigo_promocion" id="mostrar_codigo_promocion" <?= (int) $membresia['mostrar_codigo_promocion'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="mostrar_codigo_promocion">Mostrar campo de "código de cupón" en el checkout de la suscripción</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
