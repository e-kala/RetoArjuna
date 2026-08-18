<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$temaId = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT t.*, u.username_cache, u.avatar_cache, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
            cu.titulo AS curso_titulo, l.titulo AS leccion_titulo
     FROM foro_temas t
     JOIN usuarios_perfil u ON u.id = t.usuario_id
     JOIN foro_categorias c ON c.id = t.categoria_id
     LEFT JOIN cursos cu ON cu.id = t.curso_id
     LEFT JOIN lecciones l ON l.id = t.leccion_id
     WHERE t.id = ? LIMIT 1"
);
$stmt->bind_param('i', $temaId);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tema) {
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
     WHERE r.tema_id = ? ORDER BY r.created_at ASC"
);
$stmt->bind_param('i', $temaId);
$stmt->execute();
$respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$usuarioActual = current_user();
$esAdmin = $usuarioActual && $usuarioActual['rol'] === 'admin';
$page_title = $tema['titulo'];
$usaEditorEnriquecido = (bool) $usuarioActual;
$rutaCategoria = foro_categoria_ruta((int) $tema['categoria_id']);

require __DIR__ . '/inc/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door"></i> Foro</a></li>
    <?php foreach ($rutaCategoria as $nodo): ?>
      <li class="breadcrumb-item"><a href="categoria.php?slug=<?= urlencode($nodo['slug']) ?>"><?= htmlspecialchars($nodo['nombre']) ?></a></li>
    <?php endforeach; ?>
    <?php if ($tema['curso_titulo']): ?>
      <li class="breadcrumb-item"><a href="curso.php?curso_id=<?= (int) $tema['curso_id'] ?>"><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($tema['curso_titulo']) ?></a></li>
      <?php if ($tema['leccion_titulo']): ?>
        <li class="breadcrumb-item"><a href="curso.php?curso_id=<?= (int) $tema['curso_id'] ?>&leccion_id=<?= (int) $tema['leccion_id'] ?>"><?= htmlspecialchars($tema['leccion_titulo']) ?></a></li>
      <?php endif; ?>
    <?php endif; ?>
  </ol>
</nav>

<h1 class="h3 fw-bold mb-4">
  <?php if ($tema['fijado']): ?><span class="badge rounded-pill me-1" style="background:#fff3e0;color:var(--pf-accent-ink);"><i class="bi bi-pin-angle-fill"></i> Fijado</span><?php endif; ?>
  <?php if ($tema['cerrado']): ?><span class="badge rounded-pill text-bg-secondary me-1"><i class="bi bi-lock-fill"></i> Cerrado</span><?php endif; ?>
  <span id="tema-titulo-texto"><?= htmlspecialchars($tema['titulo']) ?></span>
</h1>

<div class="card shadow-sm mb-3" id="post-tema">
  <div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-3 text-muted small">
      <span class="pf-user-avatar"><?= htmlspecialchars(strtoupper(substr((string) $tema['username_cache'], 0, 1))) ?></span>
      <div>
        <strong class="text-dark"><?= htmlspecialchars($tema['username_cache']) ?></strong> · <?= foro_tiempo_relativo($tema['created_at']) ?>
        <?php if ($tema['editado_en']): ?>
          <button type="button" class="btn btn-link btn-sm p-0 text-muted text-decoration-underline align-baseline" data-historial-tipo="tema" data-historial-id="<?= (int) $tema['id'] ?>">(editado)</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="pf-forum-post-body" id="cuerpo-tema"><?= $tema['contenido'] ?></div>
    <?php $puedeEditarTema = $usuarioActual && ((int) $usuarioActual['id'] === (int) $tema['usuario_id'] || $esAdmin); ?>
    <?php if ($puedeEditarTema): ?>
      <div class="d-flex flex-column gap-2 mt-3" id="editar-tema" style="display:none;">
        <input type="text" class="form-control fw-bold" id="editar-tema-titulo" value="<?= htmlspecialchars($tema['titulo']) ?>" maxlength="200">
        <div class="pf-forum-editor" id="editorEditarTema"></div>
        <div class="d-none" id="fuente-editar-tema"><?= $tema['contenido'] ?></div>
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
      <?php if ($puedeEditarTema): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-editar-tema"><i class="bi bi-pencil"></i> Editar</button>
      <?php endif; ?>
      <?php if ($esAdmin): ?>
        <div class="btn-group btn-group-sm ms-auto pf-forum-mod-actions">
          <button class="btn btn-outline-secondary" data-accion="<?= $tema['fijado'] ? 'desfijar' : 'fijar' ?>"><i class="bi bi-pin-angle"></i> <?= $tema['fijado'] ? 'Quitar fijado' : 'Fijar' ?></button>
          <button class="btn btn-outline-secondary" data-accion="<?= $tema['cerrado'] ? 'reabrir' : 'cerrar' ?>"><i class="bi bi-lock"></i> <?= $tema['cerrado'] ? 'Reabrir' : 'Cerrar' ?></button>
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
          <strong class="text-dark"><?= htmlspecialchars($r['username_cache']) ?></strong> · <?= foro_tiempo_relativo($r['created_at']) ?>
          <?php if ($r['editado_en']): ?>
            <button type="button" class="btn btn-link btn-sm p-0 text-muted text-decoration-underline align-baseline" data-historial-tipo="respuesta" data-historial-id="<?= (int) $r['id'] ?>">(editado)</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="pf-forum-post-body" id="cuerpo-respuesta-<?= (int) $r['id'] ?>"><?= $r['contenido'] ?></div>
      <?php if ($puedeEditarResp): ?>
        <div class="d-flex flex-column gap-2 mt-3" id="editar-respuesta-<?= (int) $r['id'] ?>" style="display:none;">
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
        <?php if ($puedeEditarResp): ?>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-respuesta" data-respuesta-id="<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i> Editar</button>
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
      });
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
        window.location.reload();
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

