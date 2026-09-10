<?php
// Filas de la tabla de productos — compartido por productos.php (render
// inicial, dentro de <tbody>) y productos_buscar.php (respuesta Ajax del
// buscador en vivo, que reemplaza el <tbody> completo). Espera $productos ya
// definido.
foreach ($productos as $p): ?>
  <tr>
    <td><a href="../../index.php?action=producto&slug=<?= urlencode($p['slug']) ?>" target="_blank"><?= htmlspecialchars($p['nombre']) ?></a></td>
    <td><?= $p['tipo'] === 'fisico' ? 'Físico' : 'Digital' ?></td>
    <td>$<?= number_format((float) $p['precio'], 2) ?></td>
    <td><?= $p['stock'] !== null ? (int) $p['stock'] : '—' ?></td>
    <td><?= htmlspecialchars(date('d/m/Y', strtotime($p['created_at']))) ?></td>
    <td data-ajax-estado><?= (int) $p['activo'] === 1 ? 'Publicado' : 'Oculto' ?></td>
    <td class="d-flex gap-2">
      <a href="producto_form.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
      <form method="post" class="d-inline-flex align-items-center" data-ajax="toggle">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="accion" value="toggle_activo">
        <div class="form-check form-switch mb-0">
          <input type="checkbox" class="form-check-input" role="switch" <?= (int) $p['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $p['activo'] === 1 ? 'Ocultar' : 'Publicar' ?>">
        </div>
      </form>
      <form method="post" class="d-inline" data-ajax="eliminar" data-confirm="¿Eliminar este producto?">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="accion" value="eliminar_producto">
        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
      </form>
    </td>
  </tr>
<?php endforeach;
if (!$productos): ?>
  <tr><td colspan="7" class="text-muted text-center">No hay productos que coincidan.</td></tr>
<?php endif; ?>
