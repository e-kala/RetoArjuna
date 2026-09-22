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
    $redirectStatus = $_GET['redirect_status'] ?? '';
    // 'processing' es el retorno normal de un voucher OXXO recién generado
    // (todavía sin pagar) — no es un error, así que no cae a 'cancelada'.
    // $suscripcionOxxoPendiente/$voucherOxxoPendiente (más abajo) son los
    // que de verdad deciden qué mostrar en ese caso; aquí solo se evita el
    // mensaje de "no se completó" que confundiría al usuario justo cuando
    // acaba de generar su voucher correctamente.
    $resultadoCheckout = match ($redirectStatus) {
        'succeeded' => 'exito',
        'processing' => 'voucher_generado',
        default => 'cancelada',
    };
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

// Una transferencia pendiente bloquea el flujo de compra (mensaje "ya
// registramos tu pago, espera confirmación") — una suscripción de Stripe
// pendiente (creada pero nunca confirmada con el Payment Element, ej. si el
// usuario cerró la pestaña a medias) NO debe bloquear nada: simplemente deja
// que intente de nuevo con el botón normal. Esa fila vieja se descarta sola
// (Stripe expira la factura a las 23h) o un admin la limpia desde
// panel/admin/membresias.php.
// oxxo_recurrente pendiente se trata aparte (ver $suscripcionOxxoPendiente
// más abajo) porque, a diferencia de transferencia, sí tiene un voucher
// concreto que mostrar/pagar — no basta el mensaje genérico de "espera
// confirmación".
$transferenciaPendiente = null;
$suscripcionOxxoPendiente = null;
if ($usuario && !$esMiembro && $membresia) {
    $usuarioIdActual = (int) $usuario['id'];
    $membresiaIdActual = (int) $membresia['id'];
    $stmt = $conn->prepare(
        "SELECT id FROM membresia_suscripciones WHERE usuario_id = ? AND membresia_id = ? AND estado = 'pendiente' AND metodo = 'transferencia' LIMIT 1"
    );
    $stmt->bind_param('ii', $usuarioIdActual, $membresiaIdActual);
    $stmt->execute();
    $transferenciaPendiente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT id, modo FROM membresia_suscripciones WHERE usuario_id = ? AND membresia_id = ? AND estado = 'pendiente' AND metodo = 'oxxo_recurrente' LIMIT 1"
    );
    $stmt->bind_param('ii', $usuarioIdActual, $membresiaIdActual);
    $stmt->execute();
    $suscripcionOxxoPendiente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$membresiaOxxoVisible = membresia_oxxo_visible_para_usuario_actual();

$voucherOxxoPendiente = null;
if ($suscripcionOxxoPendiente) {
    require_once __DIR__ . '/../backend/pagos/membresia_oxxo_helper.php';

    // Al volver de confirmar el Payment Element con redirect_status=processing,
    // Stripe ya generó next_action.oxxo_display_details pero nadie lo ha
    // guardado todavía — se completa aquí mismo (síncrono, sin depender del
    // webhook, que puede no estar configurado en local o tardar) usando el
    // mismo patrón que backend/pagos/checkout.php.
    if ($resultadoCheckout === 'voucher_generado' && isset($_GET['payment_intent'])) {
        membresia_oxxo_completar_datos_voucher($conn, (string) $_GET['payment_intent'], $suscripcionOxxoPendiente['modo']);
    }
    $voucherOxxoPendiente = membresia_oxxo_voucher_pendiente($conn, (int) $suscripcionOxxoPendiente['id']);
}

