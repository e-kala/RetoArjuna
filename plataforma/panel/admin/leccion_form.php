<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

// Una lección pertenece a un curso O a un evento, nunca ambos (ver
// schema_lecciones_compartidas.sql).
$cursoId = (int) ($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
$eventoId = (int) ($_GET['evento_id'] ?? $_POST['evento_id'] ?? 0);
$esEvento = $eventoId > 0;
$padreId = $esEvento ? $eventoId : $cursoId;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($esEvento) {
    $stmt = $conn->prepare('SELECT id, titulo FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $padreId);
    $stmt->execute();
    $padre = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $volverUrl = 'lecciones.php?evento_id=' . $padreId;
} else {
    $stmt = $conn->prepare('SELECT id, titulo FROM cursos WHERE id = ?');
    $stmt->bind_param('i', $padreId);
    $stmt->execute();
    $padre = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $volverUrl = 'lecciones.php?curso_id=' . $padreId;
}
if (!$padre) {
    header('Location: ' . ($esEvento ? 'eventos.php' : 'cursos.php'));
    exit;
}

$leccion = ['titulo' => '', 'descripcion' => '', 'tipo_contenido' => 'video', 'contenido_url' => '',
            'contenido_texto' => '', 'orden' => 0, 'duracion_min' => '', 'vista_previa' => 0, 'foro_url' => ''];
$videos = [''];
$materiales = [['titulo' => '', 'url' => '']];

if ($id) {
    $stmt = $esEvento
        ? $conn->prepare('SELECT * FROM lecciones WHERE id = ? AND evento_id = ?')
        : $conn->prepare('SELECT * FROM lecciones WHERE id = ? AND curso_id = ?');
    $stmt->bind_param('ii', $id, $padreId);
    $stmt->execute();
    $leccion = $stmt->get_result()->fetch_assoc() ?: $leccion;
    $stmt->close();

    $stmt = $conn->prepare('SELECT url FROM leccion_videos WHERE leccion_id = ? ORDER BY orden');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $videosGuardados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'url');
    $stmt->close();
    if ($videosGuardados) {
        $videos = $videosGuardados;
    }

    $stmt = $conn->prepare('SELECT titulo, url FROM leccion_materiales WHERE leccion_id = ? ORDER BY orden');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $materialesGuardados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($materialesGuardados) {
        $materiales = $materialesGuardados;
    }
} else {
    $columna = $esEvento ? 'evento_id' : 'curso_id';
    $max = $conn->query("SELECT COALESCE(MAX(orden),0) m FROM lecciones WHERE $columna = " . $padreId)->fetch_assoc()['m'];
    $leccion['orden'] = (int) $max + 1;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') !== 'eliminar_leccion') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo_contenido'] ?? 'video';
    $contenidoUrl = trim($_POST['contenido_url'] ?? '');
    $contenidoTexto = $_POST['contenido_texto'] ?? '';
    $orden = (int) ($_POST['orden'] ?? 0);
    $duracion = $_POST['duracion_min'] !== '' ? (int) $_POST['duracion_min'] : null;
    $vistaPrevia = isset($_POST['vista_previa']) ? 1 : 0;
    $foroUrl = trim($_POST['foro_url'] ?? '');

    $videosEnviados = array_values(array_filter(array_map('trim', $_POST['videos'] ?? []), fn($v) => $v !== ''));
    $materialesTitulos = $_POST['materiales_titulo'] ?? [];
    $materialesUrls = $_POST['materiales_url'] ?? [];
    $materialesEnviados = [];
    foreach ($materialesTitulos as $i => $t) {
        $t = trim($t);
        $u = trim($materialesUrls[$i] ?? '');
        if ($t !== '' && $u !== '') {
            $materialesEnviados[] = ['titulo' => $t, 'url' => $u];
        }
    }

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } else {
        if ($id) {
            $stmt = $esEvento
                ? $conn->prepare('UPDATE lecciones SET titulo=?, descripcion=?, tipo_contenido=?, contenido_url=?, contenido_texto=?, orden=?, duracion_min=?, vista_previa=?, foro_url=? WHERE id=? AND evento_id=?')
                : $conn->prepare('UPDATE lecciones SET titulo=?, descripcion=?, tipo_contenido=?, contenido_url=?, contenido_texto=?, orden=?, duracion_min=?, vista_previa=?, foro_url=? WHERE id=? AND curso_id=?');
            $stmt->bind_param('sssssiiisii', $titulo, $descripcion, $tipo, $contenidoUrl, $contenidoTexto, $orden, $duracion, $vistaPrevia, $foroUrl, $id, $padreId);
        } else {
            $stmt = $esEvento
                ? $conn->prepare('INSERT INTO lecciones (evento_id, titulo, descripcion, tipo_contenido, contenido_url, contenido_texto, orden, duracion_min, vista_previa, foro_url) VALUES (?,?,?,?,?,?,?,?,?,?)')
                : $conn->prepare('INSERT INTO lecciones (curso_id, titulo, descripcion, tipo_contenido, contenido_url, contenido_texto, orden, duracion_min, vista_previa, foro_url) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('isssssiiis', $padreId, $titulo, $descripcion, $tipo, $contenidoUrl, $contenidoTexto, $orden, $duracion, $vistaPrevia, $foroUrl);
        }
        if ($stmt->execute()) {
            $leccionId = $id ?: $stmt->insert_id;
            $stmt->close();

            $del = $conn->prepare('DELETE FROM leccion_videos WHERE leccion_id = ?');
            $del->bind_param('i', $leccionId);
            $del->execute();
            $del->close();
            foreach ($videosEnviados as $i => $url) {
                $urlNormalizada = normalizar_url_youtube($url);
                $ins = $conn->prepare('INSERT INTO leccion_videos (leccion_id, url, orden) VALUES (?, ?, ?)');
                $ins->bind_param('isi', $leccionId, $urlNormalizada, $i);
                $ins->execute();
                $ins->close();
            }

            $del = $conn->prepare('DELETE FROM leccion_materiales WHERE leccion_id = ?');
            $del->bind_param('i', $leccionId);
            $del->execute();
            $del->close();
            foreach ($materialesEnviados as $i => $m) {
                $ins = $conn->prepare('INSERT INTO leccion_materiales (leccion_id, titulo, url, orden) VALUES (?, ?, ?, ?)');
                $ins->bind_param('issi', $leccionId, $m['titulo'], $m['url'], $i);
                $ins->execute();
                $ins->close();
            }

            header('Location: ' . $volverUrl);
            exit;
        }
        $error = '¿El orden ya está usado en ' . ($esEvento ? 'este evento' : 'este curso') . '?';
        $stmt->close();
    }
}

