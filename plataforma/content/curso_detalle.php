<?php
require_once __DIR__ . '/../backend/ofertas.php';
$slug = $_GET['slug'] ?? '';
$stmt = $conn->prepare('SELECT * FROM cursos WHERE slug = ? AND activo = 1 LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso) {
    echo '<div class="container" style="margin-top:143px;"><p>Curso no encontrado.</p></div>';
    return;
}

$cursoId = (int) $curso['id'];
$usuario = current_user();
// Un borrador nunca se muestra a un alumno (todavía se está editando) —
// un admin sí los ve, marcados aparte, para poder encontrarlos y terminarlos.
$esAdminCurso = $usuario && $usuario['rol'] === 'admin';
$stmt = $conn->prepare($esAdminCurso
    ? 'SELECT * FROM lecciones WHERE curso_id = ? ORDER BY orden'
    : 'SELECT * FROM lecciones WHERE curso_id = ? AND estado_publicacion = "publicado" ORDER BY orden');
$stmt->bind_param('i', $cursoId);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tieneAcceso = $usuario ? usuario_tiene_acceso_curso($usuario['id'], $cursoId) : false;
$esMiembro = $usuario ? usuario_tiene_membresia_activa($usuario['id']) : false;
$incluidoPorMembresia = $esMiembro && (int) $curso['incluido_membresia'] === 1;

// Motor de ofertas (checklist.txt OF01-OF10) — única fuente de verdad de
// precio/badge/CTA, la misma que usa checkout.php.
$codigoCupon = $_GET['cupon'] ?? ($_SESSION['cupon_pendiente'] ?? null);
$ofertaItem = [
    'id' => $cursoId,
    'precio' => (float) $curso['precio'],
    'gratuito' => (bool) $curso['gratuito'],
    'incluido_membresia' => (bool) $curso['incluido_membresia'],
    'solo_miembros' => false,
    'descuento_miembro_pct' => $curso['descuento_miembro_pct'] !== null ? (float) $curso['descuento_miembro_pct'] : null,
    'ya_tiene_acceso' => $tieneAcceso,
];
$oferta = resolver_oferta($conn, 'curso', $ofertaItem, $usuario, $codigoCupon);

// Flujo de venta (landing comercial enlazada, ver panel/admin/
// contenido_form.php): quien NO tiene acceso todavía se manda a la landing
// en vez de ver esta página de venta genérica — quien ya tiene acceso
// (comprado o incluido por membresía) sigue entrando aquí normal, nunca a
// la landing. Un admin tampoco se redirige, para poder revisar/editar el
// curso sin estorbos. Tampoco se redirige si ?auto=1 (ver más abajo,
// "Auto-inscripción al volver de crear cuenta/login"): ese parámetro es la
// señal explícita de "vengo de dar clic en Inscribirme/Comprar y ya me
// autentiqué, completa la acción" — sin esta excepción, cualquier curso con
// landing vinculada rebotaba de vuelta a la landing en ese momento exacto y
// el auto-checkout nunca se alcanzaba a ejecutar.
if (!$esAdminCurso && $curso['landing_page_id'] && !in_array($oferta['estado'], ['acceso', 'incluido_membresia'], true) && !($usuario && ($_GET['auto'] ?? '') === '1')) {
    $stmtLanding = $conn->prepare('SELECT slug FROM landing_pages WHERE id = ? AND activo = 1');
    $stmtLanding->bind_param('i', $curso['landing_page_id']);
    $stmtLanding->execute();
    $landingVinculada = $stmtLanding->get_result()->fetch_assoc();
    $stmtLanding->close();
    if ($landingVinculada) {
        header('Location: ' . BASE_URL . '/index.php?action=landing&slug=' . urlencode($landingVinculada['slug']));
        exit;
    }
}

$volverActual = urlencode((string) ($_SERVER['REQUEST_URI'] ?? ''));
// Mismo valor que $volverActual, pero con &auto=1 — ahorra el clic extra en
// "Comprar"/"Inscribirme" al regresar de crear cuenta o iniciar sesión (ver
// más abajo, "Auto-inscripción al volver de crear cuenta/login").
$volverActualConAuto = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '') . (str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ? '&' : '?') . 'auto=1');

