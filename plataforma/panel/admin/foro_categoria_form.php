<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$categoria = ['nombre' => '', 'slug' => '', 'descripcion' => '', 'orden' => 0];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM foro_categorias WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $categoria = $stmt->get_result()->fetch_assoc() ?: $categoria;
    $stmt->close();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden = (int) ($_POST['orden'] ?? 0);

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $slugBase = strtolower(trim($nombre));
        $slugBase = iconv('UTF-8', 'ASCII//TRANSLIT', $slugBase) ?: $slugBase;
        $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase);
        $slugBase = trim($slugBase, '-') ?: 'categoria';

        $slug = $slugBase;
        $sufijo = 1;
        while (true) {
            $stmt = $conn->prepare('SELECT id FROM foro_categorias WHERE slug = ? AND id <> ?');
            $stmt->bind_param('si', $slug, $id);
            $stmt->execute();
            $existe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$existe) {
                break;
            }
            $sufijo++;
            $slug = $slugBase . '-' . $sufijo;
        }

        if ($id) {
            $stmt = $conn->prepare('UPDATE foro_categorias SET nombre=?, slug=?, descripcion=?, orden=? WHERE id=?');
            $stmt->bind_param('sssii', $nombre, $slug, $descripcion, $orden, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO foro_categorias (nombre, slug, descripcion, orden) VALUES (?,?,?,?)');
            $stmt->bind_param('sssi', $nombre, $slug, $descripcion, $orden);
        }
        $stmt->execute();
        $stmt->close();
        header('Location: foro_categorias.php');
        exit;
    }
}

$pageTitle = $id ? 'Editar categoría' : 'Nueva categoría';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-md-8"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= htmlspecialchars($categoria['nombre']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Orden</label><input type="number" class="form-control" name="orden" value="<?= (int) $categoria['orden'] ?>"></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $categoria['descripcion']) ?></textarea></div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
