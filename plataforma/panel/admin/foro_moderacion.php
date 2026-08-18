<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../../foro/backend/foro_helpers.php';
require_role('admin');
requerir_csrf_form();

$categoriasArbol = foro_categorias_arbol_plano(foro_categorias_arbol());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $categoriaFiltroRedirect = (int) ($_POST['categoria_filtro'] ?? 0);

    switch ($accion) {
        case 'fijar':
        case 'desfijar':
            $stmt = $conn->prepare('UPDATE foro_temas SET fijado = ? WHERE id = ?');
            $valor = $accion === 'fijar' ? 1 : 0;
            $stmt->bind_param('ii', $valor, $id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'cerrar':
        case 'reabrir':
            $stmt = $conn->prepare('UPDATE foro_temas SET cerrado = ? WHERE id = ?');
            $valor = $accion === 'cerrar' ? 1 : 0;
            $stmt->bind_param('ii', $valor, $id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'eliminar_tema':
            $stmt = $conn->prepare('DELETE FROM foro_temas WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'eliminar_respuesta':
            $stmt = $conn->prepare('SELECT tema_id FROM foro_respuestas WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $respuesta = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($respuesta) {
                $stmt = $conn->prepare('DELETE FROM foro_respuestas WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare('UPDATE foro_temas SET respuestas_count = GREATEST(0, respuestas_count - 1) WHERE id = ?');
                $stmt->bind_param('i', $respuesta['tema_id']);
                $stmt->execute();
                $stmt->close();
            }
            break;
    }
    header('Location: foro_moderacion.php' . ($categoriaFiltroRedirect ? '?categoria=' . $categoriaFiltroRedirect : ''));
    exit;
}

$categoriaFiltro = (int) ($_GET['categoria'] ?? 0);
if ($categoriaFiltro && !in_array($categoriaFiltro, array_map('intval', array_column($categoriasArbol, 'id')), true)) {
    $categoriaFiltro = 0;
}

$totalTemas = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas')->fetch_assoc()['t'];
$totalRespuestas = (int) $conn->query('SELECT COUNT(*) t FROM foro_respuestas')->fetch_assoc()['t'];
$totalFijados = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas WHERE fijado = 1')->fetch_assoc()['t'];
$totalCerrados = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas WHERE cerrado = 1')->fetch_assoc()['t'];
$totalTemasEditados = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas WHERE editado_en IS NOT NULL')->fetch_assoc()['t'];
$totalRespuestasEditadas = (int) $conn->query('SELECT COUNT(*) t FROM foro_respuestas WHERE editado_en IS NOT NULL')->fetch_assoc()['t'];

$whereFiltro = $categoriaFiltro ? 'WHERE t.categoria_id = ?' : '';
$sqlTemas = "SELECT t.id, t.titulo, t.fijado, t.cerrado, t.respuestas_count, t.vistas, t.editado_en, t.created_at,
                    c.nombre AS categoria_nombre, u.username_cache
             FROM foro_temas t
             JOIN foro_categorias c ON c.id = t.categoria_id
             JOIN usuarios_perfil u ON u.id = t.usuario_id
             $whereFiltro
             ORDER BY t.created_at DESC LIMIT 50";
$stmt = $conn->prepare($sqlTemas);
if ($categoriaFiltro) {
    $stmt->bind_param('i', $categoriaFiltro);
}
$stmt->execute();
$temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare(
    "SELECT r.id, r.tema_id, r.contenido, r.editado_en, r.created_at, t.titulo AS tema_titulo, u.username_cache
     FROM foro_respuestas r
     JOIN foro_temas t ON t.id = r.tema_id
     JOIN usuarios_perfil u ON u.id = r.usuario_id
     ORDER BY r.created_at DESC LIMIT 50"
);
$stmt->execute();
$respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Moderación del foro';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Moderación del foro</h1>

<div class="row g-3 mb-4">
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Temas</div><div class="h4"><?= $totalTemas ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Respuestas</div><div class="h4"><?= $totalRespuestas ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Fijados</div><div class="h4"><?= $totalFijados ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Cerrados</div><div class="h4"><?= $totalCerrados ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Temas editados</div><div class="h4"><?= $totalTemasEditados ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Respuestas editadas</div><div class="h4"><?= $totalRespuestasEditadas ?></div></div></div>
</div>

<div class="card p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Temas recientes</h2>
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="small text-muted mb-0">Categoría</label>
      <select name="categoria" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
        <option value="">Todas</option>
        <?php foreach ($categoriasArbol as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $categoriaFiltro === (int) $c['id'] ? 'selected' : '' ?>>
            <?= str_repeat('— ', (int) $c['nivel']) ?><?= htmlspecialchars($c['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-responsive">
  <table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Tema</th><th>Categoría</th><th>Autor</th><th>Estado</th><th>Respuestas</th><th>Vistas</th><th>Fecha</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($temas as $t): ?>
        <tr>
          <td>
            <a href="../../../foro/tema.php?id=<?= (int) $t['id'] ?>" target="_blank"><?= htmlspecialchars($t['titulo']) ?></a>
            <?php if ($t['editado_en']): ?><span class="badge bg-light text-muted border">editado</span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($t['categoria_nombre']) ?></td>
          <td><?= htmlspecialchars($t['username_cache']) ?></td>
          <td>
            <?php if ($t['fijado']): ?><span class="badge bg-warning text-dark">Fijado</span><?php endif; ?>
            <?php if ($t['cerrado']): ?><span class="badge bg-secondary">Cerrado</span><?php endif; ?>
          </td>
          <td><?= (int) $t['respuestas_count'] ?></td>
          <td><?= (int) $t['vistas'] ?></td>
          <td><?= htmlspecialchars($t['created_at']) ?></td>
          <td class="d-flex flex-wrap gap-1">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="categoria_filtro" value="<?= $categoriaFiltro ?>">
              <input type="hidden" name="accion" value="<?= $t['fijado'] ? 'desfijar' : 'fijar' ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $t['fijado'] ? 'Desfijar' : 'Fijar' ?></button>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="categoria_filtro" value="<?= $categoriaFiltro ?>">
              <input type="hidden" name="accion" value="<?= $t['cerrado'] ? 'reabrir' : 'cerrar' ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $t['cerrado'] ? 'Reabrir' : 'Cerrar' ?></button>
            </form>
            <form method="post" onsubmit="return confirm('¿Eliminar este tema y todas sus respuestas? No se puede deshacer.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="categoria_filtro" value="<?= $categoriaFiltro ?>">
              <input type="hidden" name="accion" value="eliminar_tema">
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$temas): ?><tr><td colspan="8" class="text-muted">No hay temas.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="card p-3">
  <h2 class="h5 mb-3">Respuestas recientes</h2>
  <div class="table-responsive">
  <table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Tema</th><th>Contenido</th><th>Autor</th><th>Fecha</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($respuestas as $r): ?>
        <tr>
          <td><a href="../../../foro/tema.php?id=<?= (int) $r['tema_id'] ?>" target="_blank"><?= htmlspecialchars($r['tema_titulo']) ?></a></td>
          <td>
            <?= htmlspecialchars(mb_substr(trim(strip_tags($r['contenido'])), 0, 120)) ?><?= mb_strlen(trim(strip_tags($r['contenido']))) > 120 ? '…' : '' ?>
            <?php if ($r['editado_en']): ?><span class="badge bg-light text-muted border">editado</span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($r['username_cache']) ?></td>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('¿Eliminar esta respuesta? No se puede deshacer.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="accion" value="eliminar_respuesta">
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$respuestas): ?><tr><td colspan="5" class="text-muted">No hay respuestas.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
