<?php
// Filas de la tabla de usuarios — compartido por usuarios.php (render inicial,
// dentro de <tbody>) y usuarios_buscar.php (respuesta AJAX del buscador en
// vivo, que reemplaza el <tbody> completo). Espera $usuarios,
// $membresiasDisponibles, $miId, $busqueda, $tipo ya definidos.
$volverQueryStr = http_build_query(array_filter([
    'q' => $busqueda !== '' ? $busqueda : null,
    'tipo' => $tipo !== 'todos' ? $tipo : null,
]));
?>
<?php foreach ($usuarios as $u): ?>
  <tr>
    <td><?= htmlspecialchars((string) $u['username_cache']) ?></td>
    <td><?= htmlspecialchars((string) $u['email_cache']) ?></td>
    <td><?= htmlspecialchars(date('d/m/Y', strtotime($u['created_at']))) ?></td>
    <td>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <input type="hidden" name="accion" value="toggle_prueba">
        <button class="btn btn-sm <?= (int) $u['es_prueba'] === 1 ? 'btn-warning' : 'btn-outline-secondary' ?>"><?= (int) $u['es_prueba'] === 1 ? 'Prueba' : 'Real' ?></button>
      </form>
    </td>
    <td>
      <?php if ((int) $u['id'] === $miId): ?>
        <?= htmlspecialchars($u['rol']) ?> <span class="text-muted small">(tú)</span>
      <?php else: ?>
        <form method="post" class="d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input type="hidden" name="accion" value="cambiar_rol">
          <select name="rol" class="form-select form-select-sm">
            <?php foreach (['estudiante', 'instructor', 'admin'] as $r): ?>
              <option value="<?= $r ?>" <?= $u['rol'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary">Guardar</button>
        </form>
      <?php endif; ?>
    </td>
    <td>
      <?php if ($u['membresia_suscripcion_id']): ?>
        <div class="mb-1">
          <span class="badge" style="background:#6f42c1;">👑 <?= htmlspecialchars($u['membresia_nombre']) ?></span>
          <?php if ((int) $u['membresia_renovacion_automatica'] === 1): ?><span class="badge bg-info text-dark" title="Se renueva automáticamente">🔁</span><?php endif; ?>
        </div>
        <div class="d-flex gap-1">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirMembresiaModal({
            titulo: 'Editar membresía',
            suscripcionId: <?= (int) $u['membresia_suscripcion_id'] ?>,
            usuarioId: <?= (int) $u['id'] ?>,
            usuarioLabel: <?= htmlspecialchars(json_encode($u['username_cache'] . ' — ' . $u['email_cache']), ENT_QUOTES) ?>,
            membresiaId: <?= (int) $u['membresia_activa_id'] ?>,
            metodo: <?= htmlspecialchars(json_encode($u['membresia_metodo']), ENT_QUOTES) ?>,
            fechaInicio: <?= htmlspecialchars(json_encode($u['membresia_fecha_inicio'] ?? date('Y-m-d')), ENT_QUOTES) ?>,
            caduca: <?= $u['membresia_periodo_actual_fin'] ? 'true' : 'false' ?>,
            renovacionAutomatica: <?= (int) $u['membresia_renovacion_automatica'] === 1 ? 'true' : 'false' ?>,
            notificar: false,
            volver: 'usuarios.php',
            volverQuery: <?= htmlspecialchars(json_encode($volverQueryStr), ENT_QUOTES) ?>
          })">Editar</button>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="hidden" name="suscripcion_id" value="<?= (int) $u['membresia_suscripcion_id'] ?>">
            <input type="hidden" name="accion" value="quitar_membresia">
            <button class="btn btn-sm btn-outline-danger">Quitar</button>
          </form>
        </div>
      <?php elseif ($membresiasDisponibles): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirMembresiaModal({
          titulo: 'Otorgar membresía',
          usuarioId: <?= (int) $u['id'] ?>,
          usuarioLabel: <?= htmlspecialchars(json_encode($u['username_cache'] . ' — ' . $u['email_cache']), ENT_QUOTES) ?>,
          volver: 'usuarios.php',
          volverQuery: <?= htmlspecialchars(json_encode($volverQueryStr), ENT_QUOTES) ?>
        })">Otorgar</button>
      <?php else: ?>
        <span class="text-muted small">—</span>
      <?php endif; ?>
    </td>
    <td><?= (int) $u['activo'] === 1 ? 'Activo' : 'Deshabilitado' ?></td>
    <td class="d-flex gap-2 flex-wrap">
      <?php if ((int) $u['id'] !== $miId): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input type="hidden" name="accion" value="toggle_activo">
          <button class="btn btn-sm btn-outline-secondary"><?= (int) $u['activo'] === 1 ? 'Deshabilitar' : 'Habilitar' ?></button>
        </form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('¿Generar una contraseña temporal para este usuario?');">
        <?= csrf_field() ?>
        <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <input type="hidden" name="accion" value="resetear_password">
        <button class="btn btn-sm btn-outline-dark">Resetear contraseña</button>
      </form>
      <?php if ((int) $u['id'] !== $miId): ?>
        <form method="post" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars($u['username_cache'], ENT_QUOTES) ?> permanentemente? Se borrarán también sus pagos, progreso, certificados y publicaciones del foro. Esto no se puede deshacer.');">
          <?= csrf_field() ?>
          <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input type="hidden" name="accion" value="eliminar_usuario">
          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
<?php if (!$usuarios): ?><tr><td colspan="8" class="text-muted">No hay usuarios que coincidan.</td></tr><?php endif; ?>
