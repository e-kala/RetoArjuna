<?php
require_once __DIR__ . '/../backend/pagos/stripe_helper.php';
require_once __DIR__ . '/../backend/mailer.php';
require_once __DIR__ . '/../backend/ofertas.php';

$membresia = $conn->query('SELECT * FROM membresias WHERE activo = 1 ORDER BY orden ASC LIMIT 1')->fetch_assoc();
$usuario = current_user();
$esMiembro = $usuario ? usuario_tiene_membresia_activa($usuario['id']) : false;

// El cupón viaja por sesión (P03: debe sobrevivir cualquier navegación entre
// el enlace inicial y el pago), pero un ?cupon= explícito en la URL actual
// siempre pisa el guardado antes.
$codigoCupon = $_GET['cupon'] ?? ($_SESSION['cupon_pendiente'] ?? null);
if (isset($_GET['cupon'])) {
    guardar_cupon_sesion((string) $_GET['cupon']);
}

// Un Visitante (sin sesión) nunca debe ver precio ni beneficios de Membresía
// Camino Arjuna (checklist.txt H02/M01) — se le pide cuenta/login primero,
// conservando esta misma página como destino (volver_validado() en auth.php
// valida esa ruta antes de usarla).
if (!$usuario) {
    $volverMembresia = urlencode((string) ($_SERVER['REQUEST_URI'] ?? ''));
    $cuponQS = $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '';
    ?>
    <section class="pf-hero">
      <div class="pf-container">
        <span class="pf-eyebrow">Camino Arjuna</span>
        <h1>Necesitas una Cuenta Arjuna para ver esta sección</h1>
        <p>Crea tu Cuenta Arjuna gratis o inicia sesión para conocer los detalles.</p>
      </div>
    </section>
    <section class="pf-section">
      <div class="pf-container" style="max-width:420px;">
        <div class="card border-0 shadow-sm p-4 text-center">
          <a class="pf-btn pf-btn-primary pf-btn-lg mb-2" href="?action=registro&volver=<?= $volverMembresia . $cuponQS ?>">Crear mi cuenta</a>
          <a class="pf-btn pf-btn-outline" href="?action=ingreso&volver=<?= $volverMembresia . $cuponQS ?>">Ya tengo cuenta, iniciar sesión</a>
        </div>
      </div>
    </section>
    <?php
    return;
}

$resultadoCheckout = $_GET['suscripcion'] ?? '';
if ($resultadoCheckout === '' && isset($_GET['payment_intent'])) {
    $resultadoCheckout = ($_GET['redirect_status'] ?? '') === 'succeeded' ? 'exito' : 'cancelada';
}
$stripeListo = config_esta_lista(STRIPE_PUBLISHABLE_KEY) && config_esta_lista(STRIPE_SECRET_KEY);
$publishableKeyActiva = stripe_publishable_key_activa();

