<?php
// Página pública de reclamo de un regalo (?action=regalo&codigo=X) — quien
// abre el enlace ve qué le regalaron y de quién, y decide aceptar. Todo el
// estado (disponible/reclamado/aceptado/revocado) se lee de `regalos`
// (backend/regalos.php), nunca se recalcula aquí.
$codigo = strtoupper(trim((string) ($_GET['codigo'] ?? '')));
$regalo = $codigo !== '' ? regalo_obtener_por_codigo($codigo) : null;

if (!$regalo) {
    echo '<div class="container" style="margin-top:143px;margin-bottom:60px;"><p>Este enlace de regalo no existe o ya no está disponible.</p></div>';
    return;
}

$esCurso = $regalo['curso_id'] !== null;
$titulo = $esCurso ? $regalo['curso_titulo'] : $regalo['evento_titulo'];
$slug = $esCurso ? $regalo['curso_slug'] : $regalo['evento_slug'];
$modalidad = (float) $regalo['descuento_pct'] >= 100
    ? 'acceso completo'
    : ((float) $regalo['descuento_pct']) . '% de descuento';

$usuario = current_user();
$volverActual = urlencode((string) ($_SERVER['REQUEST_URI'] ?? ''));

if (!$usuario) {
    ?>
    <div class="container" style="margin-top: 143px; margin-bottom: 60px;">
      <div class="card p-4 text-center" style="max-width:480px;margin:0 auto;">
        <div style="font-size:40px;">🎁</div>
        <h1 class="h4 mt-2">Te regalaron <?= htmlspecialchars($modalidad) ?> a <?= htmlspecialchars((string) $titulo) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($regalo['da_username']) ?> te regaló esto — crea tu Cuenta Arjuna (o inicia sesión) para aceptarlo.</p>
        <a href="?action=registro&volver=<?= $volverActual ?>" class="btn btn-primary mb-2" style="background:#f7931e;border-color:#f7931e;">Crear mi cuenta</a>
        <a href="?action=ingreso&volver=<?= $volverActual ?>" class="btn btn-outline-secondary">Ya tengo cuenta</a>
      </div>
    </div>
    <?php
    return;
}

$resultado = regalo_reclamar($regalo, (int) $usuario['id']);
if (!$resultado['success']) {
    echo '<div class="container" style="margin-top:143px;margin-bottom:60px;"><div class="card p-4 text-center" style="max-width:480px;margin:0 auto;"><p class="mb-0">' . htmlspecialchars($resultado['message']) . '</p></div></div>';
    return;
}

// regalo_reclamar() ya validó/actualizó — se relee para tener el estado fresco.
$regalo = regalo_obtener_por_codigo($codigo);
$yaAceptado = $regalo['estado'] === 'aceptado';
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <div class="card p-4 text-center" style="max-width:480px;margin:0 auto;">
    <div style="font-size:40px;">🎁</div>
    <h1 class="h4 mt-2">
      <?= $yaAceptado ? 'Ya aceptaste este regalo' : '¡Tienes un regalo!' ?>
    </h1>
    <p class="text-muted">
      <strong><?= htmlspecialchars($regalo['da_username']) ?></strong> te regaló
      <strong><?= htmlspecialchars($modalidad) ?></strong> a <strong><?= htmlspecialchars((string) $titulo) ?></strong>.
    </p>
    <?php if ($yaAceptado): ?>
      <a href="?action=<?= $esCurso ? 'curso' : 'evento' ?>&slug=<?= urlencode((string) $slug) ?>" class="btn" style="background:#f7931e;color:#fff;">Ir a <?= $esCurso ? 'mi curso' : 'mi evento' ?></a>
    <?php else: ?>
      <button id="btnAceptarRegalo" class="btn" style="background:#f7931e;color:#fff;">Aceptar regalo</button>
      <div id="aceptarRegaloMsg" class="form-text mt-2"></div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$yaAceptado): ?>
<script>
  document.getElementById('btnAceptarRegalo').addEventListener('click', async function () {
    this.disabled = true;
    const res = await fetch('backend/regalo_aceptar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ codigo: <?= json_encode($codigo) ?>, csrf_token: <?= json_encode(csrf_token()) ?> }),
    });
    const data = await res.json();
    const msg = document.getElementById('aceptarRegaloMsg');
    if (data.success) {
      window.location.href = data.redirect;
    } else {
      msg.textContent = data.message || 'No se pudo aceptar el regalo.';
      msg.className = 'form-text text-danger mt-2';
      this.disabled = false;
    }
  });
</script>
<?php endif; ?>
