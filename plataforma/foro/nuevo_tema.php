<?php
require_once __DIR__ . '/backend/foro_helpers.php';
require_login('index.php');

$page_title = 'Nuevo tema';
$usaEditorEnriquecido = true;
$categorias = foro_categorias_arbol_plano(foro_categorias_arbol());
$categoriaPreseleccionada = (int) ($_GET['categoria_id'] ?? 0);
$cursoId = (int) ($_GET['curso_id'] ?? 0);
$leccionId = (int) ($_GET['leccion_id'] ?? 0);

$curso = null;
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
<?php endif; ?>

<div id="nuevoTemaError" class="alert alert-danger d-none"></div>

<form id="nuevoTemaForm" class="card shadow-sm">
  <div class="card-body">
    <input type="hidden" id="curso_id" value="<?= (int) $cursoId ?>">
    <input type="hidden" id="leccion_id" value="<?= (int) $leccionId ?>">
    <div class="mb-3">
      <label for="categoria_id" class="form-label fw-bold">Categoría</label>
      <select id="categoria_id" name="categoria_id" class="form-select" required>
        <option value="">Selecciona una categoría</option>
        <?php foreach ($categorias as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>" <?= $categoriaPreseleccionada === (int) $cat['id'] ? 'selected' : '' ?>>
            <?= str_repeat('— ', (int) $cat['nivel']) ?><?= htmlspecialchars($cat['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label for="titulo" class="form-label fw-bold">Título</label>
      <input type="text" class="form-control" id="titulo" name="titulo" maxlength="200" required>
    </div>
    <div class="mb-3">
      <label for="editorNuevoTema" class="form-label fw-bold">Mensaje</label>
      <div class="pf-forum-editor" id="editorNuevoTema"></div>
    </div>
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

document.getElementById('nuevoTemaForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const $error = document.getElementById('nuevoTemaError');
  $error.style.display = 'none';

  fetch('backend/crear_tema.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      categoria_id: document.getElementById('categoria_id').value,
      curso_id: document.getElementById('curso_id').value,
      leccion_id: document.getElementById('leccion_id').value,
      titulo: document.getElementById('titulo').value,
      contenido: quillNuevoTema.root.innerHTML,
      csrf_token: <?= json_encode(csrf_token()) ?>
    })
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
