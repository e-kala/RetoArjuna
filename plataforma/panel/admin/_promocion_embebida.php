<?php
// Bloque reutilizable dentro de curso/evento/producto. Espera:
// $promocionEmbebida (array), $promocionTipoItem (string).
$pe = $promocionEmbebida;
$precioRegularOferta = (float) ($promocionPrecioRegular ?? 0);
$precioActualOferta = '';
if (!empty($pe['id']) || !empty($pe['valor'])) {
    $precioActualOferta = ($pe['tipo_descuento'] ?? '') === 'precio_fijo'
        ? (float) $pe['valor']
        : round($precioRegularOferta * (1 - ((float) $pe['valor'] / 100)), 2);
}
?>
<details class="col-12 pf-editor-collapsible" <?= !empty($pe['activo']) ? 'open' : '' ?>>
  <summary><strong>🏷️ Promoción pública o preventa</strong><span>Descuento visible para todo el público</span></summary>
  <div class="row g-3 p-3 pt-2">
    <div class="col-12 form-check form-switch ms-2">
      <input type="checkbox" class="form-check-input" role="switch" name="promo_activa" id="promo_activa" <?= !empty($pe['activo']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="promo_activa">Activar oferta pública</label>
    </div>
    <div class="col-md-4"><label class="form-label">Modalidad</label><select class="form-select" name="promo_modalidad">
      <option value="promocion" <?= ($pe['modalidad'] ?? '') === 'promocion' ? 'selected' : '' ?>>Promoción</option>
      <?php if ($promocionTipoItem === 'curso'): ?><option value="preventa" <?= ($pe['modalidad'] ?? '') === 'preventa' ? 'selected' : '' ?>>Preventa</option><?php endif; ?>
    </select></div>
    <div class="col-md-8"><label class="form-label">Nombre visible</label><input class="form-control" name="promo_nombre" value="<?= htmlspecialchars((string) ($pe['nombre'] ?? '')) ?>" placeholder="Ej. Preventa fundadores"></div>
    <input type="hidden" name="promo_tipo_descuento" value="precio_fijo">
    <div class="col-md-4">
      <label class="form-label" for="promo_precio_regular">Precio regular (tachado)</label>
      <div class="input-group"><span class="input-group-text">$</span><input type="number" step="0.01" min="0.01" class="form-control" name="promo_precio_regular" id="promo_precio_regular" value="<?= htmlspecialchars(number_format($precioRegularOferta, 2, '.', '')) ?>"></div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="promo_valor">Precio actual de la oferta</label>
      <div class="input-group"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" class="form-control" name="promo_valor" id="promo_valor" value="<?= $precioActualOferta === '' ? '' : htmlspecialchars(number_format((float) $precioActualOferta, 2, '.', '')) ?>"></div>
    </div>
    <div class="col-md-4 form-check form-switch mt-5 ps-5">
      <input type="checkbox" class="form-check-input" role="switch" name="promo_combinable" id="promo_combinable" <?= !empty($pe['combinable']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="promo_combinable">Acumular con membresía/cupón</label>
    </div>
    <div class="col-md-6"><label class="form-label">Inicio (opcional)</label><input type="datetime-local" class="form-control" name="promo_fecha_inicio" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) ($pe['fecha_inicio'] ?? ''), 0, 16))) ?>"></div>
    <div class="col-md-6"><label class="form-label">Fin (opcional)</label><input type="datetime-local" class="form-control" name="promo_fecha_fin" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) ($pe['fecha_fin'] ?? ''), 0, 16))) ?>"></div>
    <div class="col-12">
      <div class="rounded border bg-light p-3" aria-live="polite">
        <span class="text-muted text-decoration-line-through me-2" id="promo_vista_regular"></span>
        <strong class="fs-5" id="promo_vista_actual"></strong>
        <span class="badge ms-2 pf-badge-promocion" id="promo_vista_badge"></span>
      </div>
      <div class="form-text mt-2">Así se verá el precio en detalle, landing y checkout. En preventa, quienes compren reciben automáticamente los módulos conforme se publiquen.</div>
    </div>
  </div>
</details>
<script>
(function () {
  const regular = document.getElementById('promo_precio_regular');
  const actual = document.getElementById('promo_valor');
  const precioPrincipal = document.querySelector('input[name="precio"]');
  const modalidad = document.querySelector('select[name="promo_modalidad"]');
  const salidaRegular = document.getElementById('promo_vista_regular');
  const salidaActual = document.getElementById('promo_vista_actual');
  const salidaBadge = document.getElementById('promo_vista_badge');
  const moneda = (valor) => Number(valor || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
  function actualizarVista() {
    salidaRegular.textContent = moneda(regular.value);
    salidaActual.textContent = moneda(actual.value);
    salidaBadge.textContent = modalidad && modalidad.value === 'preventa' ? 'Preventa' : '🏷️ Promoción';
  }
  regular.addEventListener('input', function () {
    if (precioPrincipal) precioPrincipal.value = regular.value;
    actualizarVista();
  });
  if (precioPrincipal) precioPrincipal.addEventListener('input', function () {
    regular.value = precioPrincipal.value;
    actualizarVista();
  });
  actual.addEventListener('input', actualizarVista);
  if (modalidad) modalidad.addEventListener('change', actualizarVista);
  actualizarVista();
})();
</script>
