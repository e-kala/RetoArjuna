<?php
// Editor unificado de Cursos y Eventos: al crear contenido nuevo eliges arriba
// si es "Curso" o "Evento" y el formulario cambia para pedir los campos que
// correspondan — así se evita registrar algo en la tabla equivocada desde el
// principio. Para contenido YA existente que quedó mal clasificado, usa el
// botón "Convertir a evento/curso" en las listas (cursos.php/eventos.php),
// que migra los datos en vez de tener que volver a capturarlos aquí.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'curso';
$tipo = in_array($tipo, ['curso', 'evento'], true) ? $tipo : 'curso';

$curso = ['titulo' => '', 'slug' => '', 'descripcion' => '', 'nivel' => 'principiante', 'duracion_horas' => '',
          'precio' => 0, 'imagen_portada' => '', 'foro_url' => '', 'gratuito' => 0, 'incluido_membresia' => 0, 'activo' => 1];
$evento = ['titulo' => '', 'slug' => '', 'descripcion' => '', 'tipo' => 'online', 'ubicacion' => '',
           'fecha_inicio' => '', 'fecha_fin' => '', 'cupo_maximo' => '', 'precio' => 0,
           'imagen_portada' => '', 'foro_url' => '', 'video_grabado_url' => '', 'gratuito' => 0,
           'solo_miembros' => 0, 'incluido_membresia' => 0, 'activo' => 1];