// Al volver de confirmar el pago no hace falta esperar pasivamente al webhook
// invoice.paid (que puede tardar, o en local de plano nunca llega si no hay
// un endpoint de Stripe configurado) — se verifica de una vez contra la API
// de Stripe, fuente de verdad inmediata, y se activa aquí mismo si ya está
// pagada. El webhook se queda como red de seguridad para renovaciones y para
// cuando el usuario cierra la pestaña antes de volver a esta página.
$activacionInstantanea = false;
$activacionFueDePrueba = false;
if ($resultadoCheckout === 'exito' && $usuario && !$esMiembro) {
    $stmt = $conn->prepare(
        "SELECT id, membresia_id, modo, stripe_subscription_id
         FROM membresia_suscripciones
         WHERE usuario_id = ? AND metodo = 'stripe' AND estado = 'pendiente'
         ORDER BY created_at DESC LIMIT 1"
    );
    $usuarioIdVerif = (int) $usuario['id'];
    $stmt->bind_param('i', $usuarioIdVerif);
    $stmt->execute();
    $filaPendienteStripe = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($filaPendienteStripe) {
        $resSub = stripe_api('GET', 'subscriptions/' . urlencode($filaPendienteStripe['stripe_subscription_id']));
        if ($resSub['ok'] && ($resSub['data']['status'] ?? '') === 'active') {
            // current_period_end vive en el subscription item, no en la
            // suscripción misma (cambio reciente de la API de Stripe — ver
            // membresia_iniciar.php).
            $finPeriodoTs = $resSub['data']['items']['data'][0]['current_period_end'] ?? null;
            $finPeriodoFecha = $finPeriodoTs ? date('Y-m-d H:i:s', (int) $finPeriodoTs) : null;

            $stmt = $conn->prepare("UPDATE membresia_suscripciones SET estado = 'activa', periodo_actual_fin = ? WHERE id = ?");
            $stmt->bind_param('si', $finPeriodoFecha, $filaPendienteStripe['id']);
            $stmt->execute();
            $stmt->close();

            // Descarta cualquier OTRO intento de Stripe abandonado de este
            // mismo usuario (ej. cerró el formulario de tarjeta a medias y
            // volvió a intentarlo) — si no, se quedan huérfanos para
            // siempre en "Pendientes de confirmar" (panel/admin/
            // membresias.php), porque nada puede "confirmarlos": Stripe ya
            // los dejó incomplete/expirados, y ahora el usuario de todos
            // modos ya quedó activo por la fila que sí se pagó.
            $stmtLimpia = $conn->prepare(
                "DELETE FROM membresia_suscripciones WHERE usuario_id = ? AND metodo = 'stripe' AND estado = 'pendiente' AND id <> ?"
            );
            $stmtLimpia->bind_param('ii', $usuarioIdVerif, $filaPendienteStripe['id']);
            $stmtLimpia->execute();
            $stmtLimpia->close();

            $stmtM = $conn->prepare('SELECT nombre FROM membresias WHERE id = ?');
            $stmtM->bind_param('i', $filaPendienteStripe['membresia_id']);
            $stmtM->execute();
            $nombreMembresiaActivada = $stmtM->get_result()->fetch_assoc()['nombre'] ?? 'tu membresía';
            $stmtM->close();
            if ($usuario['email']) {
                enviar_email_membresia_activada($usuarioIdVerif, $usuario['email'], $nombreMembresiaActivada);
            }

            // Re-deriva $esMiembro desde la fuente de verdad en vez de
            // asumirlo — usuario_tiene_membresia_activa() ya filtra
            // modo = 'live' (ver auth.php), así que una suscripción de
            // prueba recién "activada" NUNCA debe mostrarse como acceso
            // real, aunque la fila ya diga estado = 'activa'.
            $esMiembro = usuario_tiene_membresia_activa($usuarioIdVerif);
            $activacionInstantanea = true;
            $activacionFueDePrueba = $filaPendienteStripe['modo'] === 'prueba';
        }
    }
}

