<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$temaId = (int) ($_GET['id'] ?? 0);
$usuarioActual = current_user();
// Ver inc/footer.php: el toast "Tema actualizado correctamente" viaja como
// parámetro en la URL cuando guardar-tema necesita recargar la página
// (reasignación de categoría o, si eres admin, de curso/evento/etiquetas).
$toastMensaje = isset($_GET['guardado']) ? 'Tema actualizado correctamente' : null;

$stmt = $conn->prepare(
    "SELECT t.*, u.username_cache, u.avatar_cache,
            cu.titulo AS curso_titulo, ev.titulo AS evento_titulo, l.titulo AS leccion_titulo,
            cl.nombre AS categoria_libre_nombre, cl.slug AS categoria_libre_slug
     FROM foro_temas t
     JOIN usuarios_perfil u ON u.id = t.usuario_id
     LEFT JOIN cursos cu ON cu.id = t.curso_id
     LEFT JOIN eventos ev ON ev.id = t.evento_id
     LEFT JOIN lecciones l ON l.id = t.leccion_id
     LEFT JOIN foro_categorias_libres cl ON cl.id = t.categoria_libre_id
     WHERE t.id = ? LIMIT 1"
);
$stmt->bind_param('i', $temaId);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tema || !foro_tema_es_visible($tema, $usuarioActual)) {
    header('Location: index.php');
    exit;
}

// Cuenta la vista una sola vez por sesión de navegador para no inflar el contador.
if (empty($_SESSION['foro_vistas'][$temaId])) {
    $_SESSION['foro_vistas'][$temaId] = true;
    $conn->query('UPDATE foro_temas SET vistas = vistas + 1 WHERE id = ' . $temaId);
    $tema['vistas']++;
}

$stmt = $conn->prepare(
    "SELECT r.*, u.username_cache, u.avatar_cache
     FROM foro_respuestas r
     JOIN usuarios_perfil u ON u.id = r.usuario_id
     WHERE r.tema_id = ? AND r.eliminado_en IS NULL ORDER BY r.created_at ASC"
);
$stmt->bind_param('i', $temaId);
$stmt->execute();
$respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$esAdmin = $usuarioActual && $usuarioActual['rol'] === 'admin';
$page_title = $tema['titulo'];
$usaEditorEnriquecido = (bool) $usuarioActual;
// Siempre se traen TODAS las etiquetas del tema (aprobadas o no) en su propia
// página — una etiqueta pendiente se marca con una insignia en vez de
// ocultarse; el filtro aprobada=1 solo aplica a listados agregados
// (sidebar, checkboxes de otros usuarios, etiqueta.php).
$etiquetasTema = foro_tema_etiquetas((int) $tema['id'], false);
$categoriasLibresDisponibles = foro_categorias_libres_todas();

require __DIR__ . '/inc/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door"></i> Foro</a></li>
    <?php if ($tema['curso_titulo']): ?>
      <li class="breadcrumb-item"><a href="curso.php?curso_id=<?= (int) $tema['curso_id'] ?>"><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($tema['curso_titulo']) ?></a></li>
      <?php if ($tema['leccion_titulo']): ?>
        <li class="breadcrumb-item"><a href="curso.php?curso_id=<?= (int) $tema['curso_id'] ?>&leccion_id=<?= (int) $tema['leccion_id'] ?>"><?= htmlspecialchars($tema['leccion_titulo']) ?></a></li>
      <?php endif; ?>
    <?php elseif ($tema['evento_titulo']): ?>
      <li class="breadcrumb-item"><a href="evento.php?evento_id=<?= (int) $tema['evento_id'] ?>"><i class="bi bi-calendar-event-fill"></i> <?= htmlspecialchars($tema['evento_titulo']) ?></a></li>
      <?php if ($tema['leccion_titulo']): ?>
        <li class="breadcrumb-item"><a href="evento.php?evento_id=<?= (int) $tema['evento_id'] ?>&leccion_id=<?= (int) $tema['leccion_id'] ?>"><?= htmlspecialchars($tema['leccion_titulo']) ?></a></li>
      <?php endif; ?>
    <?php endif; ?>
  </ol>
</nav>

