<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$link = ['texto' => '', 'url' => '', 'area' => 'ambos', 'abre_nueva_pestana' => 0, 'orden' => 0, 'activo' => 1];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM navbar_links WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $link = $stmt->get_result()->fetch_assoc() ?: $link;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $texto = trim($_POST['texto'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $area = in_array($_POST['area'] ?? '', ['nav', 'footer', 'ambos'], true) ? $_POST['area'] : 'ambos';
    $abreNuevaPestana = isset($_POST['abre_nueva_pestana']) ? 1 : 0;
    $orden = (int) ($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($texto === '' || $url === '') {
        $error = 'El texto y la URL son obligatorios.';
    } else {
        if ($id) {
            $stmt = $conn->prepare('UPDATE navbar_links SET texto=?, url=?, area=?, abre_nueva_pestana=?, orden=?, activo=? WHERE id=?');
            $stmt->bind_param('sssiiii', $texto, $url, $area, $abreNuevaPestana, $orden, $activo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO navbar_links (texto, url, area, abre_nueva_pestana, orden, activo) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('sssiii', $texto, $url, $area, $abreNuevaPestana, $orden, $activo);
        }
        $stmt->execute();
        $stmt->close();
        header('Location: navbar_links.php');
        exit;
    }
}

$pageTitle = $id ? 'Editar link' : 'Nuevo link';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-6"><label class="form-label">Texto</label><input class="form-control" name="texto" value="<?= htmlspecialchars($link['texto']) ?>" required></div>
  <div class="col-md-6">
    <label class="form-label">URL</label>
    <input class="form-control" name="url" value="<?= htmlspecialchars($link['url']) ?>" placeholder="plataforma/index.php?action=cursos" required>
    <div class="form-text">Relativa a la raíz del sitio. Ejemplos: <code>plataforma/index.php?action=cursos</code>, <code>foro/</code>, <code>reto-arjuna.html</code>, o una URL completa (<code>https://...</code>) para enlaces externos.</div>
  </div>
  <div class="col-md-4">
    <label class="form-label">Dónde aparece</label>
    <select class="form-select" name="area">
      <option value="ambos" <?= $link['area'] === 'ambos' ? 'selected' : '' ?>>Menú y pie de página</option>
      <option value="nav" <?= $link['area'] === 'nav' ? 'selected' : '' ?>>Solo menú</option>
      <option value="footer" <?= $link['area'] === 'footer' ? 'selected' : '' ?>>Solo pie de página</option>
    </select>
  </div>
  <div class="col-md-4"><label class="form-label">Orden</label><input type="number" class="form-control" name="orden" value="<?= (int) $link['orden'] ?>"></div>
  <div class="col-md-4 form-check mt-4">
    <input type="checkbox" class="form-check-input" name="abre_nueva_pestana" id="abre_nueva_pestana" <?= (int) $link['abre_nueva_pestana'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="abre_nueva_pestana">Abrir en pestaña nueva</label>
  </div>
  <div class="col-md-6 form-check">
    <input type="checkbox" class="form-check-input" name="activo" id="activo" <?= (int) $link['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Visible</label>
  </div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
