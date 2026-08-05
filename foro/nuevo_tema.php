<?php
require_once __DIR__ . '/backend/foro_helpers.php';
require_login('index.php');

$page_title = 'Nuevo tema';
$categorias = foro_categorias();
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

<p class="pf-forum-breadcrumb"><a href="index.php">Foro</a> / Nuevo tema</p>

<div class="pf-forum-head"><h1>Nuevo tema</h1></div>

<?php if ($curso): ?>
  <p class="pf-forum-empty" style="text-align:left;padding:12px 16px;background:var(--pf-surface);border:1px solid var(--pf-line);border-radius:var(--pf-radius-md);">
    <i class="bi bi-mortarboard-fill"></i> Este tema quedará ligado a <strong><?= htmlspecialchars($curso['titulo']) ?></strong><?= $leccionCtx ? ' — ' . htmlspecialchars($leccionCtx['titulo']) : '' ?>.
  </p>
<?php endif; ?>

<div id="nuevoTemaError" class="alert alert-danger d-none" style="display:none;"></div>

<form id="nuevoTemaForm" class="pf-forum-form">
  <input type="hidden" id="curso_id" value="<?= (int) $cursoId ?>">
  <input type="hidden" id="leccion_id" value="<?= (int) $leccionId ?>">
  <div class="campo">
    <label for="categoria_id">Categoría</label>
    <select id="categoria_id" name="categoria_id" class="form-select" required>
      <option value="">Selecciona una categoría</option>
      <?php foreach ($categorias as $cat): ?>
        <option value="<?= (int) $cat['id'] ?>" <?= $categoriaPreseleccionada === (int) $cat['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="campo">
    <label for="titulo">Título</label>
    <input type="text" id="titulo" name="titulo" maxlength="200" required>
  </div>
  <div class="campo">
    <label for="contenido">Mensaje</label>
    <textarea id="contenido" name="contenido" rows="8" required placeholder="Escribe tu mensaje. Usa @usuario para mencionar a alguien."></textarea>
  </div>
  <button type="submit" class="pf-btn pf-btn-primary">Publicar tema</button>
</form>

<script>
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
      contenido: document.getElementById('contenido').value,
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