<h1 class="h3 fw-bold mb-4">
  <span class="badge rounded-pill me-1 <?= $tema['fijado'] ? '' : 'd-none' ?>" id="badge-fijado" style="background:#fff3e0;color:var(--pf-accent-ink);"><i class="bi bi-pin-angle-fill"></i> Fijado</span>
  <span class="badge rounded-pill text-bg-secondary me-1 <?= $tema['cerrado'] ? '' : 'd-none' ?>" id="badge-cerrado"><i class="bi bi-lock-fill"></i> Cerrado</span>
  <?php if ($esAdmin): ?>
    <span class="badge rounded-pill text-bg-dark me-1 <?= $tema['oculto'] ? '' : 'd-none' ?>" id="badge-oculto"><i class="bi bi-eye-slash-fill"></i> Oculto — solo tú lo ves</span>
  <?php endif; ?>
  <span id="tema-titulo-texto"><?= htmlspecialchars($tema['titulo']) ?></span>
</h1>

<?php if ($tema['categoria_libre_nombre'] || $etiquetasTema): ?>
  <div class="mb-3">
    <?php if ($tema['categoria_libre_nombre']): ?>
      <a href="categoria.php?slug=<?= urlencode($tema['categoria_libre_slug']) ?>" class="badge rounded-pill text-decoration-none me-1" style="background:#eaf7ea;color:#1c7a3a;">
        <i class="bi bi-bookmark-fill"></i> <?= htmlspecialchars($tema['categoria_libre_nombre']) ?>
      </a>
    <?php endif; ?>
    <?php foreach ($etiquetasTema as $et): ?>
      <a href="etiqueta.php?slug=<?= urlencode($et['slug']) ?>" class="badge rounded-pill text-decoration-none me-1" style="background:#e6f0fb;color:#1c5fa8;">
        <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($et['nombre']) ?>
      </a>
      <?php if ((int) $et['aprobada'] !== 1): ?>
        <span class="badge rounded-pill text-bg-light text-muted border me-1">pendiente de aprobación</span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card shadow-sm mb-3" id="post-tema">
  <div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-3 text-muted small">
      <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $tema['username_cache'], 0, 1))) ?></span>
      <div>
        <strong class="text-dark"><?= perfil_link((int) $tema['usuario_id'], (string) $tema['username_cache']) ?></strong> · <?= foro_tiempo_relativo($tema['created_at']) ?>
        <button type="button" class="btn btn-link btn-sm p-0 text-muted text-decoration-underline align-baseline <?= $tema['editado_en'] ? '' : 'd-none' ?>" id="btn-historial-tema" data-historial-tipo="tema" data-historial-id="<?= (int) $tema['id'] ?>">(editado)</button>
      </div>
    </div>
    <div class="pf-forum-post-body" id="cuerpo-tema"><?= $tema['contenido'] ?></div>
    <?php $puedeEditarTema = $usuarioActual && ((int) $usuarioActual['id'] === (int) $tema['usuario_id'] || $esAdmin); ?>
    <?php if ($puedeEditarTema): ?>
      <div class="d-flex flex-column gap-2 mt-3 d-none" id="editar-tema">
        <input type="text" class="form-control fw-bold" id="editar-tema-titulo" value="<?= htmlspecialchars($tema['titulo']) ?>" maxlength="200">
        <div class="pf-forum-editor" id="editorEditarTema"></div>
        <div class="d-none" id="fuente-editar-tema"><?= $tema['contenido'] ?></div>
        <div>
          <label class="form-label small">Categoría <span class="text-danger">*</span></label>
          <select class="form-select form-select-sm" id="editar-tema-categoria-libre">
            <option value="">— Elige una —</option>
            <?php foreach ($categoriasLibresDisponibles as $cl): ?>
              <option value="<?= (int) $cl['id'] ?>" <?= (int) $tema['categoria_libre_id'] === (int) $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" class="form-control form-control-sm mt-1" id="editar-tema-categoria-libre-nueva" placeholder="¿No está la que buscas? Escribe una nueva">
        </div>
        <?php if ($esAdmin): ?>
          <?php
            $etiquetasDisponiblesEdicion = foro_categorias_arbol_plano(foro_categorias_arbol($usuarioActual, false));
            $etiquetasActualesIds = array_column($etiquetasTema, 'id');
          ?>
          <div class="border rounded p-3">
            <p class="small fw-bold mb-2">Reasignar (solo admin)</p>
            <div class="row g-2 mb-2">
              <div class="col-md-4">
                <label class="form-label small">Curso</label>
                <select class="form-select form-select-sm" id="editar-tema-curso">
                  <option value="">— Ninguno —</option>
                  <?php foreach (foro_lista_cursos_activos() as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $tema['curso_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['titulo']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small">Evento</label>
                <select class="form-select form-select-sm" id="editar-tema-evento">
                  <option value="">— Ninguno —</option>
                  <?php foreach (foro_lista_eventos_activos() as $e): ?>
                    <option value="<?= (int) $e['id'] ?>" <?= (int) $tema['evento_id'] === (int) $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['titulo']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small">Lección</label>
                <select class="form-select form-select-sm" id="editar-tema-leccion">
                  <option value="">— Ninguna —</option>
                  <?php foreach (foro_lecciones_de(( int) $tema['curso_id'], (int) $tema['evento_id']) as $l): ?>
                    <option value="<?= (int) $l['id'] ?>" <?= (int) $tema['leccion_id'] === (int) $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['titulo']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <label class="form-label small">Etiquetas</label>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($etiquetasDisponiblesEdicion as $et): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="editar_etiquetas[]" value="<?= (int) $et['id'] ?>" id="editar-etiqueta-<?= (int) $et['id'] ?>" <?= in_array((int) $et['id'], $etiquetasActualesIds, true) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="editar-etiqueta-<?= (int) $et['id'] ?>"><?= htmlspecialchars($et['nombre']) ?><?= (int) $et['aprobada'] !== 1 ? ' (pendiente)' : '' ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm fw-bold" style="background:var(--pf-accent);color:#fff;" id="guardar-tema">Guardar</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelar-tema">Cancelar</button>
        </div>
      </div>
    <?php endif; ?>
    <div class="d-flex align-items-center gap-2 mt-3 pt-3 border-top flex-wrap">
      <?php
        $likesTema = foro_contar_likes($tema['id'], null);
        $meGustaTema = $usuarioActual && foro_usuario_dio_like($usuarioActual['id'], $tema['id'], null);
      ?>
      <button class="btn btn-sm <?= $meGustaTema ? '' : 'btn-outline-secondary' ?> pf-forum-like-btn <?= $meGustaTema ? 'activo' : '' ?>" style="<?= $meGustaTema ? 'background:var(--pf-accent);color:#fff;border-color:var(--pf-accent);' : '' ?>" data-tema-id="<?= (int) $tema['id'] ?>" <?= $usuarioActual ? '' : 'disabled' ?>>
        <i class="bi bi-hand-thumbs-up<?= $meGustaTema ? '-fill' : '' ?>"></i> <span class="likes-count"><?= $likesTema ?></span>
      </button>
      <?php if ($likesTema > 0): ?>
        <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-likes-tipo="tema" data-likes-id="<?= (int) $tema['id'] ?>">ver quién dio like</button>
      <?php endif; ?>
      <?php if ($puedeEditarTema): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-editar-tema"><i class="bi bi-pencil"></i> Editar</button>
      <?php endif; ?>
      <?php if ($esAdmin): ?>
        <div class="btn-group btn-group-sm ms-auto pf-forum-mod-actions">
          <button id="btn-fijar" class="btn btn-outline-secondary" data-accion="<?= $tema['fijado'] ? 'desfijar' : 'fijar' ?>"><i class="bi bi-pin-angle"></i> <?= $tema['fijado'] ? 'Quitar fijado' : 'Fijar' ?></button>
          <button id="btn-cerrar" class="btn btn-outline-secondary" data-accion="<?= $tema['cerrado'] ? 'reabrir' : 'cerrar' ?>" title="Impide que otros usuarios respondan a este tema (los admins sí pueden). No lo oculta."><i class="bi bi-lock"></i> <?= $tema['cerrado'] ? 'Reabrir' : 'Cerrar' ?></button>
          <button id="btn-ocultar" class="btn btn-outline-secondary" data-accion="<?= $tema['oculto'] ? 'mostrar' : 'ocultar' ?>" title="Lo quita del foro por completo — nadie más lo ve, ni siquiera su autor."><i class="bi bi-eye-slash"></i> <?= $tema['oculto'] ? 'Mostrar' : 'Ocultar' ?></button>
          <button class="btn btn-outline-danger" data-accion="eliminar"><i class="bi bi-trash"></i> Eliminar</button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php foreach ($respuestas as $r): ?>
  <?php $puedeEditarResp = $usuarioActual && ((int) $usuarioActual['id'] === (int) $r['usuario_id'] || $esAdmin); ?>
  <div class="card shadow-sm mb-3" id="respuesta-<?= (int) $r['id'] ?>">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3 text-muted small">
        <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $r['username_cache'], 0, 1))) ?></span>
        <div>
          <strong class="text-dark"><?= perfil_link((int) $r['usuario_id'], (string) $r['username_cache']) ?></strong> · <?= foro_tiempo_relativo($r['created_at']) ?>
          <button type="button" class="btn btn-link btn-sm p-0 text-muted text-decoration-underline align-baseline <?= $r['editado_en'] ? '' : 'd-none' ?>" id="btn-historial-respuesta-<?= (int) $r['id'] ?>" data-historial-tipo="respuesta" data-historial-id="<?= (int) $r['id'] ?>">(editado)</button>
        </div>
      </div>
      <div class="pf-forum-post-body" id="cuerpo-respuesta-<?= (int) $r['id'] ?>"><?= $r['contenido'] ?></div>
      <?php if ($puedeEditarResp): ?>
        <div class="d-flex flex-column gap-2 mt-3 d-none" id="editar-respuesta-<?= (int) $r['id'] ?>">
          <div class="pf-forum-editor" id="editorEditarRespuesta-<?= (int) $r['id'] ?>"></div>
          <div class="d-none" id="fuente-editar-respuesta-<?= (int) $r['id'] ?>"><?= $r['contenido'] ?></div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm fw-bold btn-guardar-respuesta" style="background:var(--pf-accent);color:#fff;" data-respuesta-id="<?= (int) $r['id'] ?>">Guardar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-cancelar-respuesta" data-respuesta-id="<?= (int) $r['id'] ?>">Cancelar</button>
          </div>
        </div>
      <?php endif; ?>
      <div class="d-flex align-items-center gap-2 mt-3 pt-3 border-top flex-wrap">
        <?php
          $likesResp = foro_contar_likes(null, $r['id']);
          $meGustaResp = $usuarioActual && foro_usuario_dio_like($usuarioActual['id'], null, $r['id']);
        ?>
        <button class="btn btn-sm <?= $meGustaResp ? '' : 'btn-outline-secondary' ?> pf-forum-like-btn <?= $meGustaResp ? 'activo' : '' ?>" style="<?= $meGustaResp ? 'background:var(--pf-accent);color:#fff;border-color:var(--pf-accent);' : '' ?>" data-respuesta-id="<?= (int) $r['id'] ?>" <?= $usuarioActual ? '' : 'disabled' ?>>
          <i class="bi bi-hand-thumbs-up<?= $meGustaResp ? '-fill' : '' ?>"></i> <span class="likes-count"><?= $likesResp ?></span>
        </button>
        <?php if ($likesResp > 0): ?>
          <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-likes-tipo="respuesta" data-likes-id="<?= (int) $r['id'] ?>">ver quién dio like</button>
        <?php endif; ?>
        <?php if ($puedeEditarResp): ?>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-respuesta" data-respuesta-id="<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i> Editar</button>
          <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-respuesta" data-respuesta-id="<?= (int) $r['id'] ?>"><i class="bi bi-trash"></i> Eliminar</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<div class="modal fade" id="historialModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title h5"><i class="bi bi-clock-history"></i> Historial de ediciones</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="historialContenido"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="likesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title h5"><i class="bi bi-hand-thumbs-up-fill"></i> A quién le gustó</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="likesContenido"></div>
    </div>
  </div>