// Motor de ofertas (checklist.txt OF01-OF10) — promoción pública/cupón
// también aplican a membresía. Mismo criterio que membresia_iniciar.php:
// incluido_membresia/solo_miembros/descuento_miembro_pct no tienen sentido
// para la membresía misma, se pasan en false/null a propósito.
$oferta = null;
if ($usuario && !$esMiembro && $membresia && !$transferenciaPendiente && !$suscripcionOxxoPendiente) {
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
<style>
  /* Mismo sistema visual que backend/pagos/checkout.php (checkout de
     eventos/cursos/productos) — se repite aquí en vez de moverlo a
     platform.css porque ninguna otra página lo usa todavía; si un tercer
     lugar lo necesita, ese es el momento de centralizarlo ahí. */
  .pf-checkout-wrap { max-width: 560px; margin: 0 auto; padding: 0 16px; }
  .pf-checkout-wrap.pf-checkout-wrap-grid { max-width: 1040px; }
  .pf-checkout-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 28px; align-items: start; }
  @media (max-width: 900px) { .pf-checkout-grid { grid-template-columns: 1fr; } }
  .pf-checkout-card {
    background: var(--pf-surface);
    border-radius: var(--pf-radius-lg);
    box-shadow: 0 16px 40px rgba(35, 38, 43, 0.10);
    overflow: hidden;
  }
  .pf-checkout-side {
    background: var(--pf-surface);
    border-radius: var(--pf-radius-lg);
    box-shadow: 0 16px 40px rgba(35, 38, 43, 0.10);
    padding: 26px 24px;
    position: sticky;
    top: 24px;
  }
  .pf-checkout-side h3 { font-weight: 800; font-size: 16px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
  .pf-checkout-side p.pf-checkout-side-lead { color: var(--pf-muted); font-size: 13.5px; margin-bottom: 16px; }
  .pf-checkout-side ul { list-style: none; padding: 0; margin: 0 0 18px; display: flex; flex-direction: column; gap: 11px; }
  .pf-checkout-side ul li { display: flex; gap: 9px; align-items: flex-start; font-size: 14px; color: var(--pf-ink); }
  .pf-checkout-side ul li i { color: #198754; margin-top: 2px; flex-shrink: 0; }
  .pf-checkout-side-testimonial {
    border-top: 1px solid var(--pf-line); margin-top: 18px; padding-top: 16px;
    font-size: 13.5px; font-style: italic; color: var(--pf-muted); text-align: center;
  }
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
    display: flex; justify-content: center; gap: 18px; flex-wrap: wrap;
    color: var(--pf-muted); font-size: 12.5px; margin: 20px 0 4px;
  }
  .pf-checkout-confianza span { display: inline-flex; align-items: center; gap: 6px; }
  .pf-checkout-confianza i { color: var(--pf-accent); }
  .pf-checkout-card .pf-dropzone { border-color: var(--pf-line); color: var(--pf-muted); }
  .pf-checkout-card .pf-dropzone:hover,
  .pf-checkout-card .pf-dropzone:focus-visible { border-color: var(--pf-ink); background: rgba(35,38,43,0.03); }
  .pf-checkout-card .pf-dropzone.pf-dropzone-activo { background: rgba(247,147,30,0.08); }
  .pf-checkout-card .pf-dropzone-archivo { color: var(--pf-ink); }
  .pf-metodo-pago-selector { display: flex; gap: 10px; margin-bottom: 20px; }
  .pf-metodo-pago-btn {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 14px 8px; border-radius: var(--pf-radius-md);
    border: 2px solid var(--pf-line); background: var(--pf-surface);
    color: var(--pf-muted); font-weight: 700; font-size: 13px; cursor: pointer;
  }
  .pf-metodo-pago-btn i { font-size: 21px; }
  .pf-metodo-pago-btn.active { border-color: var(--pf-accent); color: var(--pf-ink); background: rgba(247,147,30,0.06); }
</style>
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
<?php elseif ($resultadoCheckout === 'voucher_generado'): ?>
  <div class="pf-container"><div class="alert alert-success">🎫 ¡Listo! Tu voucher OXXO se generó — abajo tienes el código para pagarlo en tienda.</div></div>
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
      <div class="pf-checkout-wrap pf-checkout-wrap-grid" style="margin-top:48px;">
        <div class="pf-checkout-grid">
          <div class="pf-checkout-card">
            <div class="pf-checkout-body-pad">
              <span class="pf-eyebrow"><?= htmlspecialchars($membresia['nombre']) ?></span>
              <h2 class="h4 fw-bold mt-2 mb-2"><?= htmlspecialchars($membresia['nombre']) ?></h2>
              <p class="pf-checkout-desc"><?= nl2br(htmlspecialchars((string) $membresia['descripcion'])) ?></p>

              <div class="pf-checkout-precio-box">
                <?php if ($oferta && $oferta['estado'] === 'oferta'): ?>
                  <span id="precioTachado" class="pf-checkout-precio-tachado">$<?= number_format($oferta['precio_regular'], 2) ?></span>
                  <span id="precioMostrado" class="pf-checkout-precio">$<?= number_format($oferta['precio_final'], 2) ?></span>
                  <span class="pf-checkout-precio-sufijo"> MXN / <?= $membresia['intervalo'] === 'anual' ? 'año' : 'mes' ?></span>
                  <div id="pf-desglose" class="small text-muted mt-2">
                    <div>Precio regular: $<?= number_format($oferta['precio_regular'], 2) ?></div>
                    <div><span id="descNombreOferta"><?= htmlspecialchars((string) $oferta['oferta_nombre']) ?></span>: −$<?= number_format($oferta['descuento_monto'], 2) ?></div>
                    <div class="fw-bold">Total: $<?= number_format($oferta['precio_final'], 2) ?></div>
                  </div>
                <?php else: ?>
                  <span id="precioTachado" class="pf-checkout-precio-tachado d-none"></span>
                  <span id="precioMostrado" class="pf-checkout-precio">$<?= number_format((float) $membresia['precio'], 2) ?></span>
                  <span class="pf-checkout-precio-sufijo"> MXN / <?= $membresia['intervalo'] === 'anual' ? 'año' : 'mes' ?></span>
                <?php endif; ?>
              </div>

              <?php if ($esMiembro): ?>
                <p class="text-center mb-3">✔ <img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Ya eres miembro de Camino Arjuna.</p>
                <a class="pf-btn pf-btn-primary pf-btn-lg w-100" href="backend/pagos/membresia_portal.php">Gestionar mi membresía</a>
              <?php elseif (!$usuario): ?>
                <a class="pf-btn pf-btn-primary pf-btn-lg w-100" href="?action=registro<?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>">Regístrate para suscribirte</a>
              <?php elseif ($transferenciaPendiente): ?>
                <div class="alert alert-warning mb-0">Registramos tu transferencia — en cuanto confirmemos el pago tu membresía queda activa. Si quieres, envía también tu comprobante por WhatsApp para agilizarlo.</div>
              <?php elseif ($suscripcionOxxoPendiente): ?>
                <?php if ($voucherOxxoPendiente && $voucherOxxoPendiente['numero']): ?>
                  <div class="card border-0 p-3 text-center">
                    <span class="pf-eyebrow">Voucher generado</span>
                    <h3 class="h6 mt-2 mb-2">Paga en tienda para activar tu membresía</h3>
                    <p class="text-muted small">Lleva este código a cualquier OXXO y paga en efectivo. En cuanto se registre el pago, tu membresía se activa automáticamente — te avisamos por correo y en tu panel.</p>
                    <div class="pf-checkout-precio-box" style="margin-bottom:0;">
                      <div class="text-muted small mb-1">Número de referencia</div>
                      <div style="font-size:16px;font-weight:800;letter-spacing:1px;word-break:break-all;color:var(--pf-accent-ink);"><?= htmlspecialchars($voucherOxxoPendiente['numero']) ?></div>
                    </div>
                    <?php if ($voucherOxxoPendiente['vence_en']): ?>
                      <p class="text-muted small mt-2">Vence: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($voucherOxxoPendiente['vence_en']))) ?></p>
                    <?php endif; ?>
                    <?php if ($voucherOxxoPendiente['url_voucher']): ?>
                      <a href="<?= htmlspecialchars($voucherOxxoPendiente['url_voucher']) ?>" target="_blank" class="pf-btn pf-btn-primary w-100 mt-2">Ver/imprimir comprobante</a>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <!-- Voucher creado en Stripe pero nunca confirmado en el navegador
                       (se cerró la pestaña, falló el confirmPayment(), etc.) —
                       numero/url_voucher solo se llenan DESPUÉS de que el cliente
                       confirma, así que este estado es normal, no un error. Antes
                       esto se mostraba como si el voucher ya estuviera listo ("Paga
                       en tienda") sin ningún código real, dejando al usuario sin
                       forma de continuar — ahora se retoma la confirmación con el
                       mismo client_secret (membresia_oxxo_obtener_secreto.php, ya
                       usado igual en mi_membresia.php para la renovación mensual). -->
                  <div class="card border-0 p-3 text-center">
                    <span class="pf-eyebrow">Voucher pendiente de confirmar</span>
                    <h3 class="h6 mt-2 mb-2">Falta un paso para generar tu código de pago</h3>
                    <p class="text-muted small">Iniciaste el pago por OXXO pero no llegaste a confirmarlo — retómalo aquí para obtener tu código.</p>
                    <div id="membresia-oxxo-retomar-element" class="mb-2 text-start d-none"></div>
                    <button type="button" id="btnRetomarVoucherOxxo" class="pf-btn pf-btn-primary w-100" data-suscripcion-id="<?= (int) $suscripcionOxxoPendiente['id'] ?>">Continuar y generar código</button>
                    <div id="retomarVoucherMsg" class="form-text text-danger mt-2"></div>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="pf-metodo-pago-selector" role="tablist">
                  <?php if ($stripeListo): ?>
                    <button type="button" class="pf-metodo-pago-btn active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-tarjeta"><i class="bi bi-credit-card-2-front"></i>Tarjeta</button>
                    <?php if ($membresiaOxxoVisible): ?>
                      <button type="button" class="pf-metodo-pago-btn" role="tab" data-bs-toggle="tab" data-bs-target="#tab-oxxo"><i class="bi bi-shop"></i>OXXO</button>
                    <?php endif; ?>
                  <?php endif; ?>
                  <button type="button" class="pf-metodo-pago-btn<?= $stripeListo ? '' : ' active' ?>" role="tab" data-bs-toggle="tab" data-bs-target="#tab-transferencia"><i class="bi bi-bank"></i>Transferencia</button>
                  <button type="button" class="pf-metodo-pago-btn" role="tab" data-bs-toggle="tab" data-bs-target="#tab-ventanilla"><i class="bi bi-shop-window"></i>Ventanilla</button>
                </div>
                <div class="tab-content">
                  <?php if ($stripeListo): ?>
                  <div class="tab-pane fade show active" id="tab-tarjeta">
                    <div id="membresia-payment-element" class="mb-3 d-none"></div>
                    <?php if ($membresia['mostrar_codigo_promocion']): ?>
                      <div class="input-group input-group-sm mb-3">
                        <input type="text" id="codigoCupon" class="form-control" placeholder="Código de cupón" autocomplete="off" value="<?= htmlspecialchars((string) $codigoCupon) ?>">
                        <button class="btn btn-outline-secondary" type="button" id="btnAplicarCupon">Aplicar</button>
                      </div>
                      <div id="cuponMsg" class="form-text mb-2<?= $oferta && $oferta['cupon_error'] ? ' text-danger' : '' ?>"><?= htmlspecialchars((string) ($oferta['cupon_error'] ?? '')) ?></div>
                    <?php endif; ?>
                    <button id="btnSuscribirse" class="pf-btn pf-btn-primary pf-btn-lg w-100" data-membresia-id="<?= (int) $membresia['id'] ?>">Suscribirme con tarjeta</button>
                    <button id="btnConfirmarSuscripcion" class="pf-btn pf-btn-primary pf-btn-lg w-100 d-none">Confirmar suscripción</button>
                    <div id="suscribirMsg" class="form-text text-danger mt-2"></div>
                    <div class="pf-checkout-confianza">
                      <span><i class="bi bi-shield-check"></i> Pago seguro</span>
                      <span><i class="bi bi-lock-fill"></i> Datos cifrados</span>
                      <span><i class="bi bi-credit-card-2-front"></i> Visa · Mastercard · Amex</span>
                      <span><i class="bi bi-x-circle"></i> Cancela cuando quieras</span>
                    </div>
                  </div>
                  <?php if ($membresiaOxxoVisible): ?>
                  <div class="tab-pane fade" id="tab-oxxo">
                    <p class="text-muted small">
                      Paga en efectivo en cualquier OXXO. A diferencia de tarjeta, <strong>el cobro no es automático</strong> —
                      cada mes te generamos un voucher nuevo (avisándote por correo y en tu panel) que debes pagar antes de
                      que venza para mantener tu acceso.
                    </p>
                    <button id="btnSuscribirseOxxo" class="pf-btn pf-btn-primary pf-btn-lg w-100" data-membresia-id="<?= (int) $membresia['id'] ?>">Generar voucher OXXO</button>
                    <div id="suscribirOxxoMsg" class="form-text text-danger mt-2"></div>
                  </div>
                  <?php endif; ?>
                  <?php endif; ?>
                  <div class="tab-pane fade<?= $stripeListo ? '' : ' show active' ?>" id="tab-transferencia">
                    <p>Realiza tu transferencia a:</p>
                    <ul>
                      <li><strong>Banco:</strong> <?= htmlspecialchars(BANCO_NOMBRE) ?></li>
                      <li><strong>Titular:</strong> <?= htmlspecialchars(BANCO_TITULAR) ?></li>
                      <li><strong>Cuenta CLABE (transferencias nacionales):</strong> <?= htmlspecialchars(BANCO_CLABE) ?></li>
                      <li><strong>Código SWIFT (transferencias internacionales):</strong> <?= htmlspecialchars(BANCO_SWIFT) ?></li>
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
                    <button id="btnYaTransferiMembresia" class="pf-btn pf-btn-primary pf-btn-lg w-100 mb-2" data-membresia-id="<?= (int) $membresia['id'] ?>">Ya realicé la transferencia</button>
                    <a class="btn btn-outline-success w-100" target="_blank"
                       href="https://wa.me/<?= htmlspecialchars(WHATSAPP_PAGOS) ?>?text=<?= urlencode('Hola, envío mi comprobante de la membresía ' . $membresia['nombre']) ?>">
                      Enviar comprobante por WhatsApp
                    </a>
                    <div id="transferMembresiaMsg" class="form-text mt-2"></div>
                  </div>
                  <div class="tab-pane fade" id="tab-ventanilla">
                    <p>Paga en ventanilla o en tiendas de conveniencia (OXXO y otras) a:</p>
                    <ul>
                      <li><strong>Banco:</strong> <?= htmlspecialchars(BANCO_NOMBRE) ?></li>
                      <li><strong>Titular:</strong> <?= htmlspecialchars(BANCO_TITULAR) ?></li>
                      <li><strong>Depósito en ventanilla:</strong> <?= htmlspecialchars(BANCO_VENTANILLA) ?></li>
                      <li><strong>Depósito en tiendas OXXO y otras:</strong> <?= htmlspecialchars(BANCO_VENTANILLA_OXXO) ?></li>
                    </ul>
                    <div class="mb-3">
                      <label class="form-label">Sube tu comprobante (opcional)</label>
                      <div id="comprobanteVentanillaDropzone" class="pf-dropzone" tabindex="0" role="button">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <span id="comprobanteVentanillaDropzoneTexto">Arrastra tu comprobante aquí o haz clic para buscarlo</span>
                      </div>
                      <input type="file" id="comprobanteVentanillaFile" class="d-none" accept="image/png,image/jpeg,image/webp,application/pdf">
                      <input type="file" id="comprobanteVentanillaFileCamara" class="d-none" accept="image/*" capture="environment">
                      <button type="button" id="btnTomarFotoComprobanteVentanilla" class="btn btn-link btn-sm p-0 mt-2 d-md-none">
                        <i class="bi bi-camera"></i> O toma una foto desde tu celular
                      </button>
                    </div>
                    <button id="btnYaDepositeVentanillaMembresia" class="pf-btn pf-btn-primary pf-btn-lg w-100 mb-2" data-membresia-id="<?= (int) $membresia['id'] ?>">Ya realicé mi depósito</button>
                    <a class="btn btn-outline-success w-100" target="_blank"
                       href="https://wa.me/<?= htmlspecialchars(WHATSAPP_PAGOS) ?>?text=<?= urlencode('Hola, envío mi comprobante de la membresía ' . $membresia['nombre']) ?>">
                      Enviar comprobante por WhatsApp
                    </a>
                    <div id="transferVentanillaMembresiaMsg" class="form-text mt-2"></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <aside class="pf-checkout-side">
            <h3>🛡️ Compra segura</h3>
            <p class="pf-checkout-side-lead">Tu pago se procesa de forma cifrada — nunca almacenamos los datos de tu tarjeta.</p>
            <ul>
              <li><i class="bi bi-check-circle-fill"></i> Cancela o pausa cuando quieras, sin llamadas ni trámites</li>
              <li><i class="bi bi-check-circle-fill"></i> Comprobante disponible en tu panel</li>
              <li><i class="bi bi-check-circle-fill"></i> Soporte por WhatsApp si algo falla</li>
            </ul>
            <p class="pf-checkout-side-testimonial">"Una comunidad para sostener lo que te importa."</p>
          </aside>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if ($usuario && !$esMiembro && $membresia && !$transferenciaPendiente && !$suscripcionOxxoPendiente): ?>
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

  // Membresía por OXXO — genera el voucher del primer mes y lo muestra con
  // el Payment Element (mismo componente que tarjeta, pero forzado a solo
  // 'oxxo' porque esta pestaña es exclusiva para ese método). Al confirmar,
  // Stripe redirige de vuelta a esta misma página con redirect_status=processing
  // (ver el bloque PHP de arriba: $resultadoCheckout la trata igual que
  // 'exito' visualmente, y $suscripcionOxxoPendiente + $voucherOxxoPendiente
  // se encargan de mostrar el voucher en la siguiente carga).
  const btnOxxo = document.getElementById('btnSuscribirseOxxo');
  if (btnOxxo) {
    btnOxxo.addEventListener('click', async function () {
      const textoOriginal = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generando voucher...';
      const msg = document.getElementById('suscribirOxxoMsg');
      msg.textContent = '';
      try {
        const res = await fetch('backend/pagos/membresia_oxxo_iniciar.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            membresia_id: this.dataset.membresiaId,
            csrf_token: <?= json_encode(csrf_token()) ?>,
          }),
        });
        const data = await res.json();
        if (!data.success) {
          msg.textContent = data.message || 'No se pudo generar el voucher.';
          this.disabled = false;
          this.innerHTML = textoOriginal;
          return;
        }
        const elementosOxxo = stripe.elements({ clientSecret: data.client_secret });
        const paymentElementOxxo = elementosOxxo.create('payment', {
          paymentMethodOrder: ['oxxo'],
          defaultValues: { billingDetails: { email: <?= json_encode($usuario['email'] ?? '') ?> } },
        });
        const contenedor = document.createElement('div');
        contenedor.className = 'mb-3 text-start';
        this.insertAdjacentElement('afterend', contenedor);
        paymentElementOxxo.mount(contenedor);
        this.classList.add('d-none');

        const btnConfirmar = document.createElement('button');
        btnConfirmar.className = 'pf-btn pf-btn-primary pf-btn-lg w-100 mt-2';
        btnConfirmar.textContent = 'Confirmar y generar voucher';
        contenedor.insertAdjacentElement('afterend', btnConfirmar);
        btnConfirmar.addEventListener('click', async function () {
          this.disabled = true;
          const { error } = await stripe.confirmPayment({
            elements: elementosOxxo,
            confirmParams: { return_url: window.location.href },
          });
          if (error) {
            msg.textContent = error.message;
            this.disabled = false;
          }
        });
      } catch (e) {
        msg.textContent = 'Error de conexión. Intenta de nuevo.';
        this.disabled = false;
        this.innerHTML = textoOriginal;
      }
    });
  }

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

  // Transferencia y Ventanilla comparten el mismo patrón de dropzone + botón
  // "ya pagué" + endpoint membresia_transferencia.php (ambos quedan como
  // metodo='transferencia' en membresia_suscripciones, pendiente de validar
  // por un admin en panel/admin/membresias.php — sin distinción de submétodo,
  // el comprobante/WhatsApp es lo que el admin usa para validar). Se
  // parametriza por prefijo de IDs en vez de duplicar este bloque dos veces.
  function configurarPagoManualMembresia(prefijo, idBoton, idMsg) {
    const dropzone = document.getElementById('comprobante' + prefijo + 'Dropzone');
    const dropzoneTexto = document.getElementById('comprobante' + prefijo + 'DropzoneTexto');
    const inputArchivo = document.getElementById('comprobante' + prefijo + 'File');
    const inputCamara = document.getElementById('comprobante' + prefijo + 'FileCamara');
    const btnCamara = document.getElementById('btnTomarFotoComprobante' + prefijo);
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

    document.getElementById(idBoton).addEventListener('click', async function () {
      this.disabled = true;
      const msg = document.getElementById(idMsg);
      const archivo = inputArchivo.files[0];
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
  }

  configurarPagoManualMembresia('', 'btnYaTransferiMembresia', 'transferMembresiaMsg');
  configurarPagoManualMembresia('Ventanilla', 'btnYaDepositeVentanillaMembresia', 'transferVentanillaMembresiaMsg');
</script>
<?php endif; ?>

<?php if ($suscripcionOxxoPendiente && (!$voucherOxxoPendiente || !$voucherOxxoPendiente['numero'])): ?>
<!-- Bloque de script independiente del de arriba: ese vive dentro de un
     if que excluye este mismo caso (suscripción OXXO pendiente), así que
     cuando SÍ hay una (este caso) ni el SDK de Stripe ni ese script se
     cargan en absoluto — hace falta este bloque aparte, con su propio
     script[src] y su propio Stripe(), para poder retomar la confirmación. -->
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  const stripe = Stripe(<?= json_encode($publishableKeyActiva) ?>);
  const csrfToken = <?= json_encode(csrf_token()) ?>;
  const btn = document.getElementById('btnRetomarVoucherOxxo');
  if (!btn) return;
  const msg = document.getElementById('retomarVoucherMsg');
  const contenedor = document.getElementById('membresia-oxxo-retomar-element');

  btn.addEventListener('click', async function () {
    const textoOriginal = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Cargando...';
    msg.textContent = '';
    try {
      const res = await fetch('backend/pagos/membresia_oxxo_obtener_secreto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ suscripcion_id: this.dataset.suscripcionId, csrf_token: csrfToken }),
      });
      const data = await res.json();
      if (!data.success) {
        msg.textContent = data.message || 'No se pudo cargar el voucher.';
        this.disabled = false;
        this.innerHTML = textoOriginal;
        return;
      }
      if (data.ya_confirmado) {
        // Stripe ya tiene el pago confirmado (ej. otra pestaña lo completó) —
        // solo falta que esta página vuelva a leer numero/url_voucher.
        window.location.reload();
        return;
      }
      const elements = stripe.elements({ clientSecret: data.client_secret });
      const paymentElement = elements.create('payment', { paymentMethodOrder: ['oxxo'] });
      contenedor.classList.remove('d-none');
      paymentElement.mount(contenedor);
      this.classList.add('d-none');

      const btnConfirmar = document.createElement('button');
      btnConfirmar.type = 'button';
      btnConfirmar.className = 'pf-btn pf-btn-primary w-100 mt-2';
      btnConfirmar.textContent = 'Confirmar y generar código';
      contenedor.insertAdjacentElement('afterend', btnConfirmar);
      btnConfirmar.addEventListener('click', async function () {
        this.disabled = true;
        const { error } = await stripe.confirmPayment({
          elements,
          confirmParams: { return_url: window.location.href },
        });
        if (error) {
          msg.textContent = error.message;
          this.disabled = false;
        }
      });
    } catch (e) {
      msg.textContent = 'Error de conexión. Intenta de nuevo.';
      this.disabled = false;
      this.innerHTML = textoOriginal;
    }
  });
})();
</script>
<?php endif; ?>

<?php if ($usuario && !$esMiembro && $membresia && !$transferenciaPendiente && !$suscripcionOxxoPendiente): ?>
<script>
  // Los botones de método de pago siguen usando data-bs-toggle="tab" de
  // Bootstrap (así el .tab-content de abajo no cambia), pero Bootstrap solo
  // gestiona la clase .active dentro de un <ul class="nav"> — aquí no es un
  // <li>/<a class="nav-link">, así que se mueve a mano (mismo patrón que
  // backend/pagos/checkout.php).
  document.querySelectorAll('.pf-metodo-pago-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.pf-metodo-pago-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
    });
  });
</script>
<?php endif; ?>