if ($id && $tipo === 'curso') {
    $stmt = $conn->prepare('SELECT * FROM cursos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc() ?: $curso;
    $stmt->close();
} elseif ($id && $tipo === 'evento') {
    $stmt = $conn->prepare('SELECT * FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc() ?: $evento;
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
    $precio = (float) ($_POST['precio'] ?? 0);
    $gratuito = isset($_POST['gratuito']) ? 1 : 0;
    $incluidoMembresia = isset($_POST['incluido_membresia']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($tipo === 'curso') {
        $nivel = $_POST['nivel'] ?? 'principiante';
        $duracion = $_POST['duracion_horas'] !== '' ? (float) $_POST['duracion_horas'] : null;
        $imagen = (string) $curso['imagen_portada'];
        $foroUrl = trim($_POST['foro_url'] ?? '');
        try {
            $imagen = procesar_imagen_form('imagen_portada_file', 'cursos', $imagen);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($titulo === '') {
            $error = 'El título es obligatorio.';
        } elseif ($error === '') {
            if ($id) {
                $stmt = $conn->prepare('UPDATE cursos SET titulo=?, slug=?, descripcion=?, nivel=?, duracion_horas=?, precio=?, imagen_portada=?, foro_url=?, gratuito=?, incluido_membresia=?, activo=? WHERE id=?');
                $stmt->bind_param('ssssddssiiii', $titulo, $slug, $descripcion, $nivel, $duracion, $precio, $imagen, $foroUrl, $gratuito, $incluidoMembresia, $activo, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO cursos (titulo, slug, descripcion, nivel, duracion_horas, precio, imagen_portada, foro_url, gratuito, incluido_membresia, activo) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->bind_param('ssssddssiii', $titulo, $slug, $descripcion, $nivel, $duracion, $precio, $imagen, $foroUrl, $gratuito, $incluidoMembresia, $activo);
            }
            if ($stmt->execute()) {
                header('Location: cursos.php');
                exit;
            }
            $error = '¿El slug ya existe? Prueba con otro.';
            $stmt->close();
        }
    } else {
        $tipoEvento = $_POST['tipo_evento'] ?? 'online';
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
        $fechaFin = $_POST['fecha_fin'] !== '' ? $_POST['fecha_fin'] : null;
        $cupoMaximo = $_POST['cupo_maximo'] !== '' ? (int) $_POST['cupo_maximo'] : null;
        $imagen = (string) $evento['imagen_portada'];
        $foroUrl = trim($_POST['foro_url'] ?? '');
        $videoGrabado = trim($_POST['video_grabado_url'] ?? '');
        $soloMiembros = isset($_POST['solo_miembros']) ? 1 : 0;

        try {
            $imagen = procesar_imagen_form('imagen_portada_file', 'eventos', $imagen);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($error === '' && ($titulo === '' || $fechaInicio === '')) {
            $error = 'Título y fecha de inicio son obligatorios.';
        }

        if ($error === '') {
            if ($id) {
                $stmt = $conn->prepare('UPDATE eventos SET titulo=?, slug=?, descripcion=?, tipo=?, ubicacion=?, fecha_inicio=?, fecha_fin=?, cupo_maximo=?, precio=?, imagen_portada=?, foro_url=?, video_grabado_url=?, gratuito=?, solo_miembros=?, incluido_membresia=?, activo=? WHERE id=?');
                $stmt->bind_param('sssssssidsssiiiii', $titulo, $slug, $descripcion, $tipoEvento, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $videoGrabado, $gratuito, $soloMiembros, $incluidoMembresia, $activo, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO eventos (titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, fecha_fin, cupo_maximo, precio, imagen_portada, foro_url, video_grabado_url, gratuito, solo_miembros, incluido_membresia, activo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->bind_param('sssssssidsssiiii', $titulo, $slug, $descripcion, $tipoEvento, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $videoGrabado, $gratuito, $soloMiembros, $incluidoMembresia, $activo);
            }
            if ($stmt->execute()) {
                header('Location: eventos.php');
                exit;
            }
            $error = '¿El slug ya existe? Prueba con otro.';
            $stmt->close();
        }
    }
}

$item = $tipo === 'curso' ? $curso : $evento;
$pageTitle = ($id ? 'Editar ' : 'Nuevo ') . ($tipo === 'curso' ? 'curso' : 'evento');
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mb-4">
  <label class="form-label fw-bold">¿Qué tipo de contenido es?</label>
  <?php if ($id): ?>
    <p class="text-muted small mb-0">
      Este contenido ya existe como <strong><?= $tipo === 'curso' ? 'curso' : 'evento' ?></strong>.
      Para cambiarlo de tipo, ciérralo aquí y usa el botón "Convertir a <?= $tipo === 'curso' ? 'evento' : 'curso' ?>" en la lista de <?= $tipo === 'curso' ? 'cursos.php' : 'eventos.php' ?>.
    </p>
  <?php else: ?>
    <div class="btn-group" role="group">
      <a href="?tipo=curso" class="btn <?= $tipo === 'curso' ? 'btn-dark' : 'btn-outline-secondary' ?>">🎓 Curso</a>
      <a href="?tipo=evento" class="btn <?= $tipo === 'evento' ? 'btn-dark' : 'btn-outline-secondary' ?>">📅 Evento</a>
    </div>
  <?php endif; ?>
</div>

<form method="post" class="row g-3" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

  <div class="col-md-6"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($item['titulo']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Slug (opcional)</label><input class="form-control" name="slug" value="<?= htmlspecialchars($item['slug']) ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $item['descripcion']) ?></textarea></div>

  <?php if ($tipo === 'curso'): ?>
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
    <?php
    $imgPickerId = 'curso';
    $imgPickerCampo = 'imagen_portada_file';
    $imgPickerSubdir = 'cursos';
    $imgPickerActual = (string) $curso['imagen_portada'];
    $imgPickerLabel = 'Imagen de portada';
    include __DIR__ . '/_imagen_picker.php';
    ?>
    <?php
    $foroFieldId = 'curso';
    $foroFieldValor = (string) $curso['foro_url'];
    include __DIR__ . '/_foro_link_field.php';
    ?>
    <?php if ($id): ?>
      <div class="col-12">
        <p class="text-muted small mb-0">
          Las lecciones de este curso se gestionan aparte.
          <a href="lecciones.php?curso_id=<?= (int) $id ?>">Gestionar lecciones</a>.
        </p>
      </div>
    <?php endif; ?>
    <div class="col-md-6 form-check">
      <input type="checkbox" class="form-check-input" name="gratuito" id="gratuito" <?= (int) $curso['gratuito'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="gratuito">Curso gratuito</label>
    </div>
    <div class="col-md-6 form-check">
      <input type="checkbox" class="form-check-input" name="incluido_membresia" id="incluido_membresia" <?= (int) $curso['incluido_membresia'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="incluido_membresia">👑 Incluido con membresía (los miembros lo obtienen gratis)</label>
    </div>
  <?php else: ?>
    <div class="col-md-4">
      <label class="form-label">Tipo</label>
      <select class="form-select" name="tipo_evento">
        <option value="online" <?= $evento['tipo'] === 'online' ? 'selected' : '' ?>>En línea</option>
        <option value="presencial" <?= $evento['tipo'] === 'presencial' ? 'selected' : '' ?>>Presencial</option>
      </select>
    </div>
    <div class="col-md-8"><label class="form-label">Ubicación (dirección o liga de Zoom)</label><input class="form-control" name="ubicacion" value="<?= htmlspecialchars((string) $evento['ubicacion']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Fecha y hora de inicio</label><input type="datetime-local" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $evento['fecha_inicio'], 0, 16))) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Fecha y hora de fin (opcional)</label><input type="datetime-local" class="form-control" name="fecha_fin" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $evento['fecha_fin'], 0, 16))) ?>"></div>
    <div class="col-md-4"><label class="form-label">Cupo máximo (opcional)</label><input type="number" class="form-control" name="cupo_maximo" value="<?= htmlspecialchars((string) $evento['cupo_maximo']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $evento['precio']) ?>"></div>
    <div class="col-md-4 form-check mt-4">
      <input type="checkbox" class="form-check-input" name="gratuito" id="gratuito" <?= (int) $evento['gratuito'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="gratuito">Evento gratuito</label>
    </div>
    <?php
    $imgPickerId = 'evento';
    $imgPickerCampo = 'imagen_portada_file';
    $imgPickerSubdir = 'eventos';
    $imgPickerActual = (string) $evento['imagen_portada'];
    $imgPickerLabel = 'Imagen de portada';
    include __DIR__ . '/_imagen_picker.php';
    ?>
    <?php
    $foroFieldId = 'evento';
    $foroFieldValor = (string) $evento['foro_url'];
    include __DIR__ . '/_foro_link_field.php';
    ?>
    <div class="col-md-6"><label class="form-label">Video grabado (para "pasados/grabados")</label><input class="form-control" name="video_grabado_url" value="<?= htmlspecialchars((string) $evento['video_grabado_url']) ?>" placeholder="https://www.youtube.com/embed/..."></div>
    <div class="col-md-6 form-check mt-4">
      <input type="checkbox" class="form-check-input" name="solo_miembros" id="solo_miembros" <?= (int) $evento['solo_miembros'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="solo_miembros">👑 Exclusivo para miembros (masterclass)</label>
    </div>
    <div class="col-md-6 form-check mt-4">
      <input type="checkbox" class="form-check-input" name="incluido_membresia" id="incluido_membresia" <?= (int) $evento['incluido_membresia'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="incluido_membresia">👑 Incluido con membresía (gratis para miembros, los demás lo pueden comprar)</label>
    </div>
  <?php endif; ?>

  <div class="col-md-6 form-check">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $item['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicado</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
