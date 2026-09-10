<?php
require_once __DIR__ . '/backend/foro_helpers.php';
require_login('index.php');

$page_title = 'Nuevo tema';
$usaEditorEnriquecido = true;
$usuarioActual = current_user();
$etiquetasDisponibles = foro_categorias_arbol_plano(foro_categorias_arbol($usuarioActual));
$categoriasLibresDisponibles = foro_categorias_libres_todas();
$etiquetaPreseleccionada = (int) ($_GET['etiqueta_id'] ?? 0);
$categoriaLibrePreseleccionada = (int) ($_GET['categoria_libre_id'] ?? 0);
$cursoId = (int) ($_GET['curso_id'] ?? 0);
$eventoId = (int) ($_GET['evento_id'] ?? 0);
$leccionId = (int) ($_GET['leccion_id'] ?? 0);

$curso = null;
$evento = null;
$leccionCtx = null;
if ($cursoId) {
    $stmt = $conn->prepare('SELECT id, titulo FROM cursos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $cursoId);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc();
    if (!$curso) {
        $cursoId = 0;
    } elseif ($leccionId) {
        $stmt = $conn->prepare('SELECT id, titulo FROM lecciones WHERE id = ? AND curso_id = ? LIMIT 1');
        $stmt->bind_param('ii', $leccionId, $cursoId);
        $stmt->execute();
        $leccionCtx = $stmt->get_result()->fetch_assoc();
        if (!$leccionCtx) {
            $leccionId = 0;
        }
    }
} elseif ($eventoId) {
    $stmt = $conn->prepare('SELECT id, titulo FROM eventos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    if (!$evento) {
        $eventoId = 0;
    } elseif ($leccionId) {
        $stmt = $conn->prepare('SELECT id, titulo FROM lecciones WHERE id = ? AND evento_id = ? LIMIT 1');
        $stmt->bind_param('ii', $leccionId, $eventoId);
        $stmt->execute();
        $leccionCtx = $stmt->get_result()->fetch_assoc();
        if (!$leccionCtx) {
            $leccionId = 0;
        }
    }
}

require __DIR__ . '/inc/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php">Foro</a></li>
    <li class="breadcrumb-item active" aria-current="page">Nuevo tema</li>
  </ol>
</nav>

<h1 class="h3 fw-bold mb-4">Nuevo tema</h1>

<?php if ($curso): ?>
  <div class="alert alert-light border d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-mortarboard-fill"></i> Este tema quedará ligado a <strong><?= htmlspecialchars($curso['titulo']) ?></strong><?= $leccionCtx ? ' — ' . htmlspecialchars($leccionCtx['titulo']) : '' ?>.
  </div>
<?php elseif ($evento): ?>
  <div class="alert alert-light border d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar-event-fill"></i> Este tema quedará ligado a <strong><?= htmlspecialchars($evento['titulo']) ?></strong><?= $leccionCtx ? ' — ' . htmlspecialchars($leccionCtx['titulo']) : '' ?>.
  </div>
<?php endif; ?>

<div id="nuevoTemaError" class="alert alert-danger d-none"></div>

<form id="nuevoTemaForm" class="card shadow-sm">
  <div class="card-body">
    <input type="hidden" id="curso_id" value="<?= (int) $cursoId ?>">
    <input type="hidden" id="evento_id" value="<?= (int) $eventoId ?>">
    <input type="hidden" id="leccion_id" value="<?= (int) $leccionId ?>">
    <div class="mb-3">
      <label for="categoriaLibreId" class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
      <select class="form-select" id="categoriaLibreId">
        <option value="">— Elige una —</option>
        <?php foreach ($categoriasLibresDisponibles as $cl): ?>
          <option value="<?= (int) $cl['id'] ?>" <?= $categoriaLibrePreseleccionada === (int) $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="categoriaLibreNueva" class="form-label mt-2 small">¿No está la que buscas? Escribe una nueva — se crea al instante, sin esperar aprobación.</label>
      <input type="text" class="form-control" id="categoriaLibreNueva" placeholder="ej. Anuncios, Testimonios">
    </div>
    <div class="mb-3">
      <label for="titulo" class="form-label fw-bold">Título</label>
      <input type="text" class="form-control" id="titulo" name="titulo" maxlength="200" required>
    </div>
    <div class="mb-3">
      <label for="editorNuevoTema" class="form-label fw-bold">Mensaje</label>
      <div class="pf-forum-editor" id="editorNuevoTema"></div>
    </div>
    <div class="mb-3">
      <label class="form-label fw-bold">Etiquetas (opcional)</label>
      <div class="d-flex flex-wrap gap-3">
        <?php foreach ($etiquetasDisponibles as $et): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="etiquetas[]" value="<?= (int) $et['id'] ?>" id="etiqueta-<?= (int) $et['id'] ?>" <?= $etiquetaPreseleccionada === (int) $et['id'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="etiqueta-<?= (int) $et['id'] ?>"><?= htmlspecialchars($et['nombre']) ?></label>
          </div>
        <?php endforeach; ?>
        <?php if (!$etiquetasDisponibles): ?><span class="text-muted small">Todavía no hay etiquetas.</span><?php endif; ?>
      </div>
      <label for="etiquetaNueva" class="form-label mt-2 small">¿Falta una etiqueta? Escribe una nueva (o varias, separadas por coma) — quedará pendiente de aprobación de un admin.</label>
      <input type="text" class="form-control" id="etiquetaNueva" placeholder="ej. gratitud, práctica diaria">
    </div>
    <?php if ($leccionCtx): ?>
      <div class="mb-3">
        <label class="form-label fw-bold">Visibilidad de esta reflexión</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="visibilidad" id="visPublico" value="publico" checked>
          <label class="form-check-label" for="visPublico">Pública — cualquiera en el foro puede verla</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="visibilidad" id="visPrivado" value="privado">
          <label class="form-check-label" for="visPrivado">Solo yo</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="visibilidad" id="visCompartido" value="compartido">
          <label class="form-check-label" for="visCompartido">Compartir con alguien en específico</label>
        </div>
        <div class="mt-2 d-none" id="compartidoConWrap">
          <input type="text" class="form-control" id="compartidoCon" placeholder="Usuario o correo de la persona">
        </div>
        <div class="form-text d-none" id="avisoAdminVisibilidad">
          Los administradores pueden ver esta entrada por motivos de moderación y seguridad, aunque la marques como privada o compartida.
        </div>
      </div>
    <?php endif; ?>
    <button type="submit" class="btn fw-bold" style="background:var(--pf-accent);color:#fff;">Publicar tema</button>
  </div>
</form>

<script>
const PF_QUILL_TOOLBAR = [['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'code-block'], ['link'], ['clean']];
const quillNuevoTema = new Quill('#editorNuevoTema', {
  theme: 'snow',
  placeholder: 'Escribe tu mensaje. Usa @usuario para mencionar a alguien.',
  modules: { toolbar: PF_QUILL_TOOLBAR }
});

<?php if ($leccionCtx): ?>
document.querySelectorAll('input[name="visibilidad"]').forEach(function (radio) {
  radio.addEventListener('change', function () {
    const esPrivadoOCompartido = this.value !== 'publico';
    document.getElementById('compartidoConWrap').classList.toggle('d-none', this.value !== 'compartido');
    document.getElementById('avisoAdminVisibilidad').classList.toggle('d-none', !esPrivadoOCompartido);
  });
});
<?php endif; ?>

document.getElementById('nuevoTemaForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const $error = document.getElementById('nuevoTemaError');
  $error.style.display = 'none';

  const categoriaLibreId = document.getElementById('categoriaLibreId').value;
  const categoriaLibreNueva = document.getElementById('categoriaLibreNueva').value.trim();
  if (!categoriaLibreId && !categoriaLibreNueva) {
    $error.textContent = 'Elige una categoría de la lista o escribe una nueva.';
    $error.style.display = 'block';
    document.getElementById('categoriaLibreId').focus();
    return;
  }

  const visibilidadEl = document.querySelector('input[name="visibilidad"]:checked');
  const compartidoConEl = document.getElementById('compartidoCon');

  const datos = new URLSearchParams({
    curso_id: document.getElementById('curso_id').value,
    evento_id: document.getElementById('evento_id').value,
    leccion_id: document.getElementById('leccion_id').value,
    titulo: document.getElementById('titulo').value,
    contenido: quillNuevoTema.root.innerHTML,
    visibilidad: visibilidadEl ? visibilidadEl.value : 'publico',
    compartido_con: compartidoConEl ? compartidoConEl.value : '',
    etiqueta_nueva: document.getElementById('etiquetaNueva').value,
    categoria_libre_id: document.getElementById('categoriaLibreId').value,
    categoria_libre_nueva: document.getElementById('categoriaLibreNueva').value,
    csrf_token: <?= json_encode(csrf_token()) ?>
  });
  document.querySelectorAll('input[name="etiquetas[]"]:checked').forEach(el => datos.append('etiquetas[]', el.value));

  fetch('backend/crear_tema.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: datos
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.href = data.redirect;
      } else {
        $error.textContent = data.message || 'No se pudo publicar el tema.';
        $error.style.display = 'block';
      }
    })
    .catch(() => {
      $error.textContent = 'Error de conexión. Intenta de nuevo.';
      $error.style.display = 'block';
    });
});
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