// Auto-inscripción al volver de crear cuenta/login: si el clic que trajo
// aquí era explícitamente para inscribirse (?auto=1, ver el link de abajo),
// ya no hace falta un segundo clic en "Comprar"/"Inscribirme" — si es de
// pago, se manda directo a checkout.php antes de imprimir nada; si es
// gratis/incluido en membresía, el botón se auto-dispara más abajo por JS
// (necesita la petición AJAX con CSRF, no puede ser un header() plano).
$quiereAutoInscribirse = $usuario && $oferta['estado'] !== 'acceso' && ($_GET['auto'] ?? '') === '1';
$puedeAccederGratisCurso = $oferta['estado'] === 'gratuito' || $oferta['estado'] === 'incluido_membresia' || $oferta['acceso_gratis_automatico'];
if ($quiereAutoInscribirse && !$puedeAccederGratisCurso) {
    header('Location: backend/pagos/checkout.php?curso_id=' . $cursoId . ($codigoCupon ? '&cupon=' . urlencode($codigoCupon) : ''));
    exit;
}

$completadas = [];
if ($usuario) {
    $stmt = $conn->prepare('SELECT leccion_id FROM progreso WHERE usuario_id = ? AND curso_id = ? AND completado = 1');
    $stmt->bind_param('ii', $usuario['id'], $cursoId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $completadas[(int) $row['leccion_id']] = true;
    }
    $stmt->close();
}
$porcentaje = count($lecciones) > 0 ? (int) round(count($completadas) / count($lecciones) * 100) : 0;
$mostrarBienvenida = $tieneAcceso && ($_GET['bienvenida'] ?? '') === '1';
$primeraLeccion = $lecciones[0] ?? null;

$resumenCalificacion = calificacion_resumen($cursoId, null);
$puedeCalificar = $usuario && usuario_puede_calificar_curso($usuario['id'], $cursoId);
$miCalificacion = $usuario ? obtener_calificacion_usuario($usuario['id'], $cursoId, null) : null;