</div>

<?php if ($tema['cerrado'] && !$esAdmin): ?>
  <div class="text-center text-muted py-4"><i class="bi bi-lock-fill"></i> Este tema está cerrado y ya no acepta respuestas.</div>
<?php elseif ($usuarioActual): ?>
  <div class="card shadow-sm mt-4">
    <div class="card-body">
      <div id="responderError" class="alert alert-danger d-none"></div>
      <form id="responderForm">
        <div class="mb-3">
          <label for="editorRespuesta" class="form-label fw-bold"><i class="bi bi-reply-fill"></i> Responder</label>
          <div class="pf-forum-editor" id="editorRespuesta"></div>
        </div>
        <button type="submit" class="btn fw-bold" style="background:var(--pf-accent);color:#fff;">Publicar respuesta</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="text-center text-muted py-4"><a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=ingreso">Inicia sesión</a> para responder.</div>
<?php endif; ?>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const TEMA_ID = <?= (int) $tema['id'] ?>;
const PF_QUILL_TOOLBAR = [['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'code-block'], ['link'], ['clean']];

document.querySelectorAll('.pf-forum-like-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (btn.disabled) return;
    const params = { csrf_token: CSRF_TOKEN };
    if (btn.dataset.temaId) params.tema_id = btn.dataset.temaId;
    if (btn.dataset.respuestaId) params.respuesta_id = btn.dataset.respuestaId;

    fetch('backend/like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(params)
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          btn.querySelector('.likes-count').textContent = data.likes;
          btn.classList.toggle('activo', data.activo);
        }
      })
      .catch(() => {});
  });
});

