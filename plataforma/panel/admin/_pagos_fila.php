<?php
// Imprime una <tr> de la tabla de pagos, con sus acciones — compartido
// entre pagos.php (pestaña Pendientes) y pagos_usuario.php (todo lo de un
// usuario), para no duplicar la lógica de qué botones mostrar según
// origen/estado/tipo. $volverQs (opcional) es la query string a la que
// regresar tras una acción (ej. "tab=pendientes" o "usuario_id=23") — se
// manda como campo oculto para que _pagos_acciones.php sepa a dónde
// redirigir en el caso sin-Ajax.
function pf_pintar_fila_pago(array $p, array $estadoBadge, string $volverQs = ''): void
{
    ?>
    <tr>
      <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $p['usuario_id'] ?>" target="_blank"><?= htmlspecialchars((string) $p['username_cache']) ?></a><br><span class="text-muted small"><?= htmlspecialchars((string) $p['email_cache']) ?></span></td>
      <td><?= htmlspecialchars($p['tipo']) ?></td>
      <td>
        <?php if ($p['origen'] === 'pago' && $p['curso_id']): ?>
          <a href="../../index.php?action=curso&slug=<?= urlencode($p['curso_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['articulo']) ?></a>
        <?php elseif ($p['origen'] === 'pago' && $p['evento_id']): ?>
          <a href="../../index.php?action=evento&slug=<?= urlencode($p['evento_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['articulo']) ?></a>
        <?php elseif ($p['origen'] === 'pago' && $p['producto_id']): ?>
          <a href="../../index.php?action=producto&slug=<?= urlencode($p['producto_slug']) ?>" target="_blank"><?= htmlspecialchars((string) $p['articulo']) ?></a>
        <?php else: ?>
          <?= htmlspecialchars((string) $p['articulo']) ?>
        <?php endif; ?>
      </td>
      <td><?= $p['cantidad'] !== null ? (int) $p['cantidad'] : '—' ?></td>
      <td>$<?= number_format((float) $p['monto'], 2) ?></td>
      <td><?= htmlspecialchars($p['metodo_pago']) ?></td>
      <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['created_at']))) ?></td>
      <td data-ajax-estado>
        <span class="badge <?= $estadoBadge[$p['estado']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($p['estado']) ?></span>
        <?php if (($p['modo'] ?? 'live') === 'prueba'): ?><span class="badge bg-dark" title="Pago hecho en modo prueba de Stripe — no es dinero real">🧪 prueba</span><?php endif; ?>
      </td>
      <td>
        <?php if ($p['comprobante_url']): ?><a href="<?= htmlspecialchars($p['comprobante_url']) ?>" target="_blank">Comprobante</a><?php endif; ?>
        <?php if ($p['direccion_envio']): ?><div class="small text-muted"><?= nl2br(htmlspecialchars($p['direccion_envio'])) ?></div><?php endif; ?>
      </td>
      <td class="d-flex gap-2">
        <?php if ($p['origen'] === 'membresia'): ?>
          <a href="membresias.php" class="btn btn-sm btn-outline-primary">Gestionar</a>
        <?php else: ?>
          <?php
          // Confirmar/Rechazar a mano solo tiene sentido para transferencia
          // — el admin es quien de verdad decide viendo el comprobante. Un
          // pago con tarjeta pendiente nunca necesita esto: si el cobro se
          // completó, Stripe ya lo confirmó solo (checkout.php/webhook); si
          // sigue pendiente es un intento incompleto (ver pestaña
          // "Incompletos" en pagos.php), no una decisión pendiente.
          ?>
          <?php if ($p['estado'] === 'pendiente' && $p['metodo_pago'] === 'transferencia'): ?>
            <form method="post" data-ajax="accion" data-confirm="¿Confirmar este pago por transferencia? Verifica primero el comprobante — esta acción otorga el acceso de inmediato.">
              <?= csrf_field() ?>
              <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="accion" value="validar_pago">
              <input type="hidden" name="estado" value="confirmado">
              <?php if ($volverQs !== ''): ?><input type="hidden" name="volver_qs" value="<?= htmlspecialchars($volverQs, ENT_QUOTES) ?>"><?php endif; ?>
              <button class="btn btn-sm btn-success">Confirmar</button>
            </form>
            <form method="post" data-ajax="accion">
              <?= csrf_field() ?>
              <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="accion" value="validar_pago">
              <input type="hidden" name="estado" value="rechazado">
              <?php if ($volverQs !== ''): ?><input type="hidden" name="volver_qs" value="<?= htmlspecialchars($volverQs, ENT_QUOTES) ?>"><?php endif; ?>
              <button class="btn btn-sm btn-danger">Rechazar</button>
            </form>
          <?php endif; ?>
          <?php if ($p['origen'] === 'pago' && $p['producto_id'] && $p['estado'] === 'confirmado'): ?>
            <form method="post" data-ajax="accion" data-confirm="¿Quitar el acceso de <?= htmlspecialchars((string) $p['username_cache'], ENT_QUOTES) ?> a este producto? Dejará de verlo como comprado, aunque el registro del pago se conserva.">
              <?= csrf_field() ?>
              <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="accion" value="revocar_acceso_producto">
              <?php if ($volverQs !== ''): ?><input type="hidden" name="volver_qs" value="<?= htmlspecialchars($volverQs, ENT_QUOTES) ?>"><?php endif; ?>
              <button class="btn btn-sm btn-outline-warning">Quitar acceso</button>
            </form>
          <?php endif; ?>
          <?php
          // Borrar la fila también revoca el acceso si el pago era de un
          // producto y estaba confirmado (usuario_compro_producto() solo
          // mira si existe la fila) — a diferencia de "Quitar acceso", acá
          // se pierde el historial completo, sin dejar rastro de quién lo
          // quitó. El aviso lo deja explícito para que no sea una sorpresa.
          $eliminarRevocaAcceso = $p['origen'] === 'pago' && $p['producto_id'] && $p['estado'] === 'confirmado';
          $mensajeEliminar = $eliminarRevocaAcceso
              ? '¿Eliminar este pago? Como está confirmado, esto también le quita el acceso al producto a ' . (string) $p['username_cache'] . ' — a diferencia de «Quitar acceso», aquí se pierde el historial del pago por completo. Esta acción no se puede deshacer.'
              : '¿Eliminar este pago? Esta acción no se puede deshacer.';
          ?>
          <form method="post" data-ajax="eliminar" data-confirm="<?= htmlspecialchars($mensajeEliminar, ENT_QUOTES) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_pago">
            <?php if ($volverQs !== ''): ?><input type="hidden" name="volver_qs" value="<?= htmlspecialchars($volverQs, ENT_QUOTES) ?>"><?php endif; ?>
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php
}
