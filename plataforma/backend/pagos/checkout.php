<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/item_resolver.php';
require_login();

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$item = resolver_item_pago($conn, $_GET, $usuarioPerfilId);
if (!$item || !$item['activo'] || $item['gratuito']) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function destino_tras_pago(array $item): string
{
    if ($item['tipo'] === 'curso') {
        return BASE_URL . '/index.php?action=curso&slug=' . urlencode($item['slug']) . '&bienvenida=1';
    }
    if ($item['tipo'] === 'evento') {
        return BASE_URL . '/index.php?action=evento&slug=' . urlencode($item['slug']);
    }
    return BASE_URL . '/panel/index.php?action=mis_compras&pago=ok';
}

if ($item['ya_tiene_acceso']) {
    header('Location: ' . destino_tras_pago($item));
    exit;
}

// Stripe.js confirmPayment() regresa aquí (redirect por defecto es 'always') con
// estos parámetros en la URL. redirect_status=succeeded es la propia confirmación
// de Stripe de que el cobro se completó — no depende de que el webhook ya haya
// corrido, así que sirve para redirigir de inmediato aunque el webhook (que es
// quien de verdad marca `pagos.estado = 'confirmado'` y libera el acceso) tarde
// unos segundos más en llegar.
if (isset($_GET['payment_intent']) && ($_GET['redirect_status'] ?? '') === 'succeeded') {
    header('Location: ' . destino_tras_pago($item));
    exit;
}

$stripeListo = config_esta_lista(STRIPE_PUBLISHABLE_KEY) && config_esta_lista(STRIPE_SECRET_KEY);
$paramName = $item['tipo'] . '_id';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comprar · Reto Arjuna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php if ($stripeListo): ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
</head>
<body style="background:#f5f5f5;">
  <div class="container" style="max-width:640px;margin-top:60px;margin-bottom:60px;">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h4 mb-1"><?= htmlspecialchars($item['titulo']) ?></h1>
        <p class="text-muted mb-4">$<?= number_format($item['precio'], 2) ?> MXN<?= $item['tipo'] === 'producto' ? ' c/u' : '' ?></p>

        <?php if ($item['tipo'] === 'producto'): ?>
          <div class="mb-3">
            <label class="form-label">Cantidad</label>
            <input type="number" id="cantidad" class="form-control" value="1" min="1" style="max-width:120px;">
          </div>
        <?php endif; ?>

        <?php if ($item['es_fisico']): ?>
          <div class="mb-4">
            <label class="form-label">Dirección de envío</label>
            <textarea id="direccionEnvio" class="form-control" rows="3" placeholder="Calle, número, colonia, ciudad, CP"></textarea>
          </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-3">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-stripe">Tarjeta</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-transfer">Transferencia</button></li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="tab-stripe">
            <?php if ($stripeListo): ?>
              <div id="payment-element" class="mb-3"></div>
              <button class="btn w-100" style="background:#f7931e;color:#fff;" id="btnPagarStripe">Pagar con tarjeta</button>
              <div id="stripeMsg" class="form-text text-danger"></div>
            <?php else: ?>
              <p class="text-muted">Los pagos con tarjeta aún no están configurados. Usa la pestaña de transferencia.</p>
            <?php endif; ?>
          </div>
          <div class="tab-pane fade" id="tab-transfer">
            <p>Realiza tu depósito o transferencia a:</p>
            <ul>
              <li><strong>Banco:</strong> <?= htmlspecialchars(BANCO_NOMBRE) ?></li>
              <li><strong>CLABE:</strong> <?= htmlspecialchars(BANCO_CLABE) ?></li>
              <li><strong>Titular:</strong> <?= htmlspecialchars(BANCO_TITULAR) ?></li>
            </ul>
            <button class="btn w-100 mb-2" style="background:#f7931e;color:#fff;" id="btnYaTransferi">Ya realicé la transferencia</button>
            <a class="btn btn-outline-success w-100" target="_blank"
               href="https://wa.me/<?= htmlspecialchars(WHATSAPP_PAGOS) ?>?text=<?= urlencode('Hola, envío mi comprobante de ' . $item['titulo']) ?>">
              Enviar comprobante por WhatsApp
            </a>
            <div id="transferMsg" class="form-text"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
    const PARAM_NAME = <?= json_encode($paramName) ?>;
    const ITEM_ID = <?= (int) $item['id'] ?>;

    function datosBase() {
      const datos = { [PARAM_NAME]: ITEM_ID, csrf_token: CSRF_TOKEN };
      const cantidadEl = document.getElementById('cantidad');
      if (cantidadEl) datos.cantidad = cantidadEl.value;
      const direccionEl = document.getElementById('direccionEnvio');
      if (direccionEl) datos.direccion_envio = direccionEl.value;
      return datos;
    }

    document.getElementById('btnYaTransferi').addEventListener('click', async () => {
      const res = await fetch('./transferencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(datosBase()),
      });
      const data = await res.json();
      const msg = document.getElementById('transferMsg');
      msg.textContent = data.success
        ? 'Registrado. Confirmaremos tu acceso en cuanto validemos el depósito.'
        : (data.message || 'No se pudo registrar tu pago.');
      msg.className = data.success ? 'form-text text-success' : 'form-text text-danger';
    });

    <?php if ($stripeListo): ?>
    const stripe = Stripe(<?= json_encode(STRIPE_PUBLISHABLE_KEY) ?>);
    let elements = null;

    async function iniciarStripe() {
      const res = await fetch('./stripe_create_intent.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(datosBase()),
      });
      const data = await res.json();
      const stripeMsg = document.getElementById('stripeMsg');
      if (!data.success) {
        stripeMsg.textContent = data.message || 'No se pudo iniciar el pago.';
        return;
      }
      elements = stripe.elements({ clientSecret: data.client_secret });
      elements.create('payment').mount('#payment-element');
    }
    iniciarStripe();

    document.getElementById('btnPagarStripe').addEventListener('click', async () => {
      if (!elements) return;
      const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: { return_url: window.location.href },
      });
      if (error) {
        document.getElementById('stripeMsg').textContent = error.message;
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>