// Prestaciones y regalos (checklist "Representar posibilidad de regalar") —
// solo quien ya tiene acceso puede regalarlo, y solo si el admin configuró
// el permiso para este curso (regalo_configuracion). $regaloEstado siempre
// se lee fresco de la tabla `regalos`, nunca se recalcula aparte.
$regaloConfig = $tieneAcceso ? regalo_configuracion_obtener($cursoId, null) : null;
$regaloEstado = $regaloConfig && $usuario ? regalo_estado_usuario($regaloConfig, $usuario['id']) : null;
$regaloMisEnlaces = $regaloConfig && $usuario ? regalos_generados_por($usuario['id'], $regaloConfig['id']) : [];
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=cursos" class="d-inline-block mb-3">&larr; Volver al catálogo</a>

  <?php if ($mostrarBienvenida): ?>
    <div class="card p-4 mb-4" style="background:#fff3e0;border:1px solid #f7931e;">
      <h2 class="h4 mb-2">🎉 ¡Bienvenido a <?= htmlspecialchars($curso['titulo']) ?>!</h2>
      <p class="mb-3">Tu compra fue confirmada y ya tienes acceso completo a este curso.</p>
      <?php if ($primeraLeccion): ?>
        <a href="?action=leccion&id=<?= (int) $primeraLeccion['id'] ?>" class="btn" style="background:#f7931e;color:#fff;">Comenzar curso</a>
      <?php endif; ?>
    </div>
    <script>
      $(function () {
        $.notify('¡Listo! Tu pago fue confirmado y ya tienes acceso a este curso.', { className: 'success', position: 'top right', autoHideDelay: 4000 });
      });
    </script>
  <?php endif; ?>

  <div class="d-flex align-items-center flex-wrap gap-2">
    <h1 class="mb-0"><?= htmlspecialchars($curso['titulo']) ?></h1>
    <?php if ($esAdminCurso): ?>
      <a href="panel/admin/contenido_form.php?tipo=curso&id=<?= $cursoId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Editar</a>
    <?php endif; ?>
  </div>
  <?php if ($resumenCalificacion['total'] > 0): ?>
    <p class="text-muted mb-2">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <i class="bi <?= $i <= round($resumenCalificacion['promedio']) ? 'bi-star-fill' : 'bi-star' ?>" style="color:#f7931e;"></i>
      <?php endfor; ?>
      <?= $resumenCalificacion['promedio'] ?> (<?= $resumenCalificacion['total'] ?> calificación<?= $resumenCalificacion['total'] === 1 ? '' : 'es' ?>)
    </p>
  <?php endif; ?>
  <div class="pf-contenido-html"><?= (string) $curso['descripcion'] ?></div>
  <p>
    <span class="badge bg-secondary"><?= htmlspecialchars($curso['nivel']) ?></span>
    <?php if ($oferta['estado'] === 'acceso'): ?>
      <span class="badge bg-success">Ya tienes acceso</span>
    <?php elseif ($oferta['estado'] === 'incluido_membresia'): ?>
      <span class="badge" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido en tu membresía</span>
    <?php elseif ($oferta['estado'] === 'gratuito'): ?>
      <span class="badge" style="background:#f7931e;">Gratuito</span>
    <?php elseif ($oferta['estado'] === 'oferta'): ?>
      <span class="badge text-decoration-line-through bg-secondary">$<?= number_format($oferta['precio_regular'], 2) ?></span>
      <span class="badge" style="background:#f7931e;"><?= $oferta['precio_final'] > 0 ? '$' . number_format($oferta['precio_final'], 2) . ' MXN' : 'Gratis' ?></span>
      <span class="badge bg-secondary"><?= htmlspecialchars((string) $oferta['oferta_nombre']) ?></span>
    <?php else: ?>
      <span class="badge" style="background:#f7931e;">$<?= number_format((float) $curso['precio'], 2) ?> MXN</span>
      <?php if ($usuario && (int) $curso['incluido_membresia'] === 1): ?>
        <span class="badge" style="background:#6f42c1;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con membresía</span>
      <?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if ($tieneAcceso): ?>
    <div class="progress mb-3" style="height: 8px;">
      <div class="progress-bar" style="width: <?= $porcentaje ?>%; background:#f7931e;"></div>
    </div>
    <p class="text-muted small"><?= $porcentaje ?>% completado</p>
    <a href="<?= $curso['foro_url'] ? htmlspecialchars(navbar_href($curso['foro_url'], '../')) : 'foro/curso.php?curso_id=' . $cursoId ?>" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-chat-square-text"></i> Discutir este curso en el foro</a>
  <?php endif; ?>

  <?php if ($regaloConfig): ?>
    <?php
    $regaloModalidad = (float) $regaloConfig['descuento_pct'] >= 100
        ? 'acceso'
        : ((float) $regaloConfig['descuento_pct']) . '% de descuento';
    ?>
    <div class="card p-3 mb-3" style="max-width:480px;background:#fff8ec;border-color:#f7931e;">
      <h2 class="h6 mb-1">🎁 Regalar <?= htmlspecialchars($regaloModalidad) ?> — <?= (int) $regaloEstado['disponibles'] ?> disponible<?= (int) $regaloEstado['disponibles'] === 1 ? '' : 's' ?></h2>
      <?php if ($regaloEstado['puede_generar']): ?>
        <button id="btnGenerarRegalo" class="btn btn-sm mt-1" style="background:#f7931e;color:#fff;max-width:220px;">Generar enlace de regalo</button>
        <div id="generarRegaloMsg" class="form-text mt-1"></div>
      <?php elseif ($regaloEstado['motivo_bloqueo']): ?>
        <p class="text-muted small mb-0"><?= htmlspecialchars($regaloEstado['motivo_bloqueo']) ?></p>
      <?php endif; ?>
      <?php if ($regaloMisEnlaces): ?>
        <ul class="list-group list-group-flush mt-2" id="listaRegalos">
          <?php foreach ($regaloMisEnlaces as $r): ?>
            <?php
            $regaloBadge = match ($r['estado']) {
                'disponible' => '<span class="badge bg-secondary">Disponible</span>',
                'reclamado' => '<span class="badge" style="background:#f7931e;">Reclamado por ' . htmlspecialchars((string) $r['recibe_username']) . '</span>',
                'aceptado' => '<span class="badge bg-success">Aceptado por ' . htmlspecialchars((string) $r['recibe_username']) . '</span>',
                'revocado' => '<span class="badge bg-dark">Revocado</span>',
                default => '',
            };
            ?>
            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center gap-2">
              <?php if ($r['estado'] === 'disponible'): ?>
                <code class="small text-truncate"><?= htmlspecialchars(BASE_URL . '/index.php?action=regalo&codigo=' . $r['codigo']) ?></code>
              <?php else: ?>
                <span class="small text-muted">Código <?= htmlspecialchars($r['codigo']) ?></span>
              <?php endif; ?>
              <?= $regaloBadge ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="list-group mb-4">
    <?php foreach ($lecciones as $leccion): ?>
      <?php $desbloqueada = $tieneAcceso || (int) $leccion['vista_previa'] === 1; ?>
      <?php if ($desbloqueada): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center">
          <a href="?action=leccion&id=<?= (int) $leccion['id'] ?>" class="text-decoration-none flex-grow-1">
            <?php if (!empty($completadas[(int) $leccion['id']])): ?><i class="bi bi-check-circle-fill text-success"></i><?php endif; ?>
            <?= htmlspecialchars($leccion['titulo']) ?>
          </a>
          <?php if ($esAdminCurso && $leccion['estado_publicacion'] === 'borrador'): ?><span class="badge bg-secondary me-2">Borrador</span><?php endif; ?>
          <?php if (!$tieneAcceso): ?><span class="badge bg-info me-2">Demo</span><?php endif; ?>
          <?php if ($tieneAcceso): ?>
            <a href="<?= $leccion['foro_url'] ? htmlspecialchars(navbar_href($leccion['foro_url'], '../')) : 'foro/curso.php?curso_id=' . $cursoId . '&leccion_id=' . (int) $leccion['id'] ?>" class="text-muted small" title="Discutir esta lección en el foro">
              <i class="bi bi-chat-square-text"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <span class="list-group-item d-flex justify-content-between align-items-center text-muted">
          <span><i class="bi bi-lock-fill"></i> <?= htmlspecialchars($leccion['titulo']) ?></span>
        </span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($puedeCalificar): ?>
    <div class="card p-3 mb-4" style="max-width:480px;">
      <h2 class="h6 mb-2"><?= $miCalificacion ? 'Tu calificación' : '¿Qué te pareció este curso?' ?></h2>
      <div id="calificarEstrellas" data-valor="<?= (int) ($miCalificacion['puntuacion'] ?? 0) ?>">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="bi <?= $i <= (int) ($miCalificacion['puntuacion'] ?? 0) ? 'bi-star-fill' : 'bi-star' ?> estrella-calificar" data-estrella="<?= $i ?>" style="color:#f7931e;font-size:1.4rem;cursor:pointer;"></i>
        <?php endfor; ?>
      </div>
      <textarea id="calificarComentario" class="form-control mt-2" rows="2" maxlength="500" placeholder="Comentario opcional"><?= htmlspecialchars((string) ($miCalificacion['comentario'] ?? '')) ?></textarea>
      <button id="btnGuardarCalificacion" class="btn btn-sm mt-2" style="background:#f7931e;color:#fff;">Guardar calificación</button>
      <div id="calificarMsg" class="form-text mt-1"></div>
    </div>
  <?php endif; ?>

  <?php if ($oferta['estado'] !== 'acceso'): ?>
    <div class="card p-3" style="max-width:480px;">
      <?php if (!$usuario): ?>
        <p class="mb-2">Crea tu Cuenta Arjuna para inscribirte — en cuanto termines, quedas inscrito automáticamente, sin pasos extra.</p>
        <a href="?action=registro&volver=<?= $volverActualConAuto ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn" style="background:#f7931e;color:#fff;"><?= $oferta['estado'] === 'gratuito' || $oferta['acceso_gratis_automatico'] ? 'Inscribirme gratis' : 'Inscribirme' ?></a>
      <?php elseif ($oferta['estado'] === 'gratuito' || $oferta['acceso_gratis_automatico']): ?>
        <button id="btnInscribirseCurso" class="btn" style="background:#f7931e;color:#fff;">Inscribirme gratis</button>
        <div id="inscribirCursoMsg" class="form-text mt-2"></div>
      <?php elseif ($oferta['estado'] === 'incluido_membresia'): ?>
        <button id="btnInscribirseCurso" class="btn" style="background:#f7931e;color:#fff;">Accesar gratis con mi membresía</button>
        <div id="inscribirCursoMsg" class="form-text mt-2"></div>
      <?php else: ?>
        <a href="backend/pagos/checkout.php?curso_id=<?= $cursoId ?><?= $codigoCupon ? '&cupon=' . urlencode($codigoCupon) : '' ?>" class="btn" style="background:#f7931e;color:#fff;">Comprar curso completo</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($puedeCalificar): ?>