<?php if ($usuarioActual && (!$tema['cerrado'] || $esAdmin)): ?>
const quillRespuesta = new Quill('#editorRespuesta', {
  theme: 'snow',
  placeholder: 'Escribe tu respuesta. Usa @usuario para mencionar a alguien.',
  modules: { toolbar: PF_QUILL_TOOLBAR }
});

document.getElementById('responderForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const $error = document.getElementById('responderError');
  $error.style.display = 'none';

  fetch('backend/responder.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      tema_id: TEMA_ID,
      contenido: quillRespuesta.root.innerHTML,
      csrf_token: CSRF_TOKEN
    })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // El nuevo mensaje y sus botones (editar/eliminar/like) se arman con
        // el mismo layout que ya construye PHP — se recarga a la página del
        // tema (no queda otra sin duplicar esa plantilla en JS), pero SÍ se
        // respeta el ancla que ya manda el backend para que la respuesta
        // recién publicada quede a la vista de inmediato, en vez de siempre
        // aterrizar arriba del todo.
        window.location.href = data.redirect || window.location.href;
      } else {
        $error.textContent = data.message || 'No se pudo publicar la respuesta.';
        $error.style.display = 'block';
      }
    })
    .catch(() => {
      $error.textContent = 'Error de conexión. Intenta de nuevo.';
      $error.style.display = 'block';
    });
});
<?php endif; ?>