$pageTitle = $id ? 'Editar lección' : 'Nueva lección';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($padre['titulo']) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="<?= $esEvento ? 'evento_id' : 'curso_id' ?>" value="<?= $padreId ?>">
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-8"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($leccion['titulo']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Orden</label><input type="number" class="form-control" name="orden" value="<?= (int) $leccion['orden'] ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="2"><?= htmlspecialchars((string) $leccion['descripcion']) ?></textarea></div>
  <div class="col-md-4">
    <label class="form-label">Tipo de contenido</label>
    <select class="form-select" name="tipo_contenido" id="tipoContenido">
      <?php foreach (['video', 'pdf', 'texto', 'quiz'] as $t): ?>
        <option value="<?= $t ?>" <?= $leccion['tipo_contenido'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4"><label class="form-label">Duración (min)</label><input type="number" class="form-control" name="duracion_min" value="<?= htmlspecialchars((string) $leccion['duracion_min']) ?>"></div>
  <div class="col-md-4 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="vista_previa" id="vista_previa" <?= (int) $leccion['vista_previa'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="vista_previa">Vista previa gratuita (demo)</label>
  </div>
  <?php
  $foroFieldId = 'leccion';
  $foroFieldValor = (string) $leccion['foro_url'];
  include __DIR__ . '/_foro_link_field.php';
  ?>

  <div class="col-12">
    <label class="form-label">Videos de YouTube (si el tipo es "video")</label>
    <div id="videosLista">
      <?php foreach ($videos as $url): ?>
        <div class="input-group mb-2 video-fila">
          <input class="form-control" name="videos[]" value="<?= htmlspecialchars($url) ?>" placeholder="https://www.youtube.com/watch?v=XXXX">
          <button type="button" class="btn btn-outline-danger btn-quitar-fila">&times;</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAgregarVideo">+ Agregar otro video</button>
    <div class="form-text">Cualquier formato de link de YouTube funciona (watch, youtu.be, shorts) — se convierte automáticamente. Se muestran en este orden.</div>
  </div>

  <div class="col-12"><label class="form-label">Ruta a PDF (si el tipo es "pdf")</label><input class="form-control" name="contenido_url" value="<?= htmlspecialchars((string) $leccion['contenido_url']) ?>" placeholder="content/docs/manual.pdf"></div>
  <div class="col-12"><label class="form-label">Contenido de texto (HTML básico, solo si el tipo es "texto")</label><textarea class="form-control" name="contenido_texto" rows="6"><?= htmlspecialchars((string) $leccion['contenido_texto']) ?></textarea></div>

  <div class="col-12">
    <label class="form-label">Materiales de apoyo (enlaces adicionales: documentos, recursos, etc.)</label>
    <div id="materialesLista">
      <?php foreach ($materiales as $m): ?>
        <div class="row g-2 mb-2 material-fila">
          <div class="col-md-4"><input class="form-control" name="materiales_titulo[]" value="<?= htmlspecialchars($m['titulo']) ?>" placeholder="Título del material"></div>
          <div class="col-md-7"><input class="form-control" name="materiales_url[]" value="<?= htmlspecialchars($m['url']) ?>" placeholder="https://..."></div>
          <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 btn-quitar-fila">&times;</button></div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAgregarMaterial">+ Agregar material</button>
  </div>

  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>

<script>
document.getElementById('btnAgregarVideo').addEventListener('click', function () {
  const div = document.createElement('div');
  div.className = 'input-group mb-2 video-fila';
  div.innerHTML = '<input class="form-control" name="videos[]" placeholder="https://www.youtube.com/watch?v=XXXX">' +
                  '<button type="button" class="btn btn-outline-danger btn-quitar-fila">&times;</button>';
  document.getElementById('videosLista').appendChild(div);
});

document.getElementById('btnAgregarMaterial').addEventListener('click', function () {
  const div = document.createElement('div');
  div.className = 'row g-2 mb-2 material-fila';
  div.innerHTML = '<div class="col-md-4"><input class="form-control" name="materiales_titulo[]" placeholder="Título del material"></div>' +
                  '<div class="col-md-7"><input class="form-control" name="materiales_url[]" placeholder="https://..."></div>' +
                  '<div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 btn-quitar-fila">&times;</button></div>';
  document.getElementById('materialesLista').appendChild(div);
});

document.addEventListener('click', function (event) {
  if (event.target.classList.contains('btn-quitar-fila')) {
    event.target.closest('.video-fila, .material-fila').remove();
  }
});
</script>
<?php include __DIR__ . '/_footer.php'; ?>
