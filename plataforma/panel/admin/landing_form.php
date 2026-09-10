<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/landing_pages.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$landing = ['titulo' => '', 'slug' => '', 'contenido_html' => '', 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM landing_pages WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $landing = $stmt->get_result()->fetch_assoc() ?: $landing;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $titulo = trim($_POST['titulo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $titulo), '-'));
    }
    $activo = isset($_POST['activo']) ? 1 : 0;
    $contenidoHtml = (string) $landing['contenido_html'];

    try {
        $extraido = procesar_subida_html_landing('html_file');
        if ($extraido !== null) {
            $contenidoHtml = $extraido;
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } elseif (!$id && $contenidoHtml === '') {
        $error = 'Sube un archivo .html.';
    } elseif ($error === '') {
        if ($id) {
            $stmt = $conn->prepare('UPDATE landing_pages SET titulo=?, slug=?, contenido_html=?, activo=? WHERE id=?');
            $stmt->bind_param('sssii', $titulo, $slug, $contenidoHtml, $activo, $id);
        } else {
            $miId = (int) $_SESSION['usuario_perfil_id'];
            $stmt = $conn->prepare('INSERT INTO landing_pages (titulo, slug, contenido_html, activo, creado_por) VALUES (?,?,?,?,?)');
            $stmt->bind_param('sssii', $titulo, $slug, $contenidoHtml, $activo, $miId);
        }
        if ($stmt->execute()) {
            if ($esAjax) {
                echo json_encode(['success' => true, 'redirect' => 'landing_pages.php']);
                exit;
            }
            header('Location: landing_pages.php');
            exit;
        }
        $error = '¿El slug ya existe? Prueba con otro.';
        $stmt->close();
    }

    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$pageTitle = $id ? 'Editar landing page' : 'Nueva landing page';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3" enctype="multipart/form-data" data-ajax-form style="max-width:640px;">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-12"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($landing['titulo']) ?>" required></div>
  <div class="col-12">
    <label class="form-label">Slug (opcional)</label>
    <input class="form-control" name="slug" value="<?= htmlspecialchars($landing['slug']) ?>">
    <div class="form-text">Define la URL: <code>?action=landing&slug=tu-slug</code>. Vacío = se genera del título.</div>
  </div>
  <div class="col-12">
    <label class="form-label">Archivo .html<?= $id ? ' (opcional — deja vacío para conservar el actual)' : '' ?></label>
    <input type="file" class="form-control" name="html_file" accept=".html,.htm">
    <div class="form-text">
      Sube el HTML tal cual lo exportó tu herramienta de diseño (con &lt;style&gt;/&lt;script&gt; propios si los tiene) —
      se extrae automáticamente lo necesario para incrustarlo en el navbar/footer de la plataforma.
    </div>
  </div>
  <?php if ($id && $landing['contenido_html']): ?>
    <div class="col-12">
      <p class="text-muted small mb-1">Ya tiene contenido subido. <a href="../../index.php?action=landing&slug=<?= urlencode($landing['slug']) ?>" target="_blank">Ver landing page actual</a>.</p>
    </div>
  <?php endif; ?>
  <div class="col-12 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="activo" id="activo" <?= (int) $landing['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicada</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
