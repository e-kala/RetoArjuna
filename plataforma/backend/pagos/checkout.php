<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/item_resolver.php';
require_once __DIR__ . '/stripe_helper.php';
require_login();

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

// El cupón viaja por sesión (P03: debe sobrevivir cualquier navegación entre
// el enlace inicial y el pago), pero un ?cupon= explícito en la URL actual
// siempre pisa el guardado antes (un enlace nuevo gana sobre uno viejo).
$codigoCupon = $_GET['cupon'] ?? ($_SESSION['cupon_pendiente'] ?? null);
if (isset($_GET['cupon'])) {
    guardar_cupon_sesion((string) $_GET['cupon']);
}

$item = resolver_item_pago($conn, $_GET, $usuarioPerfilId, $codigoCupon);
if (!$item || !$item['activo'] || $item['gratuito'] || in_array($item['estado'], ['incluido_membresia', 'exclusivo_bloqueado'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
// Curso/evento que quedó en $0 por cupón/promoción se resuelve en su propia
// página de detalle (mismo botón "Inscribirme gratis" ya construido ahí,
// extendido para aceptar cupón) — checkout.php solo maneja el caso de
// producto, que no tiene ese botón todavía (ver más abajo).
if ($item['acceso_gratis_automatico'] && $item['tipo'] !== 'producto') {
    header('Location: ' . BASE_URL . '/index.php?action=' . $item['tipo'] . '&slug=' . urlencode($item['slug']));
    exit;
}

function destino_tras_pago(array $item): string
{
    if ($item['tipo'] === 'curso') {
        return BASE_URL . '/index.php?action=curso&slug=' . urlencode($item['slug']) . '&bienvenida=1';
    }
    if ($item['tipo'] === 'evento') {
        return BASE_URL . '/index.php?action=evento&slug=' . urlencode($item['slug']) . '&pago=ok';
    }
    return BASE_URL . '/panel/index.php?action=mis_compras&pago=ok';
}

if ($item['ya_tiene_acceso']) {
    header('Location: ' . destino_tras_pago($item));
    exit;
}

// Stripe.js confirmPayment() regresa aquí (redirect por defecto es 'always') con
// estos parámetros en la URL. redirect_status=succeeded es la propia confirmación
// de Stripe de que el cobro se completó — no hace falta esperar pasivamente al
// webhook payment_intent.succeeded (que puede tardar, o en local de plano nunca
// llega si no hay un endpoint de Stripe configurado): se verifica de una vez
// contra la API de Stripe, fuente de verdad inmediata, y se confirma el pago
// aquí mismo si ya se cobró (mismo patrón que membresia.php). El webhook se
// queda como red de seguridad para cuando el usuario cierra la pestaña antes
// de volver a esta página.
if (isset($_GET['payment_intent']) && ($_GET['redirect_status'] ?? '') === 'succeeded') {
    $intentId = (string) $_GET['payment_intent'];
    $resIntent = stripe_api('GET', 'payment_intents/' . urlencode($intentId));
    if ($resIntent['ok'] && ($resIntent['data']['status'] ?? '') === 'succeeded') {
        $stmt = $conn->prepare(
            "UPDATE pagos SET estado = 'confirmado', fecha_pago = NOW(), fecha_validacion = NOW()
             WHERE transaccion_id = ? AND metodo_pago = 'stripe' AND estado <> 'confirmado'"
        );
        $stmt->bind_param('s', $intentId);
        $stmt->execute();
        $afectados = $stmt->affected_rows;
        $stmt->close();

        if ($afectados > 0) {
            $stmtU = $conn->prepare('SELECT email_cache FROM usuarios_perfil WHERE id = ? LIMIT 1');
            $stmtU->bind_param('i', $usuarioPerfilId);
            $stmtU->execute();
            $filaUsuario = $stmtU->get_result()->fetch_assoc();
            $stmtU->close();
            if ($filaUsuario && $filaUsuario['email_cache']) {
                $enlace = SITE_URL . '/index.php?action=' . $item['tipo'] . '&slug=' . urlencode($item['slug']);
                if ($item['tipo'] === 'curso') {
                    $enlace .= '&bienvenida=1';
                }
                enviar_email_inscripcion($usuarioPerfilId, $filaUsuario['email_cache'], $item['titulo'], $enlace);
            }
        }
    }
    header('Location: ' . destino_tras_pago($item));
    exit;
}

$stripeListo = config_esta_lista(STRIPE_PUBLISHABLE_KEY) && config_esta_lista(STRIPE_SECRET_KEY);
$paramName = $item['tipo'] . '_id';
$publishableKeyActiva = stripe_publishable_key_activa();
$etiquetaTipo = ['curso' => 'Curso', 'evento' => 'Evento', 'producto' => 'Producto'][$item['tipo']] ?? 'Compra';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comprar · <?= htmlspecialchars($item['titulo']) ?> · Reto Arjuna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../../assets/css/platform.css?v=2.8">
  <?php if ($stripeListo): ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
  <style>
    body.pf-checkout-body { background: var(--pf-bg); min-height: 100vh; }
    .pf-checkout-wrap { max-width: 560px; margin: 56px auto; padding: 0 16px; }
    .pf-checkout-card {
      background: var(--pf-surface);
      border-radius: var(--pf-radius-lg);
      box-shadow: 0 16px 40px rgba(35, 38, 43, 0.10);
      overflow: hidden;
    }
    .pf-checkout-img { width: 100%; height: 200px; object-fit: cover; display: block; }
    .pf-checkout-body-pad { padding: 32px 32px 28px; }
    .pf-checkout-desc { color: var(--pf-muted); font-size: 15px; margin-bottom: 24px; }
    .pf-checkout-precio-box {
      background: rgba(247, 147, 30, 0.08);
      border: 1px solid rgba(247, 147, 30, 0.25);
      border-radius: var(--pf-radius-md);
      padding: 16px 20px;
      text-align: center;
      margin-bottom: 24px;
    }
    .pf-checkout-precio-tachado { color: var(--pf-muted); text-decoration: line-through; font-size: 16px; margin-right: 8px; }
    .pf-checkout-precio { color: var(--pf-accent-ink); font-size: 32px; font-weight: 800; }
    .pf-checkout-precio-sufijo { font-size: 14px; font-weight: 600; color: var(--pf-muted); }
    .pf-checkout-confianza {
      display: flex;
      justify-content: center;
      gap: 18px;
      flex-wrap: wrap;
      color: var(--pf-muted);
      font-size: 12.5px;
      margin: 20px 0 4px;
    }
    .pf-checkout-confianza span { display: inline-flex; align-items: center; gap: 6px; }
    .pf-checkout-confianza i { color: var(--pf-accent); }
    .pf-checkout-card .nav-tabs { border-bottom: 1px solid var(--pf-line); }
    .pf-checkout-card .nav-tabs .nav-link { color: var(--pf-muted); font-weight: 700; border: none; border-bottom: 2px solid transparent; }
    .pf-checkout-card .nav-tabs .nav-link.active { color: var(--pf-ink); border-bottom-color: var(--pf-accent); background: transparent; }
    /* pf-dropzone (platform.css) está pensado para fondos oscuros (pf-callout) —
       aquí la tarjeta es clara, así que se invierten sus colores. */
    .pf-checkout-card .pf-dropzone { border-color: var(--pf-line); color: var(--pf-muted); }
    .pf-checkout-card .pf-dropzone:hover,
    .pf-checkout-card .pf-dropzone:focus-visible { border-color: var(--pf-ink); background: rgba(35,38,43,0.03); }
    .pf-checkout-card .pf-dropzone.pf-dropzone-activo { background: rgba(247,147,30,0.08); }
    .pf-checkout-card .pf-dropzone-archivo { color: var(--pf-ink); }
  </style>
</head>
<body class="pf-body pf-checkout-body">
  <?= stripe_modo_prueba_banner_html() ?>
  <div class="pf-checkout-wrap">
    <div class="pf-checkout-card">
      <?php if (!empty($item['imagen'])): ?>
        <img src="<?= htmlspecialchars(BASE_URL . '/' . $item['imagen']) ?>" class="pf-checkout-img" alt="">
      <?php endif; ?>
      <div class="pf-checkout-body-pad">
        <span class="pf-eyebrow"><?= htmlspecialchars($etiquetaTipo) ?></span>
        <h1 class="h4 mt-2 mb-2" style="font-weight:800;"><?= htmlspecialchars($item['titulo']) ?></h1>
        <?php if (!empty($item['descripcion'])): ?>
          <p class="pf-checkout-desc"><?= nl2br(htmlspecialchars($item['descripcion'])) ?></p>
        <?php endif; ?>

        <div class="pf-checkout-precio-box">
          <div>
            <span id="precioTachado" class="pf-checkout-precio-tachado<?= $item['estado'] === 'oferta' ? '' : ' d-none' ?>"><?= $item['estado'] === 'oferta' ? '$' . number_format($item['precio_regular'], 2) : '' ?></span>
            <span id="precioMostrado" class="pf-checkout-precio">$<?= number_format($item['precio_final'], 2) ?></span>
            <span class="pf-checkout-precio-sufijo"> MXN<?= $item['tipo'] === 'producto' ? ' c/u' : ' · pago único' ?></span>
          </div>
          <?php if ($item['estado'] === 'oferta'): ?>
            <div id="pf-desglose" class="small text-muted mt-2">
              <div>Precio regular: $<?= number_format($item['precio_regular'], 2) ?></div>
              <div><span id="descNombreOferta"><?= htmlspecialchars((string) $item['oferta_nombre']) ?></span>: −$<?= number_format($item['descuento_monto'], 2) ?></div>
              <div class="fw-bold">Total: $<?= number_format($item['precio_final'], 2) ?></div>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($item['mostrar_codigo_promocion']): ?>
          <div class="input-group input-group-sm mb-3">
            <input type="text" id="codigoCupon" class="form-control" placeholder="Código de cupón" autocomplete="off" value="<?= htmlspecialchars((string) $codigoCupon) ?>">
            <button class="btn btn-outline-secondary" type="button" id="btnAplicarCupon">Aplicar</button>
          </div>
          <div id="cuponMsg" class="form-text mb-2<?= $item['cupon_error'] ? ' text-danger' : '' ?>"><?= htmlspecialchars((string) $item['cupon_error']) ?></div>
        <?php endif; ?>

        <?php if ($item['tipo'] === 'producto' && !$item['acceso_gratis_automatico']): ?>
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

        <?php if ($item['acceso_gratis_automatico']): ?>
          <!-- Producto en $0 por cupón/promoción: se salta Stripe por completo
               (no acepta PaymentIntents de $0) y se registra el pago directo. -->
          <button class="pf-btn pf-btn-primary pf-btn-lg w-100" id="btnObtenerGratis">Obtener gratis</button>
          <div id="gratisMsg" class="form-text mt-2"></div>
        <?php else: ?>
        <ul class="nav nav-tabs justify-content-center mb-3">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-stripe">Tarjeta</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-transfer">Transferencia</button></li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="tab-stripe">
            <?php if ($stripeListo): ?>
              <div id="payment-element" class="mb-3"></div>
              <button class="pf-btn pf-btn-primary pf-btn-lg w-100" id="btnPagarStripe">Pagar con tarjeta</button>
              <div id="stripeMsg" class="form-text text-danger"></div>
              <div class="pf-checkout-confianza">
                <span><i class="bi bi-shield-check"></i> Pago seguro</span>
                <span><i class="bi bi-lock-fill"></i> Datos cifrados</span>
                <span><i class="bi bi-credit-card-2-front"></i> Visa · Mastercard · Amex</span>
              </div>
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
            <div class="mb-3">
              <label class="form-label">Sube tu comprobante (opcional)</label>
              <div id="comprobanteDropzone" class="pf-dropzone" tabindex="0" role="button">
                <i class="bi bi-cloud-arrow-up"></i>
                <span id="comprobanteDropzoneTexto">Arrastra tu comprobante aquí o haz clic para buscarlo</span>
              </div>
              <input type="file" id="comprobanteFile" class="d-none" accept="image/png,image/jpeg,image/webp,application/pdf">
              <input type="file" id="comprobanteFileCamara" class="d-none" accept="image/*" capture="environment">
              <button type="button" id="btnTomarFotoComprobante" class="btn btn-link btn-sm p-0 mt-2 d-md-none">
                <i class="bi bi-camera"></i> O toma una foto desde tu celular
              </button>
            </div>
            <button class="pf-btn pf-btn-primary pf-btn-lg w-100 mb-2" id="btnYaTransferi">Ya realicé la transferencia</button>
            <a class="btn btn-outline-success w-100" target="_blank"
               href="https://wa.me/<?= htmlspecialchars(WHATSAPP_PAGOS) ?>?text=<?= urlencode('Hola, envío mi comprobante de ' . $item['titulo']) ?>">
              Enviar comprobante por WhatsApp
            </a>
            <div id="transferMsg" class="form-text"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="../../content/notify.min.js"></script>
  <script>
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
    const PARAM_NAME = <?= json_encode($paramName) ?>;
    const ITEM_ID = <?= (int) $item['id'] ?>;
    const CODIGO_REGALO = <?= json_encode((string) ($_GET['regalo'] ?? '')) ?>;

    function datosBase() {
      const datos = { [PARAM_NAME]: ITEM_ID, csrf_token: CSRF_TOKEN };
      const cantidadEl = document.getElementById('cantidad');
      if (cantidadEl) datos.cantidad = cantidadEl.value;
      const direccionEl = document.getElementById('direccionEnvio');
      if (direccionEl) datos.direccion_envio = direccionEl.value;
      const cuponEl = document.getElementById('codigoCupon');
      if (cuponEl && cuponEl.value.trim()) datos.codigo_cupon = cuponEl.value.trim();
      if (CODIGO_REGALO) datos.regalo = CODIGO_REGALO;
      return datos;
    }

    const btnObtenerGratis = document.getElementById('btnObtenerGratis');
    if (btnObtenerGratis) {
      btnObtenerGratis.addEventListener('click', async function () {
        this.disabled = true;
        const msg = document.getElementById('gratisMsg');
        try {
          const res = await fetch('./obtener_gratis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(datosBase()),
          });
          const data = await res.json();
          if (data.success) {
            window.location.href = data.redirect || '<?= htmlspecialchars(BASE_URL) ?>/panel/index.php?action=mis_compras&pago=ok';
          } else {
            msg.textContent = data.message || 'No se pudo completar.';
            msg.className = 'form-text text-danger mt-2';
            this.disabled = false;
          }
        } catch (e) {
          msg.textContent = 'Error de conexión. Intenta de nuevo.';
          msg.className = 'form-text text-danger mt-2';
          this.disabled = false;
        }
      });
    }

    (function () {
      const dropzone = document.getElementById('comprobanteDropzone');
      const dropzoneTexto = document.getElementById('comprobanteDropzoneTexto');
      const inputArchivo = document.getElementById('comprobanteFile');
      const inputCamara = document.getElementById('comprobanteFileCamara');
      const btnCamara = document.getElementById('btnTomarFotoComprobante');
      const textoOriginal = dropzoneTexto.textContent;

      function mostrarArchivoElegido(nombre) {
        dropzoneTexto.textContent = nombre;
        dropzoneTexto.classList.add('pf-dropzone-archivo');
      }

      function ponerArchivoEnInput(input, file) {
        const transferencia = new DataTransfer();
        transferencia.items.add(file);
        input.files = transferencia.files;
      }

      dropzone.addEventListener('click', () => inputArchivo.click());
      dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); inputArchivo.click(); }
      });
      dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('pf-dropzone-activo'); });
      dropzone.addEventListener('dragleave', () => dropzone.classList.remove('pf-dropzone-activo'));
      dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('pf-dropzone-activo');
        const archivo = e.dataTransfer.files[0];
        if (archivo) {
          ponerArchivoEnInput(inputArchivo, archivo);
          mostrarArchivoElegido(archivo.name);
        }
      });
      inputArchivo.addEventListener('change', () => {
        if (inputArchivo.files[0]) {
          mostrarArchivoElegido(inputArchivo.files[0].name);
        } else {
          dropzoneTexto.textContent = textoOriginal;
          dropzoneTexto.classList.remove('pf-dropzone-archivo');
        }
      });

      btnCamara.addEventListener('click', () => inputCamara.click());
      inputCamara.addEventListener('change', () => {
        const foto = inputCamara.files[0];
        if (foto) {
          ponerArchivoEnInput(inputArchivo, foto);
          mostrarArchivoElegido(foto.name);
        }
      });
    })();

    document.getElementById('btnYaTransferi').addEventListener('click', async function () {
      this.disabled = true;
      const msg = document.getElementById('transferMsg');
      const archivo = document.getElementById('comprobanteFile').files[0];
      const datos = new FormData();
      const base = datosBase();
      Object.keys(base).forEach((clave) => datos.append(clave, base[clave]));
      if (archivo) datos.append('comprobante_file', archivo);
      try {
        const res = await fetch('./transferencia.php', { method: 'POST', body: datos });
        const data = await res.json();
        msg.textContent = data.success
          ? 'Registrado. Confirmaremos tu acceso en cuanto validemos el depósito.'
          : (data.message || 'No se pudo registrar tu pago.');
        msg.className = data.success ? 'form-text text-success' : 'form-text text-danger';
        if (data.success) {
          $.notify('Comprobante registrado — confirmaremos tu acceso en cuanto validemos el depósito.', { className: 'success', position: 'top right', autoHideDelay: 4000 });
        } else {
          this.disabled = false;
        }
      } catch (e) {
        msg.textContent = 'Error de conexión. Intenta de nuevo.';
        msg.className = 'form-text text-danger';
        this.disabled = false;
      }
    });

    <?php if ($stripeListo && !$item['acceso_gratis_automatico']): ?>
    const stripe = Stripe(<?= json_encode($publishableKeyActiva) ?>);
    let elements = null;
    let paymentIntentId = null;

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
      paymentIntentId = data.payment_intent_id;
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

    <?php if ($item['mostrar_codigo_promocion']): ?>
    document.getElementById('btnAplicarCupon').addEventListener('click', async function () {
      const codigo = document.getElementById('codigoCupon').value.trim();
      const cuponMsg = document.getElementById('cuponMsg');
      cuponMsg.className = 'form-text mb-2';
      if (!codigo) {
        cuponMsg.textContent = 'Escribe un código de cupón.';
        cuponMsg.classList.add('text-danger');
        return;
      }
      this.disabled = true;
      const datos = datosBase();
      datos.codigo_cupon = codigo;
      if (typeof paymentIntentId !== 'undefined' && paymentIntentId) datos.payment_intent_id = paymentIntentId;
      try {
        const res = await fetch('./aplicar_cupon.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(datos),
        });
        const data = await res.json();
        if (data.success) {
          const formatoMXN = (n) => '$' + n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          document.getElementById('precioTachado').textContent = formatoMXN(data.monto_original);
          document.getElementById('precioTachado').classList.remove('d-none');
          document.getElementById('precioMostrado').textContent = formatoMXN(data.monto_final);
          cuponMsg.textContent = '¡Cupón aplicado! Descuento de ' + formatoMXN(data.descuento) + '.';
          cuponMsg.classList.add('text-success');
          document.getElementById('codigoCupon').disabled = true;
          if (typeof elements !== 'undefined' && elements && elements.fetchUpdates) {
            elements.fetchUpdates();
          }
          if (data.recargar) window.location.reload();
        } else {
          cuponMsg.textContent = data.message || 'No se pudo aplicar el cupón.';
          cuponMsg.classList.add('text-danger');
          this.disabled = false;
        }
      } catch (e) {
        cuponMsg.textContent = 'Error de conexión. Intenta de nuevo.';
        cuponMsg.classList.add('text-danger');
        this.disabled = false;
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>