// Antes usaba .style.display, pero el contenedor de edición también trae la
// clase "d-flex" de Bootstrap — esa clase se define con !important en el
// CSS de Bootstrap, y una regla !important de hoja de estilos SIEMPRE le
// gana a un estilo inline que no sea también !important. El resultado: el
// display:none inline nunca hacía nada, la caja de edición se veía abierta
// desde que cargaba la página, sin haber dado clic en "Editar" — por eso
// Quill nunca llegaba a inicializarse y "Guardar" tronaba. La clase d-none
// (también !important, pero pensada por Bootstrap para combinarse con otras
// utilidades de display) sí gana de forma consistente.
function pfActivarEdicion(prefijo) {
  document.getElementById('cuerpo-' + prefijo).classList.add('d-none');
  document.getElementById('editar-' + prefijo).classList.remove('d-none');
}
function pfCancelarEdicion(prefijo) {
  document.getElementById('cuerpo-' + prefijo).classList.remove('d-none');
  document.getElementById('editar-' + prefijo).classList.add('d-none');
}

<?php if ($puedeEditarTema): ?>
// Quill no puede inicializarse correctamente dentro de un contenedor oculto
// (display:none) — se crea la primera vez que se abre la edición, no antes.
let quillEditarTema = null;
document.getElementById('btn-editar-tema').addEventListener('click', function () {
  pfActivarEdicion('tema');
  if (!quillEditarTema) {
    try {
      quillEditarTema = new Quill('#editorEditarTema', { theme: 'snow', modules: { toolbar: PF_QUILL_TOOLBAR } });
      quillEditarTema.root.innerHTML = document.getElementById('fuente-editar-tema').innerHTML;
    } catch (e) {
      // Antes esto fallaba en silencio: quillEditarTema se quedaba null y el
      // error real de Quill nunca llegaba a verse — el usuario solo notaba
      // que "Guardar" tronaba después, sin pista de por qué. Ahora se avisa
      // aquí mismo, en el momento en que realmente ocurre.
      console.error('No se pudo inicializar el editor de texto:', e);
      alert('No se pudo cargar el editor de texto. Recarga la página e intenta de nuevo.');
    }
  }
});
document.getElementById('cancelar-tema').addEventListener('click', function () { pfCancelarEdicion('tema'); });
const categoriaLibreOriginal = document.getElementById('editar-tema-categoria-libre').value;
document.getElementById('guardar-tema').addEventListener('click', function () {
  if (!quillEditarTema) {
    alert('El editor de texto no cargó correctamente. Da clic en "Cancelar" e "Editar" de nuevo antes de guardar.');
    return;
  }
  const titulo = document.getElementById('editar-tema-titulo').value;
  const contenido = quillEditarTema.root.innerHTML;
  const categoriaLibreId = document.getElementById('editar-tema-categoria-libre').value;
  const categoriaLibreNueva = document.getElementById('editar-tema-categoria-libre-nueva').value;
  if (!categoriaLibreId && !categoriaLibreNueva.trim()) {
    alert('Elige una categoría de la lista o escribe una nueva.');
    return;
  }
  const datos = new URLSearchParams({ tema_id: TEMA_ID, titulo: titulo, contenido: contenido, csrf_token: CSRF_TOKEN });
  datos.set('categoria_libre_id', categoriaLibreId);
  datos.set('categoria_libre_nueva', categoriaLibreNueva);
  const cambioCategoria = categoriaLibreNueva.trim() !== '' || categoriaLibreId !== categoriaLibreOriginal;
  const cursoEl = document.getElementById('editar-tema-curso');
  if (cursoEl) {
    datos.set('curso_id', cursoEl.value);
    datos.set('evento_id', document.getElementById('editar-tema-evento').value);
    datos.set('leccion_id', document.getElementById('editar-tema-leccion').value);
    document.querySelectorAll('input[name="editar_etiquetas[]"]:checked').forEach(el => datos.append('etiquetas[]', el.value));
  }
  fetch('backend/editar_tema.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: datos
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.success) {
        alert(data.message || 'No se pudo guardar la edición.');
        return;
      }
      // La reasignación de curso/evento/etiquetas (solo admin) y de
      // categoría cambian cosas fuera de este bloque (breadcrumb, insignias,
      // barra lateral) — más simple y confiable recargar en ese caso que
      // replicar toda esa lógica en JS; el resto de una edición normal
      // (título/contenido) sí se actualiza al instante, sin recargar.
      if (cursoEl || cambioCategoria) {
        window.location.href = 'tema.php?id=' + TEMA_ID + '&guardado=1';
        return;
      }
      document.getElementById('tema-titulo-texto').textContent = data.titulo;
      document.getElementById('cuerpo-tema').innerHTML = data.contenido_html;
      document.getElementById('editar-tema-titulo').value = data.titulo;
      document.getElementById('btn-historial-tema').classList.remove('d-none');
      pfCancelarEdicion('tema');
      pfMostrarToast('Tema actualizado correctamente');
    })
    .catch(function () {
      alert('Error de conexión o sesión expirada. Recarga la página e intenta de nuevo.');
    });
});
<?php endif; ?>

