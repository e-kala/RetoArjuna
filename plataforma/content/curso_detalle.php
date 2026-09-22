<?php
require_once __DIR__ . '/../backend/ofertas.php';
$slug = $_GET['slug'] ?? '';
// Sin filtrar por activo aquí — un admin necesita poder ver un curso oculto
// (ver el guard de abajo, justo después de saber si es admin). Filtrar
// activo=1 directo en el SELECT nunca deja llegar la fila para nadie, ni
// siquiera para decidir si el usuario actual es admin.
$stmt = $conn->prepare('SELECT * FROM cursos WHERE slug = ? LIMIT 1');
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

// Un curso oculto (activo=0) es igual de invisible que "no existe" para
// cualquiera que no sea admin — un admin sí lo ve, con un aviso, para poder
// revisarlo/editarlo sin tener que reactivarlo primero.
if ((int) $curso['activo'] !== 1 && !$esAdminCurso) {
    echo '<div class="container" style="margin-top:143px;"><p>Curso no encontrado.</p></div>';
    return;
}
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
// el auto-checkout nunca se alcanzaba a ejecutar. Tampoco con ?ver=1 —
// enlace directo deliberado para ver el contenido/dispositivo de consumo
// sin pasar por la landing de venta (ej. el cliente revisando cómo se ve
// antes de aprobar la landing, o compartiendo el detalle real en vez del
// discurso de ventas) — ver panel/admin/cursos.php, botón "Enlace directo".
if (!$esAdminCurso && $curso['landing_page_id'] && !in_array($oferta['estado'], ['acceso', 'incluido_membresia'], true)
    && !($usuario && ($_GET['auto'] ?? '') === '1') && ($_GET['ver'] ?? '') !== '1') {
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
// el permiso para este curso (regalo_configuracion). "Regalar descuento" y
// "Regalar acceso" son independientes: $regaloOpciones trae una entrada por
// cada tipo de descuento activo, más "acceso" si aplica, para que el
// estudiante elija cuál usar al generar su enlace. El estado de cada opción
// siempre se lee fresco de `regalos`, nunca se recalcula aparte.
$regaloConfig = $tieneAcceso ? regalo_configuracion_publica($cursoId, null) : null;
$regaloOpciones = $regaloConfig && $usuario ? regalo_opciones_usuario($regaloConfig, $usuario['id']) : [];
$regaloMisEnlaces = $regaloConfig && $usuario ? regalos_generados_por($usuario['id'], $regaloConfig['id']) : [];

// Pestaña "Preguntas y respuestas" (mockup del cliente: en vez de comentarios
// propios, muestra los temas del FORO ya vinculados a este curso, con botón
// "Ver todo en el foro"). Sin lección específica aquí (ver leccion.php para
// la variante acotada a una lección) — ver foro_temas_de() en foro_helpers.php.
require_once __DIR__ . '/../foro/backend/foro_helpers.php';
$temasCurso = foro_temas_de($cursoId, null, null, $usuario);
$stmtTemaExistente = $conn->prepare('SELECT id FROM foro_temas WHERE curso_id = ? AND leccion_id IS NULL LIMIT 1');
$stmtTemaExistente->bind_param('i', $cursoId);
$stmtTemaExistente->execute();
$temaExistenteId = (int) ($stmtTemaExistente->get_result()->fetch_assoc()['id'] ?? 0) ?: null;
$stmtTemaExistente->close();
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px;">
  <a href="?action=cursos" class="d-inline-block mb-3">&larr; Volver al catálogo</a>

  <?php if ((int) $curso['activo'] !== 1): ?>
    <div class="alert alert-warning">Estás viendo este curso como oculto — no aparece en el catálogo ni pueden verlo los alumnos.</div>
  <?php endif; ?>

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
    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto pf-detalle-toggle-sidebar" id="btnToggleTemario" aria-expanded="true" aria-controls="temarioSidebar">
      <i class="bi bi-list-ul"></i> Contenido del curso
    </button>
  </div>
  <?php if ($resumenCalificacion['total'] > 0): ?>
    <p class="text-muted mb-2">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <i class="bi <?= $i <= round($resumenCalificacion['promedio']) ? 'bi-star-fill' : 'bi-star' ?>" style="color:#f7931e;"></i>
      <?php endfor; ?>
      <?= $resumenCalificacion['promedio'] ?> (<?= $resumenCalificacion['total'] ?> calificación<?= $resumenCalificacion['total'] === 1 ? '' : 'es' ?>)
    </p>
  <?php endif; ?>
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
  <?php endif; ?>

  <div class="pf-detalle-grid">
    <div class="pf-detalle-main">
      <ul class="nav nav-tabs pf-detalle-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-descripcion" type="button">Descripción</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-preguntas" type="button">Preguntas y respuestas <?php if ($temasCurso): ?><span class="badge bg-secondary"><?= count($temasCurso) ?></span><?php endif; ?></button></li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-descripcion">
          <div class="pf-contenido-html"><?= (string) $curso['descripcion'] ?></div>
        </div>
        <div class="tab-pane fade" id="tab-preguntas">
          <?php
          $temasPregyresp = $temasCurso;
          $temaExistenteIdPregyresp = $temaExistenteId;
          $verForoUrlPregyresp = 'foro/curso.php?curso_id=' . $cursoId;
          require __DIR__ . '/_preguntas_respuestas.php';
          ?>
        </div>
      </div>

      <?php if ($tieneAcceso): ?>
        <a href="<?= $curso['foro_url'] ? htmlspecialchars(navbar_href($curso['foro_url'], '../')) : 'foro/curso.php?curso_id=' . $cursoId ?>" class="btn btn-outline-secondary btn-sm mb-3 mt-2"><i class="bi bi-chat-square-text"></i> Discutir este curso en el foro</a>
      <?php endif; ?>

  <?php if ($regaloConfig && $regaloOpciones): ?>
    <div class="card p-3 mb-3" style="max-width:480px;background:#fff8ec;border-color:#f7931e;">
      <h2 class="h6 mb-2">🎁 Regalar este curso</h2>
      <?php $hayOpcionDisponible = false; ?>
      <?php foreach ($regaloOpciones as $op): ?>
        <?php $hayOpcionDisponible = $hayOpcionDisponible || $op['estado']['puede_generar']; ?>
        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
          <span class="small"><?= $op['tipo'] === 'acceso' ? 'Acceso completo' : ((float) $op['descuento_pct']) . '% de descuento' ?> — <?= (int) $op['estado']['disponibles'] ?> disponible<?= (int) $op['estado']['disponibles'] === 1 ? '' : 's' ?></span>
          <?php if ($op['estado']['puede_generar']): ?>
            <button type="button" class="btn btn-sm pf-btn-generar-regalo" data-tipo-descuento-id="<?= (int) ($op['tipo_descuento_id'] ?? 0) ?>" style="background:#f7931e;color:#fff;">Generar enlace</button>
          <?php else: ?>
            <span class="text-muted small"><?= htmlspecialchars($op['estado']['motivo_bloqueo'] ?? 'Ya generaste el máximo de enlaces.') ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div id="generarRegaloMsg" class="form-text mt-1"></div>
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
              <span class="small text-muted"><?= $r['tipo_descuento_id'] === null ? 'Acceso completo' : ((float) $r['tipo_descuento_pct']) . '%' ?></span>
              <?php if ($r['estado'] === 'disponible'): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary pf-btn-copiar-regalo" data-enlace="<?= htmlspecialchars(SITE_URL . '/index.php?action=regalo&codigo=' . $r['codigo'], ENT_QUOTES) ?>"><i class="bi bi-clipboard"></i> Copiar enlace</button>
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

      <?php if ($puedeCalificar): ?>
        <div class="card p-3 mb-4 mt-3" style="max-width:480px;">
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
        <div class="card p-3 mt-3" style="max-width:480px;">
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

    <aside class="pf-detalle-sidebar" id="temarioSidebar">
      <h2 class="h6 fw-bold mb-3">Contenido del curso</h2>
      <div class="list-group">
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
        <?php if (!$lecciones): ?>
          <p class="text-muted small px-2 py-3 mb-0">Este curso todavía no tiene lecciones publicadas.</p>
        <?php endif; ?>
      </div>
    </aside>
  </div>
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

<?php if ($regaloConfig && $regaloOpciones): ?>
<script>
  document.querySelectorAll('.pf-btn-generar-regalo').forEach(function (boton) {
    boton.addEventListener('click', async function () {
      this.disabled = true;
      const msg = document.getElementById('generarRegaloMsg');
      const res = await fetch('backend/regalo_generar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          curso_id: <?= $cursoId ?>,
          tipo_descuento_id: this.dataset.tipoDescuentoId,
          csrf_token: <?= json_encode(csrf_token()) ?>,
        }),
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
  });
  document.querySelectorAll('.pf-btn-copiar-regalo').forEach(function (boton) {
    boton.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(this.dataset.enlace);
        $.notify('Enlace copiado al portapapeles.', { className: 'success', position: 'top right', autoHideDelay: 2500 });
      } catch (e) {
        $.notify('No se pudo copiar el enlace.', { className: 'error', position: 'top right', autoHideDelay: 3000 });
      }
    });
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

<style>
  /* Layout tipo Udemy: contenido a la izquierda, temario a la derecha —
     mismo criterio de "panel lateral" ya usado en checkout.php/membresia.php
     (.pf-checkout-side), adaptado aquí a un panel OCULTABLE (no siempre
     visible): el cliente pidió que el temario se pueda plegar/mostrar, no
     que compita de forma fija con el contenido en pantallas medianas. */
  .pf-detalle-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 28px; align-items: start; }
  .pf-detalle-grid.pf-detalle-sin-sidebar { grid-template-columns: minmax(0, 1fr); }
  .pf-detalle-grid.pf-detalle-sin-sidebar > .pf-detalle-sidebar { display: none; }
  .pf-detalle-sidebar {
    background: var(--pf-surface, #fff);
    border: 1px solid var(--pf-line, #e5e0d8);
    border-radius: var(--pf-radius-lg, 12px);
    padding: 18px;
    position: sticky;
    top: 90px;
    max-height: calc(100vh - 110px);
    overflow-y: auto;
  }
  @media (max-width: 900px) {
    .pf-detalle-grid { grid-template-columns: minmax(0, 1fr); }
    .pf-detalle-sidebar { position: static; max-height: none; }
  }
  .pf-detalle-tabs .nav-link { font-weight: 700; color: var(--pf-muted, #6c757d); }
  .pf-detalle-tabs .nav-link.active { color: var(--pf-ink, #212529); border-bottom: 2px solid #f7931e; }
</style>
<script>
  // Botón "Contenido del curso": en desktop solo agrega/quita la clase que
  // colapsa la columna del grid (el temario ya vive en el DOM en todo
  // momento, solo cambia si se muestra); en viewport angosto usa el
  // Offcanvas nativo de Bootstrap (ya cargado por CDN en todo el sitio) —
  // no había ningún componente de panel lateral ocultable ya construido en
  // el proyecto, así que se usa el de Bootstrap en vez de inventar uno.
  (function () {
    const btn = document.getElementById('btnToggleTemario');
    const grid = document.querySelector('.pf-detalle-grid');
    const sidebar = document.getElementById('temarioSidebar');
    if (!btn || !grid || !sidebar) return;

    function esMovil() { return window.matchMedia('(max-width: 900px)').matches; }

    let offcanvasInstancia = null;
    function offcanvas() {
      if (!offcanvasInstancia) {
        sidebar.classList.add('offcanvas', 'offcanvas-end');
        offcanvasInstancia = new bootstrap.Offcanvas(sidebar);
      }
      return offcanvasInstancia;
    }

    btn.addEventListener('click', function () {
      if (esMovil()) {
        offcanvas().toggle();
        return;
      }
      const oculto = grid.classList.toggle('pf-detalle-sin-sidebar');
      btn.setAttribute('aria-expanded', oculto ? 'false' : 'true');
    });
  })();

  // Pestaña "Preguntas y respuestas" — publica sin salir de la página: si ya
  // hay un tema vinculado a este contexto (curso/evento, ver
  // TEMA_EXISTENTE_ID), responde ahí directo (foro/backend/responder.php,
  // sin modificar); si no, crea el tema con ese mismo contenido como post
  // original (foro/backend/crear_tema_leccion.php) — nunca duplica temas.
  (function () {
    const btn = document.getElementById('pfBtnPublicarPregunta');
    if (!btn) return;
    const textarea = document.getElementById('pfNuevaPregunta');
    const msg = document.getElementById('pfPreguntaMsg');
    let temaExistenteId = <?= json_encode($temaExistenteId) ?>;

    btn.addEventListener('click', async function () {
      const contenido = textarea.value.trim();
      if (!contenido) {
        msg.textContent = 'Escribe algo antes de publicar.';
        msg.className = 'form-text text-danger mt-1';
        return;
      }
      btn.disabled = true;
      msg.textContent = '';
      try {
        let res;
        if (temaExistenteId) {
          res = await fetch('foro/backend/responder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ tema_id: temaExistenteId, contenido, csrf_token: <?= json_encode(csrf_token()) ?> }),
          });
        } else {
          res = await fetch('foro/backend/crear_tema_leccion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ tipo: 'curso', item_id: <?= $cursoId ?>, contenido, csrf_token: <?= json_encode(csrf_token()) ?> }),
          });
        }
        const data = await res.json();
        if (!data.success) {
          msg.textContent = data.message || 'No se pudo publicar, intenta de nuevo.';
          msg.className = 'form-text text-danger mt-1';
          btn.disabled = false;
          return;
        }
        if (data.tema_id) temaExistenteId = data.tema_id;
        textarea.value = '';
        $.notify('¡Publicado!', { className: 'success', position: 'top right', autoHideDelay: 2500 });
        window.location.reload();
      } catch (e) {
        msg.textContent = 'Error de conexión. Intenta de nuevo.';
        msg.className = 'form-text text-danger mt-1';
        btn.disabled = false;
      }
    });
  })();
</script>
