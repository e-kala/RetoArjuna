<?php
// Página mostrada cuando no hay ningún evento próximo agendado (ver el
// botón nuevo en eventos_catalogo.php, pestaña "Próximos", y el botón "Ver
// el programa" de la landing en la raíz del sitio — ambos apuntan aquí en
// vez de a la landing estática vieja reto-arjuna.html).
//
// Dos caminos, ninguno bloquea al otro:
// 1. Apuntarse a la lista de espera del próximo Reto Arjuna — requiere
//    cuenta real (nunca un correo suelto sin sesión, a propósito: así se
//    reusa el flujo de registro/login ya existente en vez de inventar un
//    formulario/tabla de "solo correo" aparte). Sigue el mismo patrón
//    "volver" documentado en FLUJO_SESION_REDIRECCIONES.md: el botón manda
//    a ?action=registro&volver=<esta misma URL>, y al volver ya con sesión
//    activa, esta misma página hace el INSERT en evento_lista_espera.
// 2. Hacer el reto con las grabaciones del último evento — manda al
//    catálogo de eventos, pestaña "Pasados/grabados" (?tab=pasados),
//    accesible sin sesión (evento_detalle.php ya decide ahí si puede ver el
//    video o necesita comprar/crear cuenta primero).
$usuarioProximoEvento = current_user();

$yaEnListaEspera = false;
$acabaDeApuntarse = false;
if ($usuarioProximoEvento) {
    $stmt = $conn->prepare('SELECT id FROM evento_lista_espera WHERE usuario_id = ?');
    $stmt->bind_param('i', $usuarioProximoEvento['id']);
    $stmt->execute();
    $filaExistente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($filaExistente) {
        $yaEnListaEspera = true;
    } else {
        // Recién completó login_user() (registro o login normal) y volvió
        // aquí mismo (ver el "volver" armado abajo) — se apunta solo, sin
        // necesitar un clic extra ni un formulario aparte. INSERT IGNORE
        // por si acaso (recarga rápida/doble clic entre el SELECT de
        // arriba y este INSERT) — uq_evento_lista_espera_usuario ya evita
        // el duplicado real, esto solo evita el error de PHP si topa con esa
        // clave única.
        $stmt = $conn->prepare('INSERT IGNORE INTO evento_lista_espera (usuario_id) VALUES (?)');
        $stmt->bind_param('i', $usuarioProximoEvento['id']);
        $stmt->execute();
        $stmt->close();
        $yaEnListaEspera = true;
        $acabaDeApuntarse = true;
    }
}

$volverActual = urlencode((string) ($_SERVER['REQUEST_URI'] ?? ''));
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px; max-width: 720px;">
  <div class="text-center mb-4">
    <h1 class="fw-bold">Todavía no hay un próximo Reto Arjuna agendado</h1>
    <p class="text-muted">En cuanto se anuncie una nueva fecha, será el primer lugar donde lo vas a ver — mientras tanto, aquí tienes dos opciones.</p>
  </div>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card h-100 p-4 text-center">
        <div style="font-size:36px;">🔔</div>
        <h2 class="h5 fw-bold mt-2">Avísenme del próximo</h2>
        <p class="text-muted small mb-3">Crea tu Cuenta Arjuna (o inicia sesión si ya tienes una) y quedas en la lista de espera automáticamente — te avisamos en cuanto haya fecha.</p>
        <?php if ($yaEnListaEspera): ?>
          <p class="text-success mb-0">✔ Ya estás en la lista de espera.</p>
        <?php else: ?>
          <a href="?action=registro&volver=<?= $volverActual ?>" class="btn mb-2" style="background:#f7931e;color:#fff;">Crear mi cuenta</a>
          <a href="?action=ingreso&volver=<?= $volverActual ?>" class="btn btn-outline-secondary btn-sm">Ya tengo cuenta</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 p-4 text-center">
        <div style="font-size:36px;">🎥</div>
        <h2 class="h5 fw-bold mt-2">Haz el reto ahora, con las grabaciones</h2>
        <p class="text-muted small mb-3">No hace falta esperar a la próxima fecha en vivo — puedes vivir el Reto Arjuna completo con las grabaciones de la edición más reciente, a tu propio ritmo.</p>
        <a href="?action=eventos&tab=pasados" class="btn btn-outline-dark">Ver eventos pasados y grabados</a>
      </div>
    </div>
  </div>
</div>
<?php if ($acabaDeApuntarse): ?>
<script>
  $(function () {
    $.notify('¡Listo! Quedaste en la lista de espera del próximo Reto Arjuna.', { className: 'success', position: 'top right', autoHideDelay: 4000 });
  });
</script>
<?php endif; ?>