const quillsRespuestasEditar = {};
document.querySelectorAll('.btn-editar-respuesta').forEach(function (btn) {
  const id = btn.dataset.respuestaId;
  btn.addEventListener('click', function () {
    pfActivarEdicion('respuesta-' + id);
    if (!quillsRespuestasEditar[id]) {
      try {
        quillsRespuestasEditar[id] = new Quill('#editorEditarRespuesta-' + id, { theme: 'snow', modules: { toolbar: PF_QUILL_TOOLBAR } });
        quillsRespuestasEditar[id].root.innerHTML = document.getElementById('fuente-editar-respuesta-' + id).innerHTML;
      } catch (e) {
        console.error('No se pudo inicializar el editor de texto:', e);
        alert('No se pudo cargar el editor de texto. Recarga la página e intenta de nuevo.');
      }
    }
  });
});
document.querySelectorAll('.btn-cancelar-respuesta').forEach(function (btn) {
  btn.addEventListener('click', function () { pfCancelarEdicion('respuesta-' + btn.dataset.respuestaId); });
});
document.querySelectorAll('.btn-guardar-respuesta').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const id = btn.dataset.respuestaId;
    if (!quillsRespuestasEditar[id]) {
      alert('El editor de texto no cargó correctamente. Da clic en "Cancelar" e "Editar" de nuevo antes de guardar.');
      return;
    }
    const contenido = quillsRespuestasEditar[id].root.innerHTML;
    fetch('backend/editar_respuesta.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ respuesta_id: id, contenido: contenido, csrf_token: CSRF_TOKEN })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          document.getElementById('cuerpo-respuesta-' + id).innerHTML = data.contenido_html;
          const btnHist = document.getElementById('btn-historial-respuesta-' + id);
          if (btnHist) btnHist.classList.remove('d-none');
          pfCancelarEdicion('respuesta-' + id);
          pfMostrarToast('Respuesta actualizada correctamente');
        } else {
          alert(data.message || 'No se pudo guardar la edición.');
        }
      })
      .catch(function () {
        alert('Error de conexión o sesión expirada. Recarga la página e intenta de nuevo.');
      });
  });
});