// Solo una transferencia pendiente bloquea el flujo de compra (mensaje "ya
// registramos tu pago, espera confirmación") — una suscripción de Stripe
// pendiente (creada pero nunca confirmada con el Payment Element, ej. si el
// usuario cerró la pestaña a medias) NO debe bloquear nada: simplemente deja
// que intente de nuevo con el botón normal. Esa fila vieja se descarta sola
// (Stripe expira la factura a las 23h) o un admin la limpia desde
// panel/admin/membresias.php.
$transferenciaPendiente = null;
if ($usuario && !$esMiembro && $membresia) {
    $usuarioIdActual = (int) $usuario['id'];
    $membresiaIdActual = (int) $membresia['id'];
    $stmt = $conn->prepare(
        "SELECT id FROM membresia_suscripciones WHERE usuario_id = ? AND membresia_id = ? AND estado = 'pendiente' AND metodo <> 'stripe' LIMIT 1"
    );
    $stmt->bind_param('ii', $usuarioIdActual, $membresiaIdActual);
    $stmt->execute();
    $transferenciaPendiente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Motor de ofertas (checklist.txt OF01-OF10) — promoción pública/cupón
// también aplican a membresía. Mismo criterio que membresia_iniciar.php:
// incluido_membresia/solo_miembros/descuento_miembro_pct no tienen sentido
// para la membresía misma, se pasan en false/null a propósito.
$oferta = null;
if ($usuario && !$esMiembro && $membresia && !$transferenciaPendiente) {
    $ofertaItem = [
        'id' => $membresia['id'],
        'precio' => (float) $membresia['precio'],
        'gratuito' => false,
        'incluido_membresia' => false,
        'solo_miembros' => false,
        'descuento_miembro_pct' => null,
        'ya_tiene_acceso' => false,
    ];
    $oferta = resolver_oferta($conn, 'membresia', $ofertaItem, $usuario, $codigoCupon);
}
?>
<section class="pf-hero">
  <div class="pf-container">
    <span class="pf-eyebrow">Membresía Camino Arjuna</span>
    <h1>Tu espacio de práctica continua</h1>
    <p>No es un curso con contenido nuevo cada semana — es el lugar al que vuelves,
       una y otra vez, para reorientarte.</p>
  </div>
</section>

<?php if ($resultadoCheckout === 'exito'): ?>
  <?php
  $mensajeToastMembresia = $activacionInstantanea && $activacionFueDePrueba
      ? '🧪 Pago de prueba confirmado — suscripción activa en modo prueba.'
      : ($activacionInstantanea ? '🎉 ¡Listo! Tu membresía ya está activa.' : '🎉 ¡Listo! Tu pago se confirmó.');
  ?>
  <div class="pf-container"><div class="alert alert-success">
    <?php if ($activacionInstantanea && $activacionFueDePrueba): ?>
      🧪 ¡Listo! Se confirmó el pago de prueba y la suscripción quedó activa en modo prueba — no es acceso real (por diseño, para que puedas probar el flujo sin arriesgar contenido de pago).
    <?php elseif ($activacionInstantanea): ?>
      🎉 ¡Listo! Tu membresía ya está activa.
    <?php else: ?>
      🎉 ¡Listo! Tu pago se confirmó — en un momento verás tu acceso activo aquí mismo.
    <?php endif; ?>
  </div></div>
  <script>
    $(function () {
      $.notify(<?= json_encode($mensajeToastMembresia) ?>, { className: 'success', position: 'top right', autoHideDelay: 4000 });
    });
  </script>
<?php elseif ($resultadoCheckout === 'cancelada'): ?>
  <div class="pf-container"><div class="alert alert-warning">No se completó la suscripción. Puedes intentarlo de nuevo cuando quieras.</div></div>
<?php endif; ?>

<?php if (!$membresia): ?>
  <section class="pf-section">
    <div class="pf-container">
      <p style="color:var(--pf-muted);">La membresía todavía no está disponible.</p>
    </div>
  </section>
<?php else: ?>
  <section class="pf-section">
    <div class="pf-container">
      <div class="pf-grid-3">
        <div class="pf-card">
          <div class="pf-card-icon">🧭</div>
          <h3>Reorientación constante</h3>
          <p>Vuelve cuando lo necesites para recordar y practicar el método, no para acumular contenido sin terminar.</p>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">💬</div>
          <h3>Acompañamiento</h3>
          <p>Acceso a la comunidad y al foro, para no practicar el discernimiento en soledad.</p>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">🔑</div>
          <h3>Práctica, no teoría</h3>
          <p>El espacio gira en torno a preguntas y ejercicios reales, no a explicar el sistema desde cero otra vez.</p>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">📅</div>
          <h3>Prioridad en eventos</h3>
          <p>Te enteras primero de los próximos encuentros en línea y presenciales de la comunidad.</p>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">🌱</div>
          <h3>Sin ataduras</h3>
          <p>Cancela o pausa cuando quieras desde tu propio portal — sin llamadas ni trámites.</p>
        </div>
        <div class="pf-card">
          <div class="pf-card-icon">🕉️</div>
          <h3>Un solo camino</h3>
          <p>Todo lo que ya construiste — cursos, foro, reconocimientos — conectado a un mismo hilo de práctica.</p>
        </div>
      </div>
//muestra el pago con tarjeta y transferencia
      <div class="pf-callout" style="margin-top:48px;text-align:center; background: #a0895e;">
        <h2 style="text-align:center;"><?= htmlspecialchars($membresia['nombre']) ?></h2>
        <p style="text-align:center;"><?= nl2br(htmlspecialchars((string) $membresia['descripcion'])) ?></p>
        <div style="display:inline-block;background:rgba(247,147,30,0.14);border:1px solid rgba(247,147,30,0.4);border-radius:16px;padding:14px 28px;margin-bottom:24px;">
          <?php if ($oferta && $oferta['estado'] === 'oferta'): ?>
            <span id="precioTachado" style="font-size:16px;font-weight:600;color:rgba(255,255,255,.6);text-decoration:line-through;margin-right:8px;">$<?= number_format($oferta['precio_regular'], 2) ?></span>
            <span id="precioMostrado" style="font-size:34px;font-weight:800;color:var(--pf-accent);">$<?= number_format($oferta['precio_final'], 2) ?></span>
            <span style="font-size:15px;font-weight:600;color:rgba(255,255,255,.8);"> MXN / <?= $membresia['intervalo'] === 'anual' ? 'año' : 'mes' ?></span>
            <div id="pf-desglose" class="small mt-2" style="color:rgba(255,255,255,.7);">
              <div>Precio regular: $<?= number_format($oferta['precio_regular'], 2) ?></div>
              <div><span id="descNombreOferta"><?= htmlspecialchars((string) $oferta['oferta_nombre']) ?></span>: −$<?= number_format($oferta['descuento_monto'], 2) ?></div>
              <div class="fw-bold">Total: $<?= number_format($oferta['precio_final'], 2) ?></div>
            </div>
          <?php else: ?>
            <span id="precioTachado" class="d-none"></span>
            <span id="precioMostrado" style="font-size:34px;font-weight:800;color:var(--pf-accent);">$<?= number_format((float) $membresia['precio'], 2) ?></span>
            <span style="font-size:15px;font-weight:600;color:rgba(255,255,255,.8);"> MXN / <?= $membresia['intervalo'] === 'anual' ? 'año' : 'mes' ?></span>
          <?php endif; ?>
        </div>

        <?php if ($esMiembro): ?>
          <p style="margin-bottom:16px;text-align:center;">✔ <img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Ya eres miembro de Camino Arjuna.</p>
          <p style="text-align:center;"><a class="pf-btn pf-btn-primary pf-btn-lg" href="backend/pagos/membresia_portal.php">Gestionar mi membresía</a></p>
        <?php elseif (!$usuario): ?>
          <p style="text-align:center;"><a class="pf-btn pf-btn-primary pf-btn-lg" href="?action=registro<?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>">Regístrate para suscribirte</a></p>
        <?php elseif ($transferenciaPendiente): ?>
          <div class="alert alert-warning mb-0">Registramos tu transferencia — en cuanto confirmemos el pago tu membresía queda activa. Si quieres, envía también tu comprobante por WhatsApp para agilizarlo.</div>
        <?php else: ?>
          <ul class="nav nav-tabs justify-content-center mb-3" style="border-color:rgba(255,255,255,.2);">
            <?php if ($stripeListo): ?>
              <li class="nav-item" style="background: #bcb04f;"><button class="nav-link active text-dark" data-bs-toggle="tab" data-bs-target="#tab-tarjeta" type="button">Tarjeta</button></li>
            <?php endif; ?>
            <li class="nav-item" style="background: #bcb04f;"><button class="nav-link <?= $stripeListo ? 'text-dark' : 'active text-dark' ?>" data-bs-toggle="tab" data-bs-target="#tab-transferencia" type="button">Transferencia</button></li>
          </ul>
          <div class="tab-content" style="max-width:420px;margin:0 auto;text-align:left;">
            <?php if ($stripeListo): ?>
            <div class="tab-pane fade show active text-center" id="tab-tarjeta">
              <div id="membresia-payment-element" class="mb-3 text-start d-none"></div>
              <?php if ($membresia['mostrar_codigo_promocion']): ?>
                <div class="input-group input-group-sm mb-3">
                  <input type="text" id="codigoCupon" class="form-control" placeholder="Código de cupón" autocomplete="off" value="<?= htmlspecialchars((string) $codigoCupon) ?>">
                  <button class="btn btn-outline-secondary" type="button" id="btnAplicarCupon">Aplicar</button>
                </div>
                <div id="cuponMsg" class="form-text mb-2<?= $oferta && $oferta['cupon_error'] ? ' text-danger' : '' ?>"><?= htmlspecialchars((string) ($oferta['cupon_error'] ?? '')) ?></div>
              <?php endif; ?>
              <button id="btnSuscribirse" class="pf-btn pf-btn-primary pf-btn-lg" data-membresia-id="<?= (int) $membresia['id'] ?>">Suscribirme con tarjeta</button>
              <button id="btnConfirmarSuscripcion" class="pf-btn pf-btn-primary pf-btn-lg d-none">Confirmar suscripción</button>
              <div id="suscribirMsg" class="mt-3" style="color:#fff;"></div>
              <div class="d-flex justify-content-center flex-wrap gap-3 mt-3" style="color:rgba(255,255,255,.6);font-size:12.5px;">
                <span><i class="bi bi-shield-check" style="color:var(--pf-accent);"></i> Pago seguro</span>
                <span><i class="bi bi-lock-fill" style="color:var(--pf-accent);"></i> Datos cifrados</span>
                <span><i class="bi bi-credit-card-2-front" style="color:var(--pf-accent);"></i> Visa · Mastercard · Amex</span>
                <span><i class="bi bi-x-circle" style="color:var(--pf-accent);"></i> Cancela cuando quieras</span>
              </div>
            </div>
            <?php endif; ?>
            <div class="tab-pane fade <?= $stripeListo ? '' : 'show active' ?>" id="tab-transferencia">
              <p style="color:rgba(255,255,255,.85);">Realiza tu depósito o transferencia a:</p>
              <ul style="color:#fff;">
                <li><strong>Banco:</strong> <?= htmlspecialchars(BANCO_NOMBRE) ?></li>
                <li><strong>CLABE:</strong> <?= htmlspecialchars(BANCO_CLABE) ?></li>
                <li><strong>Titular:</strong> <?= htmlspecialchars(BANCO_TITULAR) ?></li>
              </ul>
              <div class="mb-3">
                <label class="form-label" style="color:#fff;">Sube tu comprobante (opcional)</label>
                <div id="comprobanteDropzone" class="pf-dropzone" tabindex="0" role="button">
                  <i class="bi bi-cloud-arrow-up"></i>
                  <span id="comprobanteDropzoneTexto">Arrastra tu comprobante aquí o haz clic para buscarlo</span>
                </div>
                <input type="file" id="comprobanteFile" class="d-none" accept="image/png,image/jpeg,image/webp,application/pdf">
                <input type="file" id="comprobanteFileCamara" class="d-none" accept="image/*" capture="environment">
                <button type="button" id="btnTomarFotoComprobante" class="btn btn-link btn-sm p-0 mt-2 d-md-none" style="color:#fff;text-decoration:underline;">
                  <i class="bi bi-camera"></i> O toma una foto desde tu celular
                </button>
              </div>
              <button id="btnYaTransferiMembresia" class="pf-btn pf-btn-primary w-100 mb-2" data-membresia-id="<?= (int) $membresia['id'] ?>">Ya realicé la transferencia</button>
              <a class="pf-btn pf-btn-outline w-100" target="_blank"
                 href="https://wa.me/<?= htmlspecialchars(WHATSAPP_PAGOS) ?>?text=<?= urlencode('Hola, envío mi comprobante de la membresía ' . $membresia['nombre']) ?>">
                O envía tu comprobante por WhatsApp
              </a>
              <div id="transferMembresiaMsg" class="form-text mt-2"></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if ($usuario && !$esMiembro && $membresia && !$transferenciaPendiente): ?>
<?php if ($stripeListo): ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
<script>
  function codigoCuponMembresia() {
    const el = document.getElementById('codigoCupon');
    return el && el.value.trim() ? el.value.trim() : '';
  }

  <?php if ($stripeListo): ?>
  const stripe = Stripe(<?= json_encode($publishableKeyActiva) ?>);
  let elements = null;
  let subscriptionId = null;
  let paymentIntentId = null;

  document.getElementById('btnSuscribirse').addEventListener('click', async function () {
    const textoOriginal = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Cargando formulario de tarjeta...';
    const msg = document.getElementById('suscribirMsg');
    msg.textContent = '';
    try {
      const cuerpo = {
        membresia_id: this.dataset.membresiaId,
        csrf_token: <?= json_encode(csrf_token()) ?>,
      };
      const codigo = codigoCuponMembresia();
      if (codigo) cuerpo.codigo_cupon = codigo;
      const res = await fetch('backend/pagos/membresia_iniciar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(cuerpo),
      });
      const data = await res.json();
      if (data.success && data.activada_de_inmediato) {
        // Cupón/promoción dejó la suscripción en $0 — Stripe ya marcó la
        // factura como pagada sola, no hay Payment Element que montar.
        window.location.reload();
        return;
      }
      if (data.success) {
        subscriptionId = data.subscription_id;
        paymentIntentId = data.payment_intent_id;
        elements = stripe.elements({ clientSecret: data.client_secret });
        elements.create('payment').mount('#membresia-payment-element');
        document.getElementById('membresia-payment-element').classList.remove('d-none');
        this.classList.add('d-none');
        this.innerHTML = textoOriginal;
        document.getElementById('btnConfirmarSuscripcion').classList.remove('d-none');
      } else {
        msg.textContent = data.message || 'No se pudo iniciar la suscripción.';
        this.disabled = false;
        this.innerHTML = textoOriginal;
      }
    } catch (e) {
      msg.textContent = 'Error de conexión. Intenta de nuevo.';
      this.disabled = false;
      this.innerHTML = textoOriginal;
    }
  });

  document.getElementById('btnConfirmarSuscripcion').addEventListener('click', async function () {
    if (!elements) return;
    const textoOriginal = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando pago...';
    document.getElementById('suscribirMsg').textContent = '';
    const { error } = await stripe.confirmPayment({
      elements,
      confirmParams: { return_url: window.location.href },
    });
    // Si no hubo error, Stripe ya redirigió la página fuera de aquí — no
    // hace falta restaurar el botón porque este documento ya no se ve.
    if (error) {
      document.getElementById('suscribirMsg').textContent = error.message;
      this.disabled = false;
      this.innerHTML = textoOriginal;
    }
  });

  <?php if ($membresia['mostrar_codigo_promocion']): ?>
  document.getElementById('btnAplicarCupon').addEventListener('click', async function () {
    const codigo = codigoCuponMembresia();
    const cuponMsg = document.getElementById('cuponMsg');
    cuponMsg.className = 'form-text mb-2';
    if (!codigo) {
      cuponMsg.textContent = 'Escribe un código de cupón.';
      cuponMsg.classList.add('text-danger');
      return;
    }
    this.disabled = true;
    try {
      const cuerpo = {
        tipo: 'membresia',
        membresia_id: <?= (int) $membresia['id'] ?>,
        codigo_cupon: codigo,
        csrf_token: <?= json_encode(csrf_token()) ?>,
      };
      if (paymentIntentId) cuerpo.payment_intent_id = paymentIntentId;
      const res = await fetch('backend/pagos/aplicar_cupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(cuerpo),
      });
      const data = await res.json();
      if (data.success) {
        if (data.recargar) { window.location.reload(); return; }
        const formatoMXN = (n) => '$' + n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('precioTachado').textContent = formatoMXN(data.monto_original);
        document.getElementById('precioTachado').classList.remove('d-none');
        document.getElementById('precioMostrado').textContent = formatoMXN(data.monto_final);
        cuponMsg.textContent = '¡Cupón aplicado! Descuento de ' + formatoMXN(data.descuento) + '.';
        cuponMsg.classList.add('text-success');
        document.getElementById('codigoCupon').disabled = true;
        if (elements && elements.fetchUpdates) {
          elements.fetchUpdates();
        }
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
  <?php endif; ?>

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

  document.getElementById('btnYaTransferiMembresia').addEventListener('click', async function () {
    this.disabled = true;
    const msg = document.getElementById('transferMembresiaMsg');
    const archivo = document.getElementById('comprobanteFile').files[0];
    const datos = new FormData();
    datos.append('membresia_id', this.dataset.membresiaId);
    datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
    const codigoCupon = codigoCuponMembresia();
    if (codigoCupon) datos.append('codigo_cupon', codigoCupon);
    if (archivo) datos.append('comprobante_file', archivo);
    try {
      const res = await fetch('backend/pagos/membresia_transferencia.php', { method: 'POST', body: datos });
      const data = await res.json();
      if (data.success) {
        window.location.reload();
      } else {
        msg.textContent = data.message || 'No se pudo registrar tu pago.';
        msg.className = 'form-text text-danger mt-2';
        this.disabled = false;
      }
    } catch (e) {
      msg.textContent = 'Error de conexión. Intenta de nuevo.';
      this.disabled = false;
    }
  });
</script>
<?php endif; ?>
