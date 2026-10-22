<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');

$badges = [
    ['pf-badge-proximo', '📅 Próximo evento', '#2563EB', 'Evento futuro'],
    ['pf-badge-grabado', '🎥 Grabado', '#475569', 'Evento pasado o grabación'],
    ['pf-badge-gratis', '🎉 Gratis', '#D97706', 'Acceso gratuito'],
    ['pf-badge-presencial', '📍 Presencial', '#0891B2', 'Modalidad presencial'],
    ['pf-badge-acceso', '✔ Ya tienes acceso', '#047857', 'Acceso adquirido'],
    ['pf-badge-incluido', 'Incluido con Membresía', '#08D655', 'Beneficio incluido', true],
    ['pf-badge-promocion', '🏷️ Promoción', '#EA580C', 'Oferta promocional'],
    ['pf-badge-preventa', 'Preventa', '#C2410C', 'Venta anticipada'],
    ['pf-badge-precio-miembro', 'Precio especial con membresía', '#08D655', 'Descuento aplicado a un miembro', true],
    ['pf-badge-descuento-miembro', 'Descuento con membresía 20%', '#08D655', 'Beneficio disponible para una Cuenta Arjuna sin membresía', true],
    ['pf-badge-digital', 'Digital', '#4F46E5', 'Producto digital'],
    ['pf-badge-adquirido', 'Adquirido', '#0F766E', 'Producto ya comprado'],
    ['pf-badge-precio', '$450.00 MXN', '#BE123C', 'Precio de producto'],
    ['pf-badge-agotado', 'Agotado', '#334155', 'Producto sin existencias'],
];
$pageTitle = 'Guía privada de badges';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-1">Guía privada de badges</h1>
    <p class="text-muted small mb-0">Referencia visible únicamente para administradores. Cada significado conserva un color propio.</p>
  </div>
</div>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
  <?php foreach ($badges as $badge): ?>
    <?php [$clase, $texto, $hex, $uso] = $badge; $llevaLogo = (bool) ($badge[4] ?? false); ?>
    <div class="col">
      <div class="card h-100 border-0 shadow-sm p-3">
        <div class="mb-3"><span class="badge rounded-pill <?= htmlspecialchars($clase) ?>"><?php if ($llevaLogo): ?><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> <?php endif; ?><?= htmlspecialchars($texto) ?></span></div>
        <strong><?= htmlspecialchars($uso) ?></strong>
        <code class="mt-1"><?= htmlspecialchars($hex) ?></code>
        <span class="small text-muted mt-1">.<?= htmlspecialchars($clase) ?></span>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
