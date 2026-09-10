<?php
// Selector de orden compartido por index.php/etiqueta.php/curso.php/evento.php
// — las opciones vienen de foro_ordenes_disponibles() (backend/foro_helpers.php),
// así que agregar una opción ahí la habilita aquí sola, sin tocar cada página.
//
// La página que incluye este archivo debe definir antes:
//   $orden               (string, ya validado con foro_orden_valido())
//   $ordenCamposOcultos  (array asociativo opcional: otros parámetros de la
//                         URL a conservar al cambiar el orden, ej. ['slug' => $slug])
$ordenCamposOcultos = $ordenCamposOcultos ?? [];
$ordenIdBase = 'orden-' . substr(md5(uniqid('', true)), 0, 6);
?>
<form method="get" class="d-flex align-items-center gap-2" id="form<?= $ordenIdBase ?>">
  <?php foreach ($ordenCamposOcultos as $campo => $valor): ?>
    <?php if ($valor !== null && $valor !== ''): ?>
      <input type="hidden" name="<?= htmlspecialchars((string) $campo) ?>" value="<?= htmlspecialchars((string) $valor) ?>">
    <?php endif; ?>
  <?php endforeach; ?>
  <label for="select<?= $ordenIdBase ?>" class="text-muted small fw-bold text-uppercase mb-0"><i class="bi bi-funnel-fill"></i> Ordenar</label>
  <select name="orden" id="select<?= $ordenIdBase ?>" class="form-select form-select-sm" style="width:auto;">
    <?php foreach (foro_ordenes_disponibles() as $clave => $opcion): ?>
      <option value="<?= htmlspecialchars($clave) ?>" <?= $orden === $clave ? 'selected' : '' ?>><?= htmlspecialchars($opcion['etiqueta']) ?></option>
    <?php endforeach; ?>
  </select>
</form>
<script>
document.getElementById('select<?= $ordenIdBase ?>').addEventListener('change', function () {
  document.getElementById('form<?= $ordenIdBase ?>').submit();
});
</script>
