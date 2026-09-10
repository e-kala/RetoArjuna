<?php
// Filas de la tabla de eventos — compartido por eventos.php (render inicial,
// dentro de <tbody>) y eventos_buscar.php (respuesta Ajax del buscador en
// vivo, que reemplaza el <tbody> completo). Espera $eventos ya definido.
foreach ($eventos as $ev): ?>
  <tr>
    <td><a href="../../index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>" target="_blank"><?= htmlspecialchars($ev['titulo']) ?></a></td>
    <td><?= $ev['tipo'] === 'online' ? 'En línea' : 'Presencial' ?></td>
    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ev['fecha_inicio']))) ?></td>
    <td><?= htmlspecialchars(date('d/m/Y', strtotime($ev['created_at']))) ?></td>
    <td><?= (int) $ev['gratuito'] === 1 ? 'Gratis' : '$' . number_format((float) $ev['precio'], 2) ?></td>
    <td><a href="evento_inscritos.php?evento_id=<?= (int) $ev['id'] ?>"><?= (int) $ev['total_inscritos'] ?> ver</a></td>
    <td><a href="lecciones.php?evento_id=<?= (int) $ev['id'] ?>"><?= (int) $ev['total_lecciones'] ?> gestionar</a></td>
    <td data-ajax-estado><?= (int) $ev['activo'] === 1 ? 'Publicado' : 'Oculto' ?></td>
    <td class="d-flex gap-2 flex-wrap">
      <a href="contenido_form.php?tipo=evento&id=<?= (int) $ev['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
      <form method="post" class="d-inline-flex align-items-center" data-ajax="toggle">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
        <input type="hidden" name="accion" value="toggle_activo">
        <div class="form-check form-switch mb-0">
          <input type="checkbox" class="form-check-input" role="switch" <?= (int) $ev['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $ev['activo'] === 1 ? 'Ocultar' : 'Publicar' ?>">
        </div>
      </form>
      <form method="post" class="d-inline" onsubmit="return confirm('¿Convertir esto a curso? El evento se ocultará (no se borra) y se creará un curso nuevo con estos datos, que podrás terminar de ajustar.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
        <input type="hidden" name="accion" value="convertir_a_curso">
        <button class="btn btn-sm btn-outline-dark">🎓 Convertir a curso</button>
      </form>
      <form method="post" class="d-inline" data-ajax="eliminar" data-confirm="¿Eliminar este evento?">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
        <input type="hidden" name="accion" value="eliminar_evento">
        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
      </form>
    </td>
  </tr>
<?php endforeach;
if (!$eventos): ?>
  <tr><td colspan="9" class="text-muted text-center">No hay eventos que coincidan.</td></tr>
<?php endif; ?>
