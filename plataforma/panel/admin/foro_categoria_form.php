<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../foro/backend/foro_helpers.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$categoria = ['nombre' => '', 'slug' => '', 'descripcion' => '', 'orden' => 0, 'parent_id' => null];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM foro_categorias WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $categoria = $stmt->get_result()->fetch_assoc() ?: $categoria;
    $stmt->close();
}

// Opciones válidas para "Categoría padre": todas menos ella misma, sus propios
// descendientes (evita ciclos) y cualquier categoría que ya esté en el nivel
// máximo (2) — esas no pueden tener hijos.
$descendientes = $id ? foro_categoria_descendientes($id) : [];
$opcionesPadre = [];
foreach (foro_categorias_arbol_plano(foro_categorias_arbol()) as $c) {
    if ((int) $c['id'] === $id) {
        continue;
    }
    if (in_array((int) $c['id'], $descendientes, true)) {
        continue;
    }
    if ((int) $c['nivel'] >= 2) {
        continue;
    }
    $opcionesPadre[] = $c;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden = (int) ($_POST['orden'] ?? 0);
    $parentId = (int) ($_POST['parent_id'] ?? 0) ?: null;

    // Revalidar en servidor lo mismo que ya se filtró en las opciones del
    // <select> — un POST armado a mano debe rechazarse igual.
    if ($parentId !== null) {
        if ($parentId === $id) {
            $error = 'Una categoría no puede ser su propio padre.';
        } elseif ($id && in_array($parentId, foro_categoria_descendientes($id), true)) {
            $error = 'No puedes elegir una subcategoría de esta misma categoría como su padre.';
        } else {
            $stmtPadre = $conn->prepare('SELECT id FROM foro_categorias WHERE id = ?');
            $stmtPadre->bind_param('i', $parentId);
            $stmtPadre->execute();
            if (!$stmtPadre->get_result()->fetch_assoc()) {
                $error = 'La categoría padre elegida no existe.';
            } elseif (foro_categoria_nivel($parentId) >= 2) {
                $error = 'Esa categoría ya está en el nivel máximo (no puede tener subcategorías).';
            }
            $stmtPadre->close();
        }
    }

    if ($error === '' && $nombre === '') {
        $error = 'El nombre es obligatorio.';
    }

    if ($error === '') {
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
            $stmt = $conn->prepare('UPDATE foro_categorias SET nombre=?, slug=?, descripcion=?, orden=?, parent_id=? WHERE id=?');
            $stmt->bind_param('sssiii', $nombre, $slug, $descripcion, $orden, $parentId, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO foro_categorias (nombre, slug, descripcion, orden, parent_id) VALUES (?,?,?,?,?)');
            $stmt->bind_param('sssii', $nombre, $slug, $descripcion, $orden, $parentId);
        }
        $stmt->execute();
        $stmt->close();
        header('Location: foro_categorias.php');
        exit;
    }

    // Conserva lo que el admin tecleó si hubo error, en vez de recargar de la DB.
    $categoria = ['nombre' => $nombre, 'slug' => $categoria['slug'], 'descripcion' => $descripcion, 'orden' => $orden, 'parent_id' => $parentId];
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
  <div class="col-12">
    <label class="form-label">Categoría padre (opcional)</label>
    <select class="form-select" name="parent_id">
      <option value="">— Ninguna (categoría de nivel superior) —</option>
      <?php foreach ($opcionesPadre as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) ($categoria['parent_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
          <?= str_repeat('— ', (int) $c['nivel']) ?><?= htmlspecialchars($c['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="form-text">Hasta 3 niveles: categoría → subcategoría → sub-subcategoría.</div>
  </div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars((string) $categoria['descripcion']) ?></textarea></div>
  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>
<?php include __DIR__ . '/_footer.php'; ?>