function pfActivarEdicion(prefijo) {
  document.getElementById('cuerpo-' + prefijo).style.display = 'none';
  document.getElementById('editar-' + prefijo).style.display = 'block';
}
function pfCancelarEdicion(prefijo) {
  document.getElementById('cuerpo-' + prefijo).style.display = '';
  document.getElementById('editar-' + prefijo).style.display = 'none';
}

<?php if ($puedeEditarTema): ?>
// Quill no puede inicializarse correctamente dentro de un contenedor oculto
// (display:none) — se crea la primera vez que se abre la edición, no antes.
let quillEditarTema = null;
document.getElementById('btn-editar-tema').addEventListener('click', function () {
  pfActivarEdicion('tema');
  if (!quillEditarTema) {
    quillEditarTema = new Quill('#editorEditarTema', { theme: 'snow', modules: { toolbar: PF_QUILL_TOOLBAR } });
    quillEditarTema.root.innerHTML = document.getElementById('fuente-editar-tema').innerHTML;
  }
});
document.getElementById('cancelar-tema').addEventListener('click', function () { pfCancelarEdicion('tema'); });
document.getElementById('guardar-tema').addEventListener('click', function () {
  const titulo = document.getElementById('editar-tema-titulo').value;
  const contenido = quillEditarTema.root.innerHTML;
  fetch('backend/editar_tema.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ tema_id: TEMA_ID, titulo: titulo, contenido: contenido, csrf_token: CSRF_TOKEN })
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'No se pudo guardar la edición.');
      }
    });
});
<?php endif; ?>

const quillsRespuestasEditar = {};
document.querySelectorAll('.btn-editar-respuesta').forEach(function (btn) {
  const id = btn.dataset.respuestaId;
  btn.addEventListener('click', function () {
    pfActivarEdicion('respuesta-' + id);
    if (!quillsRespuestasEditar[id]) {
      quillsRespuestasEditar[id] = new Quill('#editorEditarRespuesta-' + id, { theme: 'snow', modules: { toolbar: PF_QUILL_TOOLBAR } });
      quillsRespuestasEditar[id].root.innerHTML = document.getElementById('fuente-editar-respuesta-' + id).innerHTML;
    }
  });
});
document.querySelectorAll('.btn-cancelar-respuesta').forEach(function (btn) {
  btn.addEventListener('click', function () { pfCancelarEdicion('respuesta-' + btn.dataset.respuestaId); });
});
document.querySelectorAll('.btn-guardar-respuesta').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const id = btn.dataset.respuestaId;
    const contenido = quillsRespuestasEditar[id].root.innerHTML;
    fetch('backend/editar_respuesta.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ respuesta_id: id, contenido: contenido, csrf_token: CSRF_TOKEN })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          window.location.reload();
        } else {
          alert(data.message || 'No se pudo guardar la edición.');
        }
      });
  });
});

const historialModal = new bootstrap.Modal(document.getElementById('historialModal'));
document.querySelectorAll('[data-historial-tipo]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const tipo = btn.dataset.historialTipo;
    const id = btn.dataset.historialId;
    const contenedor = document.getElementById('historialContenido');
    contenedor.innerHTML = '<p class="text-muted text-center py-4">Cargando…</p>';
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
        if (data.success) {
          window.location.href = data.redirect || 'index.php';
        } else {
          alert(data.message || 'No se pudo completar la acción.');
        }
      });
  });
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
