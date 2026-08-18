<?php
$membresia = $conn->query('SELECT * FROM membresias WHERE activo = 1 ORDER BY orden ASC LIMIT 1')->fetch_assoc();
$usuario = current_user();
$esMiembro = $usuario ? usuario_tiene_membresia_activa($usuario['id']) : false;
$resultadoCheckout = $_GET['suscripcion'] ?? '';
$stripeListo = config_esta_lista(STRIPE_PUBLISHABLE_KEY) && config_esta_lista(STRIPE_SECRET_KEY);

$transferenciaPendiente = null;
if ($usuario && !$esMiembro && $membresia) {
    $usuarioIdActual = (int) $usuario['id'];
    $membresiaIdActual = (int) $membresia['id'];
    $stmt = $conn->prepare(
        "SELECT id FROM membresia_suscripciones WHERE usuario_id = ? AND membresia_id = ? AND estado = 'pendiente' LIMIT 1"
    );
    $stmt->bind_param('ii', $usuarioIdActual, $membresiaIdActual);
    $stmt->execute();
    $transferenciaPendiente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
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
  <div class="pf-container"><div class="alert alert-success">🎉 ¡Listo! Tu suscripción está en proceso — en un momento verás tu acceso activo aquí mismo.</div></div>
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

      <div class="pf-callout" style="margin-top:48px;text-align:left;">
        <h2 style="text-align:center;"><?= htmlspecialchars($membresia['nombre']) ?></h2>
        <p style="text-align:center;"><?= htmlspecialchars((string) $membresia['descripcion']) ?></p>
        <p style="font-size:28px;font-weight:800;margin-bottom:24px;text-align:center;">
          $<?= number_format((float) $membresia['precio'], 2) ?> MXN
          <span style="font-size:15px;font-weight:600;opacity:.75;">/ <?= $membresia['intervalo'] === 'anual' ? 'año' : 'mes' ?></span>
        </p>

        <?php if ($esMiembro): ?>
          <p style="margin-bottom:16px;text-align:center;">✔ Ya eres miembro de Camino Arjuna.</p>
          <p style="text-align:center;"><a class="pf-btn pf-btn-primary pf-btn-lg" href="backend/pagos/membresia_portal.php">Gestionar mi membresía</a></p>
        <?php elseif (!$usuario): ?>
          <p style="text-align:center;"><a class="pf-btn pf-btn-primary pf-btn-lg" href="?action=registro">Regístrate para suscribirte</a></p>
        <?php elseif ($transferenciaPendiente): ?>
          <div class="alert alert-warning mb-0">Registramos tu transferencia — en cuanto confirmemos el pago tu membresía queda activa. Si quieres, envía también tu comprobante por WhatsApp para agilizarlo.</div>
        <?php else: ?>
          <ul class="nav nav-tabs mb-3" style="border-color:rgba(255,255,255,.2);">
            <?php if ($stripeListo): ?>
              <li class="nav-item"><button class="nav-link active text-dark" data-bs-toggle="tab" data-bs-target="#tab-tarjeta" type="button">Tarjeta</button></li>
            <?php endif; ?>
            <li class="nav-item"><button class="nav-link <?= $stripeListo ? 'text-dark' : 'active text-dark' ?>" data-bs-toggle="tab" data-bs-target="#tab-transferencia" type="button">Transferencia</button></li>
          </ul>
          <div class="tab-content">
            <?php if ($stripeListo): ?>
            <div class="tab-pane fade show active" id="tab-tarjeta">
              <button id="btnSuscribirse" class="pf-btn pf-btn-primary pf-btn-lg" data-membresia-id="<?= (int) $membresia['id'] ?>">Suscribirme con tarjeta</button>
              <div id="suscribirMsg" class="mt-3" style="color:#fff;"></div>
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
                <input type="file" id="comprobanteFile" class="form-control" accept="image/png,image/jpeg,image/webp,application/pdf">
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
<script>
  <?php if ($stripeListo): ?>
  document.getElementById('btnSuscribirse').addEventListener('click', async function () {
    this.disabled = true;
    const msg = document.getElementById('suscribirMsg');
    msg.textContent = '';
    try {
      const res = await fetch('backend/pagos/membresia_iniciar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          membresia_id: this.dataset.membresiaId,
          csrf_token: <?= json_encode(csrf_token()) ?>,
        }),
      });
      const data = await res.json();
      if (data.success) {
        window.location.href = data.url;
      } else {
        msg.textContent = data.message || 'No se pudo iniciar la suscripción.';
        this.disabled = false;
      }
    } catch (e) {
      msg.textContent = 'Error de conexión. Intenta de nuevo.';
      this.disabled = false;
    }
  });
  <?php endif; ?>

  document.getElementById('btnYaTransferiMembresia').addEventListener('click', async function () {
    this.disabled = true;
    const msg = document.getElementById('transferMembresiaMsg');
    const archivo = document.getElementById('comprobanteFile').files[0];
    const datos = new FormData();
    datos.append('membresia_id', this.dataset.membresiaId);
    datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
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