// bootstrap.bundle.min.js se carga en inc/footer.php, DESPUÉS de este bloque
// de script — instanciar el Modal aquí arriba (a nivel de script, no dentro
// de un click) lanzaría "bootstrap is not defined" y abortaría TODO lo que
// viene después en este mismo <script> (eliminar/cerrar/fijar tema, ver
// quién dio like, eliminar respuesta — ninguno llegaba a registrar su
// listener). Se crea de forma perezosa, mismo patrón que ya usan
// quillEditarTema/quillsRespuestasEditar más arriba.
let historialModal = null;
document.querySelectorAll('[data-historial-tipo]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const tipo = btn.dataset.historialTipo;
    const id = btn.dataset.historialId;
    const contenedor = document.getElementById('historialContenido');
    contenedor.innerHTML = '<p class="text-muted text-center py-4">Cargando…</p>';
    if (!historialModal) historialModal = new bootstrap.Modal(document.getElementById('historialModal'));
    historialModal.show();
    fetch('backend/historial.php?tipo=' + tipo + '&id=' + id)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        contenedor.innerHTML = '';
        if (!data.success || !data.historial.length) {
          contenedor.innerHTML = '<p class="text-muted text-center py-4">No hay versiones anteriores.</p>';
          return;
        }
        data.historial.forEach(function (v, i) {
          const bloque = document.createElement('div');
          bloque.className = 'pb-3 mb-3' + (i < data.historial.length - 1 ? ' border-bottom' : '');

          const meta = document.createElement('div');
          meta.className = 'text-muted small mb-2';
          meta.textContent = 'Editado por ' + v.username_cache + ' — ' + v.editado_en;
          bloque.appendChild(meta);

          if (v.titulo_anterior) {
            const tit = document.createElement('div');
            tit.className = 'fw-bold mb-2';
            tit.textContent = v.titulo_anterior;
            bloque.appendChild(tit);
          }

          const cont = document.createElement('div');
          cont.className = 'pf-forum-post-body';
          cont.innerHTML = v.contenido_anterior;
          bloque.appendChild(cont);

          contenedor.appendChild(bloque);
        });
      })
      .catch(function () {
        contenedor.innerHTML = '<p class="text-danger text-center py-4">No se pudo cargar el historial. Intenta de nuevo.</p>';
      });
  });
});

