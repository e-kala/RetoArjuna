<?php
// "Mi membresía" — pestaña propia (2026-09-08), antes vivía como una tabla
// dentro de "Mis compras" (extraída de ahí, misma consulta/lógica).
require_once __DIR__ . '/../../backend/pagos/membresia_oxxo_helper.php';
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

// Al volver de confirmar el Payment Element del voucher de renovación
// (mi_membresia.php se pasa como return_url), Stripe ya generó
// next_action.oxxo_display_details pero nadie lo ha guardado — se completa
// aquí mismo, síncrono, mismo patrón que content/membresia.php (alta) y
// backend/pagos/checkout.php (pagos únicos). Sin esto el voucher se queda
// sin numero/url_voucher hasta que llegue el webhook (que en local puede no
// estar configurado, y en producción puede tardar).
if (isset($_GET['payment_intent']) && ($_GET['redirect_status'] ?? '') === 'processing') {
    $stmtModo = $conn->prepare(
        "SELECT ms.modo FROM membresia_vouchers_oxxo v
         JOIN membresia_suscripciones ms ON ms.id = v.suscripcion_id
         WHERE v.payment_intent_id = ? AND ms.usuario_id = ? LIMIT 1"
    );
    $paymentIntentRetorno = (string) $_GET['payment_intent'];
    $stmtModo->bind_param('si', $paymentIntentRetorno, $usuarioPerfilId);
    $stmtModo->execute();
    $filaModo = $stmtModo->get_result()->fetch_assoc();
    $stmtModo->close();
    if ($filaModo) {
        membresia_oxxo_completar_datos_voucher($conn, $paymentIntentRetorno, $filaModo['modo']);
    }
}

$stmt = $conn->prepare(
    "SELECT ms.id, ms.estado, ms.modo, ms.metodo, ms.periodo_actual_fin, ms.created_at, m.nombre, m.precio, m.intervalo
     FROM membresia_suscripciones ms
     JOIN membresias m ON m.id = ms.membresia_id
     WHERE ms.usuario_id = ?
     ORDER BY ms.created_at DESC"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$suscripciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$estadoSuscripcionBadge = ['activa' => 'bg-success', 'gracia' => 'bg-warning text-dark', 'cancelada' => 'bg-secondary', 'vencida' => 'bg-danger', 'pendiente' => 'bg-warning text-dark'];
$estadoSuscripcionLabel = ['gracia' => 'en gracia (paga pronto)'];
?>
<h1 class="h3 fw-bold mb-4">Mi membresía</h1>

<?php if ($suscripciones): ?>
  <table class="table table-bordered bg-white mb-4">
    <thead>
      <tr><th>Membresía</th><th>Precio</th><th>Estado</th><th>Vigente hasta</th><th>Desde</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($suscripciones as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['nombre']) ?></td>
          <td>$<?= number_format((float) $s['precio'], 2) ?> MXN / <?= $s['intervalo'] === 'anual' ? 'año' : 'mes' ?></td>
          <td>
            <span class="badge <?= $estadoSuscripcionBadge[$s['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($estadoSuscripcionLabel[$s['estado']] ?? $s['estado']) ?></span>
            <?php if (($s['modo'] ?? 'live') === 'prueba'): ?><span class="badge bg-dark" title="Suscripción hecha en modo prueba de Stripe — no fue dinero real">🧪 prueba</span><?php endif; ?>
            <?php if ($s['metodo'] === 'oxxo_recurrente'): ?><span class="badge bg-secondary" title="Se paga mes a mes con un voucher OXXO, no es un cobro automático">🎫 OXXO</span><?php endif; ?>
          </td>
          <td><?= $s['periodo_actual_fin'] ? htmlspecialchars(date('d/m/Y', strtotime($s['periodo_actual_fin']))) : '—' ?></td>
          <td><?= htmlspecialchars(date('d/m/Y', strtotime($s['created_at']))) ?></td>
          <td>
            <?php if ($s['estado'] === 'activa' && $s['metodo'] === 'stripe'): ?>
              <a href="<?= BASE_URL ?>/backend/pagos/membresia_portal.php" class="btn btn-sm btn-outline-primary">Gestionar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php
  // Voucher OXXO pendiente de pago — se muestra debajo de la tabla para
  // cualquier suscripción oxxo_recurrente (activa, en gracia, o incluso
  // pendiente de la primera activación) que tenga uno vigente sin pagar.
  foreach ($suscripciones as $s) {
      if ($s['metodo'] !== 'oxxo_recurrente' || !in_array($s['estado'], ['activa', 'gracia', 'pendiente'], true)) {
          continue;
      }
      $voucher = membresia_oxxo_voucher_pendiente($conn, (int) $s['id']);
      if (!$voucher) {
          continue;
      }
      ?>
      <div class="card p-3 mb-3" style="max-width:480px;background:#fff8ec;border-color:#f7931e;">
        <h2 class="h6 mb-2">🎫 Voucher OXXO pendiente — <?= htmlspecialchars($s['nombre']) ?></h2>
        <?php if ($s['estado'] === 'gracia'): ?>
          <p class="text-danger small mb-2">Tu periodo ya venció — paga este voucher antes de que se agote tu plazo de gracia para no perder el acceso.</p>
        <?php endif; ?>
        <?php if ($voucher['numero']): ?>
          <p class="mb-1"><span class="text-muted small">Número de referencia</span><br><strong style="letter-spacing:1px;word-break:break-all;"><?= htmlspecialchars($voucher['numero']) ?></strong></p>
        <?php endif; ?>
        <?php if ($voucher['vence_en']): ?>
          <p class="text-muted small mb-2">Vence: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($voucher['vence_en']))) ?></p>
        <?php endif; ?>
        <?php if ($voucher['url_voucher']): ?>
          <a href="<?= htmlspecialchars($voucher['url_voucher']) ?>" target="_blank" class="btn btn-sm" style="background:#f7931e;color:#fff;">Ver/imprimir comprobante</a>
        <?php else: ?>
          <p class="text-muted small mb-2">Este voucher es de tu renovación mensual — confírmalo para que se genere el código de pago.</p>
          <button type="button" class="btn btn-sm oxxo-confirmar-voucher" style="background:#f7931e;color:#fff;" data-suscripcion-id="<?= (int) $s['id'] ?>">Confirmar y generar código</button>
          <div class="oxxo-confirmar-msg small text-danger mt-2"></div>
        <?php endif; ?>
      </div>
      <?php
  }
  ?>
