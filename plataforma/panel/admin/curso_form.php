<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$curso = ['titulo' => '', 'slug' => '', 'descripcion' => '', 'nivel' => 'principiante', 'duracion_horas' => '',
          'precio' => 0, 'imagen_portada' => '', 'gratuito' => 0, 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM cursos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc() ?: $curso;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $titulo), '-'));
    }
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nivel = $_POST['nivel'] ?? 'principiante';
    $duracion = $_POST['duracion_horas'] !== '' ? (float) $_POST['duracion_horas'] : null;
    $precio = (float) ($_POST['precio'] ?? 0);
    $imagen = (string) $curso['imagen_portada'];
    $gratuito = isset($_POST['gratuito']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    try {
        $imagenSubida = procesar_subida_imagen('imagen_portada_file', 'cursos');
        if ($imagenSubida !== null) {
            $imagen = $imagenSubida;
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } elseif ($error === '') {
        if ($id) {
            $stmt = $conn->prepare(
                'UPDATE cursos SET titulo=?, slug=?, descripcion=?, nivel=?, duracion_horas=?, precio=?, imagen_portada=?, gratuito=?, activo=? WHERE id=?'
            );
            $stmt->bind_param('ssssddsiii', $titulo, $slug, $descripcion, $nivel, $duracion, $precio, $imagen, $gratuito, $activo, $id);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO cursos (titulo, slug, descripcion, nivel, duracion_horas, precio, imagen_portada, gratuito, activo) VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param('ssssddsii', $titulo, $slug, $descripcion, $nivel, $duracion, $precio, $imagen, $gratuito, $activo);
        }
        if ($stmt->execute()) {
            header('Location: cursos.php');
            exit;
        }
        $error = '¿El slug ya existe? Prueba con otro.';
        $stmt->close();
    }
}

$pageTitle = $id ? 'Editar curso' : 'Nuevo curso';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-6"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($curso['titulo']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Slug (opcional)</label><input class="form-control" name="slug" value="<?= htmlspecialchars($curso['slug']) ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $curso['descripcion']) ?></textarea></div>
  <div class="col-md-4">
    <label class="form-label">Nivel</label>
    <select class="form-select" name="nivel">
      <?php foreach (['principiante', 'intermedio', 'avanzado'] as $n): ?>
        <option value="<?= $n ?>" <?= $curso['nivel'] === $n ? 'selected' : '' ?>><?= ucfirst($n) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4"><label class="form-label">Duración (horas)</label><input type="number" step="0.5" class="form-control" name="duracion_horas" value="<?= htmlspecialchars((string) $curso['duracion_horas']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $curso['precio']) ?>"></div>
  <div class="col-md-6">
    <label class="form-label">Imagen de portada</label>
    <input type="file" class="form-control" name="imagen_portada_file" accept="image/png,image/jpeg,image/webp,image/gif">
    <?php if ($curso['imagen_portada']): ?>
      <div class="mt-2 d-flex align-items-center gap-2">
        <img src="../../<?= htmlspecialchars($curso['imagen_portada']) ?>" alt="" style="height:60px;border-radius:6px;">
        <span class="text-muted small">Imagen actual — sube otra para reemplazarla.</span>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($id): ?>
    <div class="col-12">
      <p class="text-muted small mb-0">
        El foro de este curso ya no se configura aquí — se genera solo por curso y por lección.
        <a href="../../../foro/curso.php?curso_id=<?= (int) $id ?>" target="_blank">Ver su foro</a>.
      </p>
    </div>
  <?php endif; ?>
  <div class="col-md-6 form-check">
    <input type="checkbox" class="form-check-input" name="gratuito" id="gratuito" <?= (int) $curso['gratuito'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="gratuito">Curso gratuito</label>
  </div>
  <div class="col-md-6 form-check">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $curso['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicado</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