let likesModal = null;
document.querySelectorAll('[data-likes-tipo]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const tipo = btn.dataset.likesTipo;
    const id = btn.dataset.likesId;
    const contenedor = document.getElementById('likesContenido');
    contenedor.innerHTML = '<p class="text-muted text-center py-4">Cargando…</p>';
    if (!likesModal) likesModal = new bootstrap.Modal(document.getElementById('likesModal'));
    likesModal.show();
    fetch('backend/likes_lista.php?tipo=' + tipo + '&id=' + id)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        contenedor.innerHTML = '';
        if (!data.success || !data.likes.length) {
          contenedor.innerHTML = '<p class="text-muted text-center py-4">Nadie le ha dado like todavía.</p>';
          return;
        }
        const lista = document.createElement('ul');
        lista.className = 'list-unstyled mb-0';
        data.likes.forEach(function (l) {
          const item = document.createElement('li');
          item.className = 'py-1';
          item.textContent = l.username_cache;
          lista.appendChild(item);
        });
        contenedor.appendChild(lista);
      })
      .catch(function () {
        contenedor.innerHTML = '<p class="text-danger text-center py-4">No se pudo cargar la lista. Intenta de nuevo.</p>';
      });
  });
});

document.querySelectorAll('.btn-eliminar-respuesta').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (!confirm('¿Enviar esta respuesta a la papelera? Un admin la revisará; si nadie hace nada en 15 días se borra sola.')) return;
    const id = btn.dataset.respuestaId;
    fetch('backend/moderar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ respuesta_id: id, accion: 'eliminar_respuesta', csrf_token: CSRF_TOKEN })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          document.getElementById('respuesta-' + id).remove();
        } else {
          alert(data.message || 'No se pudo enviar a la papelera.');
        }
      })
      .catch(function () {
        alert('Error de conexión. Intenta de nuevo.');
      });
  });
});

<?php if ($esAdmin): ?>
document.querySelectorAll('.pf-forum-mod-actions button').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const accion = btn.dataset.accion;
    if (accion === 'eliminar' && !confirm('¿Eliminar este tema y todas sus respuestas? No se puede deshacer.')) return;

    fetch('backend/moderar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ tema_id: TEMA_ID, accion: accion, csrf_token: CSRF_TOKEN })
    })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          alert(data.message || 'No se pudo completar la acción.');
          return;
        }
        // Eliminar sí necesita salir del tema (ya no existe); fijar/cerrar se
        // reflejan en la misma página sin recargar — el estado nuevo se
        // deduce de qué acción se acaba de ejecutar, sin pedirle al backend
        // que lo repita.
        if (accion === 'eliminar') {
          window.location.href = data.redirect || 'index.php';
          return;
        }
        if (accion === 'fijar' || accion === 'desfijar') {
          const fijado = accion === 'fijar';
          document.getElementById('badge-fijado').classList.toggle('d-none', !fijado);
          const btnFijar = document.getElementById('btn-fijar');
          btnFijar.dataset.accion = fijado ? 'desfijar' : 'fijar';
          btnFijar.innerHTML = '<i class="bi bi-pin-angle"></i> ' + (fijado ? 'Quitar fijado' : 'Fijar');
        } else if (accion === 'cerrar' || accion === 'reabrir') {
          const cerrado = accion === 'cerrar';
          document.getElementById('badge-cerrado').classList.toggle('d-none', !cerrado);
          const btnCerrar = document.getElementById('btn-cerrar');
          btnCerrar.dataset.accion = cerrado ? 'reabrir' : 'cerrar';
          btnCerrar.innerHTML = '<i class="bi bi-lock"></i> ' + (cerrado ? 'Reabrir' : 'Cerrar');
        } else if (accion === 'ocultar' || accion === 'mostrar') {
          const oculto = accion === 'ocultar';
          document.getElementById('badge-oculto').classList.toggle('d-none', !oculto);
          const btnOcultar = document.getElementById('btn-ocultar');
          btnOcultar.dataset.accion = oculto ? 'mostrar' : 'ocultar';
          btnOcultar.innerHTML = '<i class="bi bi-eye-slash"></i> ' + (oculto ? 'Mostrar' : 'Ocultar');
          pfMostrarToast(oculto ? 'Tema oculto — ya no aparece en el foro' : 'Tema visible de nuevo en el foro');
        }
      })
      .catch(() => {
        alert('Error de conexión. Intenta de nuevo.');
      });
  });
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