<?php else: ?>
  <p class="text-muted">Todavía no tienes una membresía. <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=membresia" style="color:#B8860B;">Conoce Camino Arjuna</a>.</p>
<?php endif; ?>

<?php if ($suscripciones && array_filter($suscripciones, fn($s) => $s['metodo'] === 'oxxo_recurrente')): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  const publishableKey = <?= json_encode(stripe_publishable_key_activa()) ?>;
  const csrfToken = <?= json_encode(csrf_token()) ?>;
  const stripe = Stripe(publishableKey);

  document.querySelectorAll('.oxxo-confirmar-voucher').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const contenedor = this.closest('.card');
      const msg = contenedor.querySelector('.oxxo-confirmar-msg');
      const textoOriginal = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Cargando...';
      msg.textContent = '';
      try {
        const res = await fetch('<?= htmlspecialchars(BASE_URL) ?>/backend/pagos/membresia_oxxo_obtener_secreto.php', {
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
          window.location.reload();
          return;
        }
        const elements = stripe.elements({ clientSecret: data.client_secret });
        const paymentElement = elements.create('payment', { paymentMethodOrder: ['oxxo'] });
        const elementoDiv = document.createElement('div');
        elementoDiv.className = 'mb-2 text-start';
        this.insertAdjacentElement('afterend', elementoDiv);
        paymentElement.mount(elementoDiv);
        this.classList.add('d-none');

        const btnConfirmar = document.createElement('button');
        btnConfirmar.type = 'button';
        btnConfirmar.className = 'btn btn-sm w-100';
        btnConfirmar.style.background = '#f7931e';
        btnConfirmar.style.color = '#fff';
        btnConfirmar.textContent = 'Confirmar voucher';
        elementoDiv.insertAdjacentElement('afterend', btnConfirmar);
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
  });
})();
</script>
<?php endif; ?>
