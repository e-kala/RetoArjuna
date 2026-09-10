<?php
// Renderiza una sola <tr> de la tabla de usuarios — compartido por
// _usuarios_filas.php (listado completo) y usuarios.php (respuesta Ajax de
// una sola fila tras cambiar rol/membresía/estado/etc., ver data-ajax en
// _footer.php). Vive en su propia función para poder re-pintar una fila sin
// tener que reconstruir el HTML a mano en JS — más robusto dado cuántas
// columnas dependen de un mismo cambio (ej. toggle_prueba también decide si
// el botón "Reiniciar" existe).
function pf_render_fila_usuario(array $u, int $miId, string $busqueda, string $tipo, array $membresiasDisponibles): string
{
    $volverQueryStr = http_build_query(array_filter([
        'q' => $busqueda !== '' ? $busqueda : null,
        'tipo' => $tipo !== 'todos' ? $tipo : null,
    ]));
    ob_start();
    ?>
  <tr>
    <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $u['id'] ?>" target="_blank"><?= htmlspecialchars((string) $u['username_cache']) ?></a></td>
    <td><?= htmlspecialchars((string) $u['email_cache']) ?></td>
    <td><?= htmlspecialchars(date('d/m/Y', strtotime($u['created_at']))) ?></td>
    <td>
      <form method="post" data-ajax="accion">
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
        <form method="post" class="d-flex gap-2" data-ajax="accion">
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
          <span class="badge" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> <?= htmlspecialchars($u['membresia_nombre']) ?></span>
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
            stripeCustomerId: <?= htmlspecialchars(json_encode($u['membresia_stripe_customer_id'] ?? ''), ENT_QUOTES) ?>,
            stripeSubscriptionId: <?= htmlspecialchars(json_encode($u['membresia_stripe_subscription_id'] ?? ''), ENT_QUOTES) ?>,
            notificar: false,
            volver: 'usuarios.php',
            volverQuery: <?= htmlspecialchars(json_encode($volverQueryStr), ENT_QUOTES) ?>
          })">Editar</button>
          <form method="post" data-ajax="accion">
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
    <td data-ajax-estado><?= (int) $u['activo'] === 1 ? 'Activo' : 'Deshabilitado' ?></td>
    <td class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirAccesoModal({
        usuarioId: <?= (int) $u['id'] ?>,
        usuarioLabel: <?= htmlspecialchars(json_encode($u['username_cache'] . ' — ' . $u['email_cache']), ENT_QUOTES) ?>,
        volver: 'usuarios.php',
        volverQuery: <?= htmlspecialchars(json_encode($volverQueryStr), ENT_QUOTES) ?>
      })">Dar acceso</button>
      <?php if ((int) $u['id'] !== $miId): ?>
        <form method="post" class="d-flex align-items-center" data-ajax="accion">
          <?= csrf_field() ?>
          <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input type="hidden" name="accion" value="toggle_activo">
          <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input" role="switch" <?= (int) $u['activo'] === 1 ? 'checked' : '' ?> aria-label="<?= (int) $u['activo'] === 1 ? 'Deshabilitar' : 'Habilitar' ?>">
          </div>
        </form>
      <?php endif; ?>
      <?php if ((int) $u['es_prueba'] === 1): ?>
        <form method="post" data-ajax="accion" data-confirm="¿Reiniciar a <?= htmlspecialchars($u['username_cache'], ENT_QUOTES) ?> como si fuera nuevo? Se borrarán sus inscripciones, compras, membresía, progreso, certificados e intentos de quiz — para poder volver a probar esos flujos desde cero. El usuario y su contraseña no se tocan.">
          <?= csrf_field() ?>
          <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input type="hidden" name="accion" value="reiniciar_prueba">
          <button class="btn btn-sm btn-outline-warning">Reiniciar</button>
        </form>
      <?php endif; ?>
      <form method="post" data-ajax="accion" data-confirm="¿Generar una contraseña temporal para este usuario?">
        <?= csrf_field() ?>
        <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <input type="hidden" name="accion" value="resetear_password">
        <button class="btn btn-sm btn-outline-dark">Resetear contraseña</button>
      </form>
      <?php if ((int) $u['id'] !== $miId): ?>
        <form method="post" data-ajax="eliminar" data-confirm="¿Eliminar a <?= htmlspecialchars($u['username_cache'], ENT_QUOTES) ?> permanentemente? Se borrarán también sus pagos, progreso, certificados y publicaciones del foro. Esto no se puede deshacer.">
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
    <?php
    return ob_get_clean();
}
