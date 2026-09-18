<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/item_resolver.php';
require_once __DIR__ . '/stripe_helper.php';
require_login();

$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$usuarioActual = current_user();

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

// Combo Membresía + Evento/Curso (ver checkout_combo_iniciar.php): el modo
// combo manda su propio subscription_id en el volver (combo_sub), porque a
// diferencia de un PaymentIntent suelto, aquí lo que hay que confirmar/
// activar es la Subscription completa, no una fila en `pagos`. Misma idea
// que el bloque de abajo (verificación síncrona inmediata, sin esperar al
// webhook) pero para el objeto Subscription+Invoice en vez de PaymentIntent.
if (isset($_GET['payment_intent'], $_GET['combo_sub']) && ($_GET['redirect_status'] ?? '') === 'succeeded') {
    require_once __DIR__ . '/combo_helper.php';
    $intentIdCombo = (string) $_GET['payment_intent'];
    $subscriptionIdCombo = (string) $_GET['combo_sub'];
    $resIntentCombo = stripe_api('GET', 'payment_intents/' . urlencode($intentIdCombo));
    if ($resIntentCombo['ok'] && ($resIntentCombo['data']['status'] ?? '') === 'succeeded') {
        $stmt = $conn->prepare(
            "UPDATE membresia_suscripciones SET estado = 'activa'
             WHERE stripe_subscription_id = ? AND usuario_id = ? AND estado <> 'activa'"
        );
        $stmt->bind_param('si', $subscriptionIdCombo, $usuarioPerfilId);
        $stmt->execute();
        $afectadosCombo = $stmt->affected_rows;
        $stmt->close();

        if ($afectadosCombo > 0) {
            $resInvoices = stripe_api('GET', 'invoices', ['subscription' => $subscriptionIdCombo, 'limit' => 1]);
            $facturaCombo = $resInvoices['data']['data'][0] ?? [];
            $comboMeta = extraer_combo_metadata_de_invoice($facturaCombo, $subscriptionIdCombo);
            if ($comboMeta) {
                activar_combo_inscripcion($conn, $usuarioPerfilId, $comboMeta['tipo'], $comboMeta['item_id']);
            }

            // Mismo criterio que content/membresia.php: cada cambio de radio
            // (solo→combo→solo...) o reintento antes de pagar crea una
            // Subscription de Stripe nueva — se descartan las que quedaron
            // 'pendiente' sin pagarse, para que no se acumulen huérfanas en
            // panel/admin/membresias.php.
            $stmtLimpiaCombo = $conn->prepare(
                "DELETE FROM membresia_suscripciones WHERE usuario_id = ? AND metodo = 'stripe' AND estado = 'pendiente' AND stripe_subscription_id <> ?"
            );
            $stmtLimpiaCombo->bind_param('is', $usuarioPerfilId, $subscriptionIdCombo);
            $stmtLimpiaCombo->execute();
            $stmtLimpiaCombo->close();

            if ($usuarioActual['email'] ?? null) {
                $stmtM = $conn->prepare('SELECT nombre FROM membresia_suscripciones s JOIN membresias m ON m.id = s.membresia_id WHERE s.stripe_subscription_id = ? LIMIT 1');
                $stmtM->bind_param('s', $subscriptionIdCombo);
                $stmtM->execute();
                $nombreMembresiaCombo = $stmtM->get_result()->fetch_assoc()['nombre'] ?? 'tu membresía';
                $stmtM->close();
                enviar_email_membresia_activada($usuarioPerfilId, $usuarioActual['email'], $nombreMembresiaCombo);
            }
        }
    }
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
            // Ver el mismo comentario en stripe_webhook.php: sin esto el
            // evento nunca aparece en panel/content/mis_eventos.php aunque
            // el usuario ya tenga acceso real (usuario_esta_inscrito_evento()
            // en auth.php acepta pagos.confirmado como fuente alterna, pero
            // "Mis eventos" solo lee evento_inscripciones).
            if ($item['tipo'] === 'evento') {
                $stmtInscribe = $conn->prepare(
                    "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado) VALUES (?, ?, 'inscrito')
                     ON DUPLICATE KEY UPDATE estado = IF(estado = 'cancelado', 'inscrito', estado)"
                );
                $eventoIdInscribe = (int) $item['id'];
                $stmtInscribe->bind_param('ii', $usuarioPerfilId, $eventoIdInscribe);
                $stmtInscribe->execute();
                $stmtInscribe->close();
            }

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

// redirect_status=processing: el usuario eligió un método asíncrono tipo
// voucher (OXXO) — Stripe ya generó el comprobante, pero el cobro no se
// completa hasta que lo paga en tienda (hasta unos días después, según
// venza el voucher). A diferencia de 'succeeded', aquí NO se otorga acceso
// ni se toca `pagos.estado` (sigue en 'pendiente', tal como quedó al
// crear el PaymentIntent) — se muestra el comprobante para que el usuario
// lo pueda guardar/imprimir, en vez de redirigir en silencio a una página
// que insinuaría que ya tiene acceso. payment_intent.payment_failed
// (stripe_webhook.php) es quien marca 'rechazado' si el voucher vence sin
// pagarse; payment_intent.succeeded lo confirma cuando sí se paga.
$voucherOxxo = null;
if (isset($_GET['payment_intent']) && ($_GET['redirect_status'] ?? '') === 'processing') {
    $intentId = (string) $_GET['payment_intent'];
    $resIntent = stripe_api('GET', 'payment_intents/' . urlencode($intentId));
    $detalleVoucher = $resIntent['data']['next_action']['oxxo_display_details'] ?? null;
    if ($resIntent['ok'] && $detalleVoucher) {
        $voucherOxxo = [
            'numero' => $detalleVoucher['number'] ?? '',
            'vence' => !empty($detalleVoucher['expires_after']) ? date('d/m/Y H:i', (int) $detalleVoucher['expires_after']) : null,
            'url' => $detalleVoucher['hosted_voucher_url'] ?? null,
        ];
    }
}

$stripeListo = config_esta_lista(STRIPE_PUBLISHABLE_KEY) && config_esta_lista(STRIPE_SECRET_KEY);
$paramName = $item['tipo'] . '_id';
$publishableKeyActiva = stripe_publishable_key_activa();
$etiquetaTipo = ['curso' => 'Curso', 'evento' => 'Evento', 'producto' => 'Producto'][$item['tipo']] ?? 'Compra';

// Combo Membresía + Evento/Curso: solo aplica a curso/evento marcados
// incluido_membresia=1, comprados por alguien que todavía no es miembro y
// para los que este checkout normal ya cobra precio completo/con oferta
// (nunca a quien ya tiene acceso o está bloqueado — esos casos ya salieron
// por el guard de arriba). Producto queda fuera: la membresía nunca aplica
// a productos de tienda.
$mostrarCombo = $stripeListo && $item['tipo'] !== 'producto' && $item['incluido_membresia'] === true
    && !usuario_tiene_membresia_activa($usuarioPerfilId);
$membresiaVisible = $mostrarCombo ? $conn->query('SELECT * FROM membresias WHERE activo = 1 ORDER BY orden ASC LIMIT 1')->fetch_assoc() : null;
if (!$membresiaVisible || !config_esta_lista((string) ($membresiaVisible['stripe_price_id'] ?? ''))) {
    $mostrarCombo = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comprar · <?= htmlspecialchars($item['titulo']) ?> · Reto Arjuna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../../assets/css/platform.css?v=2.9">
  <?php if ($stripeListo): ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
  <style>
    body.pf-checkout-body { background: var(--pf-bg); min-height: 100vh; }
    .pf-checkout-wrap { max-width: 560px; margin: 56px auto; padding: 0 16px; }
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
    .pf-checkout-side-nota {
      display: flex; gap: 10px; align-items: flex-start;
      background: rgba(35,38,43,0.03); border-radius: var(--pf-radius-md);
      padding: 14px; font-size: 13px; color: var(--pf-muted); margin-top: 8px;
    }
    .pf-checkout-side-nota i { color: var(--pf-accent); margin-top: 1px; }
    .pf-checkout-side-testimonial {
      border-top: 1px solid var(--pf-line); margin-top: 18px; padding-top: 16px;
      font-size: 13.5px; font-style: italic; color: var(--pf-muted); text-align: center;
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

    /* Métodos de pago — botones con icono en vez de tabs planas. */
    .pf-metodo-pago-selector { display: flex; gap: 10px; margin-bottom: 20px; }
    .pf-metodo-pago-btn {
      flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;
      padding: 14px 8px; border-radius: var(--pf-radius-md);
      border: 2px solid var(--pf-line); background: var(--pf-surface);
      color: var(--pf-muted); font-weight: 700; font-size: 13px; cursor: pointer;
    }
    .pf-metodo-pago-btn i { font-size: 21px; }
    .pf-metodo-pago-btn.active { border-color: var(--pf-accent); color: var(--pf-ink); background: rgba(247,147,30,0.06); }

    /* Cupón aplicado — chip verde. */
    .pf-cupon-aplicado {
      display: flex; align-items: flex-start; gap: 8px;
      background: rgba(25, 135, 84, 0.08);
      border: 1px solid rgba(25, 135, 84, 0.3);
      color: #146c43;
      border-radius: var(--pf-radius-md);
      padding: 10px 14px;
      font-size: 13.5px;
    }
    .pf-cupon-aplicado i { color: #198754; margin-top: 1px; }
    .pf-cupon-aplicado strong { font-weight: 700; }

    /* Combo Membresía + Evento/Curso. */
    .pf-combo-toggle { display: flex; flex-direction: column; gap: 10px; margin-bottom: 4px; }
    .pf-combo-opcion {
      display: flex; align-items: center; gap: 10px;
      border: 2px solid var(--pf-line); border-radius: var(--pf-radius-md);
      padding: 12px 14px; cursor: pointer; font-weight: 600; font-size: 14.5px;
    }
    .pf-combo-opcion.pf-combo-opcion-activa { border-color: var(--pf-accent); background: rgba(247,147,30,0.05); }
    .pf-combo-opcion input { flex-shrink: 0; }
    .pf-badge-recomendado {
      margin-left: auto; background: var(--pf-accent); color: #fff;
      font-size: 10px; font-weight: 800; letter-spacing: 0.04em;
      padding: 4px 8px; border-radius: 999px; white-space: nowrap;
    }
    .pf-combo-card {
      background: rgba(25, 135, 84, 0.06); border: 1px solid rgba(25, 135, 84, 0.25);
      border-radius: var(--pf-radius-md); padding: 16px 18px; font-size: 14px; margin: 14px 0 20px;
    }
    .pf-combo-card div { display: flex; justify-content: space-between; padding: 3px 0; }
    .pf-combo-card .pf-combo-total { font-weight: 800; border-top: 1px solid rgba(25,135,84,0.25); margin-top: 6px; padding-top: 8px; }
  </style>
</head>
<body class="pf-body pf-checkout-body">
  <?= stripe_modo_prueba_banner_html() ?>
  <?php if ($voucherOxxo): ?>
  <div class="pf-checkout-wrap">
    <div class="pf-checkout-card">
      <div class="pf-checkout-body-pad text-center">
        <span class="pf-eyebrow">Voucher generado</span>
        <h1 class="h4 mt-2 mb-3" style="font-weight:800;">Paga en tienda para completar tu compra</h1>
        <p class="text-muted">
          Lleva este código a cualquier OXXO y paga en efectivo. En cuanto se registre el pago, tu acceso a
          "<?= htmlspecialchars($item['titulo']) ?>" se activa automáticamente — te avisamos por correo.
        </p>
        <?php if ($voucherOxxo['numero']): ?>
          <div class="pf-checkout-precio-box">
            <div class="text-muted small mb-1">Número de referencia</div>
            <div class="pf-checkout-precio" style="font-size:18px;letter-spacing:1px;word-break:break-all;"><?= htmlspecialchars($voucherOxxo['numero']) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($voucherOxxo['vence']): ?>
          <p class="text-muted small">Vence: <?= htmlspecialchars($voucherOxxo['vence']) ?> — si no pagas antes de esa fecha, el voucher se cancela y tendrás que generar uno nuevo.</p>
        <?php endif; ?>
        <?php if ($voucherOxxo['url']): ?>
          <a href="<?= htmlspecialchars($voucherOxxo['url']) ?>" target="_blank" class="pf-btn pf-btn-primary pf-btn-lg w-100 mt-2">Ver/imprimir comprobante</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(destino_tras_pago($item)) ?>" class="btn btn-outline-secondary w-100 mt-3">Entendido, continuar</a>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="pf-checkout-wrap pf-checkout-wrap-grid">
    <div class="pf-checkout-grid">
    <div class="pf-checkout-card">
      <?php if (!empty($item['imagen'])): ?>
        <img src="<?= htmlspecialchars(BASE_URL . '/' . $item['imagen']) ?>" class="pf-checkout-img" alt="">
      <?php endif; ?>
      <div class="pf-checkout-body-pad">
        <span class="pf-eyebrow"><?= htmlspecialchars($etiquetaTipo) ?></span>
        <h1 class="h4 mt-2 mb-2" style="font-weight:800;"><?= htmlspecialchars($item['titulo']) ?></h1>
        <?php if (!empty($item['descripcion'])): ?>
          <div class="pf-checkout-desc pf-contenido-html"><?= (string) $item['descripcion'] ?></div>
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

        <?php if ($mostrarCombo): ?>
          <div class="pf-combo-toggle mb-3">
            <label class="pf-combo-opcion pf-combo-opcion-activa">
              <input type="radio" name="modoCompra" value="solo" checked>
              Solo este <?= htmlspecialchars(mb_strtolower($etiquetaTipo)) ?>
            </label>
            <label class="pf-combo-opcion">
              <input type="radio" name="modoCompra" value="combo">
              Membresía Camino Arjuna + <?= htmlspecialchars($etiquetaTipo) ?>
              <span class="pf-badge-recomendado">OPCIÓN RECOMENDADA</span>
            </label>
          </div>
          <div id="comboDesglose" class="pf-combo-card d-none">
            <div><span>Membresía Camino Arjuna</span> <span id="comboPrecioMembresia">—</span></div>
            <div><span><?= htmlspecialchars($item['titulo']) ?></span> <span id="comboPrecioItem">—</span></div>
            <div class="pf-combo-total"><span>Total primer cobro</span> <span id="comboPrecioTotal">—</span></div>
          </div>
        <?php endif; ?>

        <?php
        // El campo de cupón se muestra si el evento/curso lo permite (modo
        // "solo") O si la membresía del combo lo permite (modo "combo") —
        // son configuraciones independientes (mostrar_codigo_promocion vive
        // en cursos/eventos Y en membresias por separado). Qué precio
        // termina descontando el código depende del modo activo al
        // aplicarlo (ver btnAplicarCupon más abajo: aplicar_cupon.php para
        // "solo", checkout_combo_iniciar.php para "combo").
        $mostrarCampoCupon = $item['mostrar_codigo_promocion'] || ($mostrarCombo && !empty($membresiaVisible['mostrar_codigo_promocion']));
        ?>
        <?php if ($mostrarCampoCupon): ?>
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
        <div class="pf-metodo-pago-selector" role="tablist">
          <button type="button" class="pf-metodo-pago-btn active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-stripe"><i class="bi bi-credit-card-2-front"></i>Tarjeta</button>
          <button type="button" class="pf-metodo-pago-btn" role="tab" data-bs-toggle="tab" data-bs-target="#tab-transfer"><i class="bi bi-bank"></i>Transferencia</button>
          <button type="button" class="pf-metodo-pago-btn" role="tab" data-bs-toggle="tab" data-bs-target="#tab-ventanilla"><i class="bi bi-shop"></i>Ventanilla</button>
        </div>

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
            <button class="pf-btn pf-btn-primary pf-btn-lg w-100 mb-2" id="btnYaTransferi">Ya realicé la transferencia</button>
            <a class="btn btn-outline-success w-100" target="_blank"
               href="https://wa.me/<?= htmlspecialchars(WHATSAPP_PAGOS) ?>?text=<?= urlencode('Hola, envío mi comprobante de ' . $item['titulo']) ?>">
              Enviar comprobante por WhatsApp
            </a>
            <div id="transferMsg" class="form-text"></div>
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
            <button class="pf-btn pf-btn-primary pf-btn-lg w-100 mb-2" id="btnYaDepositeVentanilla">Ya realicé mi depósito</button>
            <a class="btn btn-outline-success w-100" target="_blank"
               href="https://wa.me/<?= htmlspecialchars(WHATSAPP_PAGOS) ?>?text=<?= urlencode('Hola, envío mi comprobante de ' . $item['titulo']) ?>">
              Enviar comprobante por WhatsApp
            </a>
            <div id="transferVentanillaMsg" class="form-text"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <aside class="pf-checkout-side">
      <?php if ($mostrarCombo): ?>
        <h3>⭐ Más valor con la membresía</h3>
        <p class="pf-checkout-side-lead">Además de este <?= htmlspecialchars(mb_strtolower($etiquetaTipo)) ?>, obtienes la membresía Camino Arjuna con acceso a encuentros semanales, descuentos y más beneficios.</p>
        <ul>
          <li><i class="bi bi-check-circle-fill"></i> Acceso a un encuentro regular semanal grupal de seguimiento</li>
          <li><i class="bi bi-check-circle-fill"></i> Descuentos en cursos, eventos y productos</li>
          <li><i class="bi bi-check-circle-fill"></i> Comunidad de práctica continua</li>
          <li><i class="bi bi-check-circle-fill"></i> Acceso a futuras actividades exclusivas</li>
        </ul>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=membresia" target="_blank" style="color:var(--pf-accent-ink);font-weight:700;font-size:13.5px;">Ver todos los beneficios de la membresía →</a>
        <div class="pf-checkout-side-nota">
          <i class="bi bi-lightbulb-fill"></i>
          <span><strong>Tú eliges.</strong> Si por ahora solo quieres este <?= htmlspecialchars(mb_strtolower($etiquetaTipo)) ?>, también puedes adquirirlo de forma individual.</span>
        </div>
      <?php else: ?>
        <h3>🛡️ Compra segura</h3>
        <p class="pf-checkout-side-lead">Tu pago se procesa de forma cifrada — nunca almacenamos los datos de tu tarjeta.</p>
        <ul>
          <li><i class="bi bi-check-circle-fill"></i> Confirmación inmediata de tu acceso</li>
          <li><i class="bi bi-check-circle-fill"></i> Comprobante disponible en tu panel</li>
          <li><i class="bi bi-check-circle-fill"></i> Soporte por WhatsApp si algo falla</li>
        </ul>
      <?php endif; ?>
      <p class="pf-checkout-side-testimonial">"Una comunidad para sostener lo que te importa."</p>
    </aside>
    </div>
  </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="../../content/notify.min.js"></script>
  <?php if (!$voucherOxxo): ?>
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

    // Transferencia y Ventanilla comparten el mismo patrón de dropzone +
    // botón "ya pagué" + endpoint transferencia.php (ambos quedan como
    // metodo_pago='transferencia' en `pagos`, pendiente de validar por un
    // admin — no hay distinción de submétodo en el schema, ni falta hace: el
    // comprobante/WhatsApp es lo que el admin usa para validar). Se
    // parametriza por prefijo de IDs en vez de duplicar este bloque dos
    // veces — ver los ids con sufijo "Ventanilla" en el HTML de arriba.
    function configurarPagoManual(prefijo, idBoton, idMsg, mensajeExito) {
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
            $.notify(mensajeExito, { className: 'success', position: 'top right', autoHideDelay: 4000 });
          } else {
            this.disabled = false;
          }
        } catch (e) {
          msg.textContent = 'Error de conexión. Intenta de nuevo.';
          msg.className = 'form-text text-danger';
          this.disabled = false;
        }
      });
    }

    configurarPagoManual('', 'btnYaTransferi', 'transferMsg', 'Comprobante registrado — confirmaremos tu acceso en cuanto validemos el depósito.');
    configurarPagoManual('Ventanilla', 'btnYaDepositeVentanilla', 'transferVentanillaMsg', 'Comprobante registrado — confirmaremos tu acceso en cuanto validemos el depósito.');

    // Los botones de método de pago siguen usando data-bs-toggle="tab" de
    // Bootstrap (así el .tab-content de abajo no cambia), pero Bootstrap solo
    // gestiona la clase .active dentro de un <ul class="nav"> — aquí no es
    // un <li>/<a class="nav-link">, así que se mueve a mano.
    document.querySelectorAll('.pf-metodo-pago-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.pf-metodo-pago-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
      });
    });

    <?php if (!$voucherOxxo && $stripeListo && !$item['acceso_gratis_automatico']): ?>
    const stripe = Stripe(<?= json_encode($publishableKeyActiva) ?>);
    const MOSTRAR_COMBO = <?= $mostrarCombo ? 'true' : 'false' ?>;
    let elements = null;
    let paymentIntentId = null;
    let modoCompraActual = 'solo';
    let comboSubscriptionId = null;

    function formatoMXN(n) {
      return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Un solo Payment Element, remontado según la opción elegida: "solo" pide
    // su client_secret a stripe_create_intent.php (PaymentIntent normal,
    // igual que siempre); "combo" lo pide a checkout_combo_iniciar.php (la
    // Subscription con la membresía + este evento/curso como cargo único en
    // la misma factura — ver ese archivo). El botón "Pagar" y su
    // confirmPayment() no cambian, siguen usando el `elements` activo.
    async function montarStripeParaModoActual(codigoCuponCombo) {
      const stripeMsg = document.getElementById('stripeMsg');
      stripeMsg.textContent = '';
      const endpoint = modoCompraActual === 'combo' ? './checkout_combo_iniciar.php' : './stripe_create_intent.php';
      let datos;
      if (modoCompraActual === 'combo') {
        datos = {
          tipo: <?= json_encode($item['tipo']) ?>,
          item_id: ITEM_ID,
          csrf_token: CSRF_TOKEN,
        };
        if (codigoCuponCombo) datos.codigo_cupon = codigoCuponCombo;
      } else {
        datos = datosBase();
      }
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(datos),
      });
      const data = await res.json();
      if (!data.success) {
        stripeMsg.textContent = data.message || 'No se pudo iniciar el pago.';
        return;
      }
      paymentIntentId = data.payment_intent_id || null;
      comboSubscriptionId = modoCompraActual === 'combo' ? data.subscription_id : null;

      if (modoCompraActual === 'combo') {
        document.getElementById('comboPrecioMembresia').textContent = formatoMXN(data.precio_membresia_final);
        document.getElementById('comboPrecioItem').textContent = formatoMXN(data.precio_item);
        document.getElementById('comboPrecioTotal').textContent = formatoMXN(data.precio_total);
      }

      document.getElementById('payment-element').innerHTML = '';
      elements = stripe.elements({ clientSecret: data.client_secret });
      elements.create('payment', {
        // Tarjeta primero, OXXO al lado — sin esto Stripe decide el orden
        // dinámicamente y podía mostrar OXXO como primera opción. El combo
        // siempre requiere tarjeta (crea una Subscription recurrente real,
        // que no puede depender de un voucher que tarda días en pagarse).
        paymentMethodOrder: modoCompraActual === 'combo' ? ['card'] : ['card', 'oxxo'],
        // Precarga el correo del usuario logueado en el campo de contacto
        // del Payment Element (se ve ya escrito, pero sigue siendo un
        // <input> normal — el usuario puede borrarlo y poner otro antes de
        // pagar, útil sobre todo para OXXO si quiere recibir el voucher en
        // un correo distinto al de su cuenta).
        defaultValues: {
          billingDetails: { email: <?= json_encode($usuarioActual['email'] ?? '') ?> },
        },
      }).mount('#payment-element');
    }
    montarStripeParaModoActual();

    <?php if ($mostrarCombo): ?>
    document.querySelectorAll('input[name="modoCompra"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        modoCompraActual = radio.value;
        document.querySelectorAll('.pf-combo-opcion').forEach(function (label) {
          label.classList.toggle('pf-combo-opcion-activa', label.querySelector('input').checked);
        });
        document.getElementById('comboDesglose').classList.toggle('d-none', modoCompraActual !== 'combo');
        montarStripeParaModoActual();
      });
    });
    <?php endif; ?>

    document.getElementById('btnPagarStripe').addEventListener('click', async () => {
      if (!elements) return;
      // El correo va SOLO por defaultValues (arriba, al crear el Payment
      // Element) — nunca aquí en confirmParams.payment_method_data: ese
      // campo tiene prioridad sobre lo que el usuario haya escrito en el
      // formulario y lo pisaría en silencio, dejando el campo visualmente
      // editable pero sin efecto real. Así, lo que el Payment Element
      // recolectó (precargado con el correo de la cuenta, pero modificable)
      // es lo único que se envía.
      let returnUrl = window.location.href;
      if (modoCompraActual === 'combo' && comboSubscriptionId) {
        returnUrl += (returnUrl.includes('?') ? '&' : '?') + 'combo_sub=' + encodeURIComponent(comboSubscriptionId);
      }
      const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: returnUrl,
        },
      });
      if (error) {
        document.getElementById('stripeMsg').textContent = error.message;
      }
    });
    <?php endif; ?>

    <?php if ($mostrarCampoCupon): ?>
    document.getElementById('btnAplicarCupon').addEventListener('click', async function () {
      const codigo = document.getElementById('codigoCupon').value.trim();
      const cuponMsg = document.getElementById('cuponMsg');
      cuponMsg.className = 'form-text mb-2';
      cuponMsg.innerHTML = '';
      if (!codigo) {
        cuponMsg.textContent = 'Escribe un código de cupón.';
        cuponMsg.classList.add('text-danger');
        return;
      }
      this.disabled = true;

      // En modo combo, el cupón es de MEMBRESÍA (aplicar_cupon.php descuenta
      // el evento/curso, no lo que necesitamos aquí) — se re-crea la
      // Subscription pasando el código, checkout_combo_iniciar.php resuelve
      // el descuento contra el precio de membresía únicamente.
      if (typeof modoCompraActual !== 'undefined' && modoCompraActual === 'combo') {
        try {
          await montarStripeParaModoActual(codigo);
          cuponMsg.innerHTML = '<div class="pf-cupon-aplicado"><i class="bi bi-check-circle-fill"></i><span>Cupón <strong>' + codigo + '</strong> aplicado a tu membresía.</span></div>';
        } catch (e) {
          cuponMsg.textContent = 'Error de conexión. Intenta de nuevo.';
          cuponMsg.classList.add('text-danger');
        }
        this.disabled = false;
        return;
      }

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
          document.getElementById('precioTachado').textContent = formatoMXN(data.monto_original);
          document.getElementById('precioTachado').classList.remove('d-none');
          document.getElementById('precioMostrado').textContent = formatoMXN(data.monto_final);
          cuponMsg.innerHTML = '<div class="pf-cupon-aplicado"><i class="bi bi-check-circle-fill"></i><span>Cupón <strong>' + codigo + '</strong> aplicado — ahorras ' + formatoMXN(data.descuento) + '.</span></div>';
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
  <?php endif; ?>
</body>
</html>