<script>
  (function () {
    const contenedor = document.getElementById('calificarEstrellas');
    const estrellas = contenedor.querySelectorAll('.estrella-calificar');
    function pintar(valor) {
      estrellas.forEach(function (e) {
        e.className = 'bi ' + (Number(e.dataset.estrella) <= valor ? 'bi-star-fill' : 'bi-star') + ' estrella-calificar';
      });
      contenedor.dataset.valor = valor;
    }
    estrellas.forEach(function (e) {
      e.addEventListener('click', function () { pintar(Number(e.dataset.estrella)); });
    });
    document.getElementById('btnGuardarCalificacion').addEventListener('click', async function () {
      const puntuacion = Number(contenedor.dataset.valor);
      const msg = document.getElementById('calificarMsg');
      if (!puntuacion) {
        msg.textContent = 'Elige una calificación de 1 a 5 estrellas.';
        msg.className = 'form-text text-danger mt-1';
        return;
      }
      this.disabled = true;
      const res = await fetch('backend/calificacion_guardar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          curso_id: <?= $cursoId ?>,
          puntuacion: puntuacion,
          comentario: document.getElementById('calificarComentario').value,
          csrf_token: <?= json_encode(csrf_token()) ?>,
        }),
      }).catch(function () { return null; });
      this.disabled = false;
      if (!res) {
        msg.textContent = 'No se pudo guardar, intenta de nuevo.';
        msg.className = 'form-text text-danger mt-1';
        return;
      }
      const data = await res.json();
      msg.textContent = data.success ? 'Gracias por tu calificación.' : (data.message || 'No se pudo guardar.');
      msg.className = 'form-text mt-1 ' + (data.success ? 'text-success' : 'text-danger');
    });
  })();
</script>
<?php endif; ?>

<?php if ($regaloConfig && $regaloEstado && $regaloEstado['puede_generar']): ?>
<script>
  document.getElementById('btnGenerarRegalo').addEventListener('click', async function () {
    this.disabled = true;
    const msg = document.getElementById('generarRegaloMsg');
    const res = await fetch('backend/regalo_generar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ curso_id: <?= $cursoId ?>, csrf_token: <?= json_encode(csrf_token()) ?> }),
    }).catch(function () { return null; });
    if (!res) {
      msg.textContent = 'No se pudo generar el enlace, intenta de nuevo.';
      msg.className = 'form-text text-danger mt-1';
      this.disabled = false;
      return;
    }
    const data = await res.json();
    if (data.success) {
      window.location.reload();
    } else {
      msg.textContent = data.message || 'No se pudo generar el enlace.';
      msg.className = 'form-text text-danger mt-1';
      this.disabled = false;
    }
  });
</script>
<?php endif; ?>

<?php if ($usuario && $oferta['estado'] !== 'acceso' && ($oferta['estado'] === 'gratuito' || $oferta['estado'] === 'incluido_membresia' || $oferta['acceso_gratis_automatico'])): ?>
<script>
  document.getElementById('btnInscribirseCurso').addEventListener('click', async function () {
    this.disabled = true;
    const res = await fetch('backend/curso_inscribir.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        curso_id: <?= $cursoId ?>,
        csrf_token: <?= json_encode(csrf_token()) ?>,
        codigo_cupon: <?= json_encode((string) $codigoCupon) ?>,
      }),
    });
    const data = await res.json();
    const msg = document.getElementById('inscribirCursoMsg');
    if (data.success) {
      window.location.href = '?action=curso&slug=<?= urlencode($curso['slug']) ?>&bienvenida=1';
    } else {
      msg.textContent = data.message || 'No se pudo completar la inscripción.';
      msg.className = 'form-text text-danger mt-2';
      this.disabled = false;
    }
  });
  <?php if ($quiereAutoInscribirse): ?>
  document.getElementById('btnInscribirseCurso').click();
  <?php endif; ?>
</script>
<?php endif; ?>
