<?php
// Todas las herramientas de admin relacionadas con el foro, en un solo lugar
// con pestañas — antes vivían repartidas en 3 páginas sueltas
// (foro_moderacion.php, foro_categorias.php, foro_categorias_libres.php).
// El formulario de crear/editar una etiqueta sigue siendo su propia página
// (foro_categoria_form.php) — mismo patrón que usa el resto del admin
// (cursos.php/contenido_form.php, etc.): la página de listado nunca es
// también el formulario, solo enlaza a él.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../foro/backend/foro_helpers.php';
require_role('admin');
requerir_csrf_form();

$tabsValidas = ['moderacion', 'etiquetas', 'categorias_libres'];
$tab = in_array($_GET['tab'] ?? '', $tabsValidas, true) ? $_GET['tab'] : 'moderacion';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $tabRedirect = in_array($_POST['tab'] ?? '', $tabsValidas, true) ? $_POST['tab'] : 'moderacion';
    $etiquetaFiltroRedirect = (int) ($_POST['etiqueta_filtro'] ?? 0);
    $visibilidadFiltroRedirect = in_array($_POST['visibilidad_filtro'] ?? '', ['ocultos', 'visibles'], true) ? $_POST['visibilidad_filtro'] : '';

    switch ($accion) {
        // --- Moderación (antes foro_moderacion.php) ---
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

        case 'ocultar':
        case 'mostrar':
            $stmt = $conn->prepare('UPDATE foro_temas SET oculto = ? WHERE id = ?');
            $valor = $accion === 'ocultar' ? 1 : 0;
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
            // Envía a la papelera (igual que backend/moderar.php) en vez de
            // borrar directo — consistente con el flujo de revisión de 15
            // días, aunque el admin lo dispare desde este panel.
            $miId = (int) $_SESSION['usuario_perfil_id'];
            $stmt = $conn->prepare('UPDATE foro_respuestas SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ? AND eliminado_en IS NULL');
            $stmt->bind_param('ii', $miId, $id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'restaurar_respuesta':
            $stmt = $conn->prepare('UPDATE foro_respuestas SET eliminado_en = NULL, eliminado_por = NULL WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'eliminar_respuesta_definitivo':
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

        // --- Etiquetas (antes foro_categorias.php) ---
        case 'eliminar_categoria':
            $stmt = $conn->prepare('DELETE FROM foro_categorias WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'aprobar_etiqueta':
            $stmt = $conn->prepare('UPDATE foro_categorias SET aprobada = 1 WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            break;

        // --- Categorías libres (antes foro_categorias_libres.php) ---
        case 'eliminar':
            $stmt = $conn->prepare('DELETE FROM foro_categorias_libres WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            break;

        case 'renombrar':
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre !== '' && mb_strlen($nombre) <= 100) {
                $slug = foro_slugify($nombre);
                $slugBase = $slug;
                $sufijo = 1;
                while (true) {
                    $stmt = $conn->prepare('SELECT id FROM foro_categorias_libres WHERE slug = ? AND id <> ? LIMIT 1');
                    $stmt->bind_param('si', $slug, $id);
                    $stmt->execute();
                    if (!$stmt->get_result()->fetch_assoc()) {
                        $stmt->close();
                        break;
                    }
                    $stmt->close();
                    $sufijo++;
                    $slug = $slugBase . '-' . $sufijo;
                }
                $stmt = $conn->prepare('UPDATE foro_categorias_libres SET nombre = ?, slug = ? WHERE id = ?');
                $stmt->bind_param('ssi', $nombre, $slug, $id);
                $stmt->execute();
                $stmt->close();
            }
            break;
    }

    $url = 'foro.php?tab=' . $tabRedirect;
    if ($tabRedirect === 'moderacion' && $etiquetaFiltroRedirect) {
        $url .= '&etiqueta=' . $etiquetaFiltroRedirect;
    }
    if ($tabRedirect === 'moderacion' && $visibilidadFiltroRedirect) {
        $url .= '&visibilidad=' . $visibilidadFiltroRedirect;
    }
    header('Location: ' . $url);
    exit;
}

// Cada pestaña solo consulta lo suyo — no tiene sentido traer las 3 cargas
// de datos si el admin solo está viendo una pestaña.
if ($tab === 'moderacion') {
    // false = también incluye etiquetas pendientes de aprobación en el filtro,
    // para que un admin pueda encontrar temas etiquetados con una que aún no aprobó.
    $categoriasArbol = foro_categorias_arbol_plano(foro_categorias_arbol(current_user(), false));

    $etiquetaFiltro = (int) ($_GET['etiqueta'] ?? 0);
    if ($etiquetaFiltro && !in_array($etiquetaFiltro, array_map('intval', array_column($categoriasArbol, 'id')), true)) {
        $etiquetaFiltro = 0;
    }

    $totalTemas = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas')->fetch_assoc()['t'];
    $totalRespuestas = (int) $conn->query('SELECT COUNT(*) t FROM foro_respuestas')->fetch_assoc()['t'];
    $totalFijados = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas WHERE fijado = 1')->fetch_assoc()['t'];
    $totalCerrados = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas WHERE cerrado = 1')->fetch_assoc()['t'];
    $totalOcultos = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas WHERE oculto = 1')->fetch_assoc()['t'];
    $totalTemasEditados = (int) $conn->query('SELECT COUNT(*) t FROM foro_temas WHERE editado_en IS NOT NULL')->fetch_assoc()['t'];
    $totalRespuestasEditadas = (int) $conn->query('SELECT COUNT(*) t FROM foro_respuestas WHERE editado_en IS NOT NULL')->fetch_assoc()['t'];

    // "Solo ocultos" es el filtro que más importa justo ahora: con todo lo
    // que ya existía oculto de un jalón (ver exportar_produccion.sql), sin
    // esto un admin no tendría forma de encontrar más allá de los 50 temas
    // más recientes para irlos mostrando.
    $ocultoFiltro = in_array($_GET['visibilidad'] ?? '', ['ocultos', 'visibles'], true) ? $_GET['visibilidad'] : '';

    $condicionesTemas = [];
    $tiposTemas = '';
    $paramsTemas = [];
    if ($etiquetaFiltro) {
        $condicionesTemas[] = 'EXISTS (SELECT 1 FROM foro_tema_etiquetas te WHERE te.tema_id = t.id AND te.categoria_id = ?)';
        $tiposTemas .= 'i';
        $paramsTemas[] = $etiquetaFiltro;
    }
    if ($ocultoFiltro === 'ocultos') {
        $condicionesTemas[] = 't.oculto = 1';
    } elseif ($ocultoFiltro === 'visibles') {
        $condicionesTemas[] = 't.oculto = 0';
    }
    $whereFiltro = $condicionesTemas ? ('WHERE ' . implode(' AND ', $condicionesTemas)) : '';

    $etiquetasResumenSql = foro_etiquetas_resumen_sql('t');
    $sqlTemas = "SELECT t.id, t.usuario_id, t.titulo, t.fijado, t.cerrado, t.oculto, t.respuestas_count, t.vistas, t.editado_en, t.created_at,
                        $etiquetasResumenSql AS etiquetas_nombres, u.username_cache
                 FROM foro_temas t
                 JOIN usuarios_perfil u ON u.id = t.usuario_id
                 $whereFiltro
                 ORDER BY t.created_at DESC LIMIT 50";
    $stmt = $conn->prepare($sqlTemas);
    if ($paramsTemas) {
        $stmt->bind_param($tiposTemas, ...$paramsTemas);
    }
    $stmt->execute();
    $temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT r.id, r.usuario_id, r.tema_id, r.contenido, r.editado_en, r.created_at, t.titulo AS tema_titulo, u.username_cache
         FROM foro_respuestas r
         JOIN foro_temas t ON t.id = r.tema_id
         JOIN usuarios_perfil u ON u.id = r.usuario_id
         WHERE r.eliminado_en IS NULL
         ORDER BY r.created_at DESC LIMIT 50"
    );
    $stmt->execute();
    $respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->query(
        "SELECT r.id, r.usuario_id, r.eliminado_por, r.tema_id, r.contenido, r.eliminado_en, t.titulo AS tema_titulo,
                autor.username_cache AS autor_username, elim.username_cache AS eliminado_por_username,
                GREATEST(0, 15 - DATEDIFF(NOW(), r.eliminado_en)) AS dias_restantes
         FROM foro_respuestas r
         JOIN foro_temas t ON t.id = r.tema_id
         JOIN usuarios_perfil autor ON autor.id = r.usuario_id
         LEFT JOIN usuarios_perfil elim ON elim.id = r.eliminado_por
         WHERE r.eliminado_en IS NOT NULL
         ORDER BY r.eliminado_en ASC"
    );
    $papelera = $stmt->fetch_all(MYSQLI_ASSOC);
} elseif ($tab === 'etiquetas') {
    // false = incluye también las pendientes de aprobación (propuestas por
    // usuarios al crear un tema — ver foro_resolver_o_crear_etiqueta()), para
    // poder aprobarlas o rechazarlas (rechazar = usar el mismo botón "Eliminar").
    $categorias = foro_categorias_arbol_plano(foro_categorias_arbol(null, false));
} elseif ($tab === 'categorias_libres') {
    $categoriasLibres = foro_categorias_libres_todas();
}

$pageTitle = 'Foro';
include __DIR__ . '/_header.php';
?>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link <?= $tab === 'moderacion' ? 'active' : '' ?>" href="?tab=moderacion"><i class="bi bi-shield-check"></i> Moderación</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab === 'etiquetas' ? 'active' : '' ?>" href="?tab=etiquetas"><i class="bi bi-chat-square-text"></i> Etiquetas</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab === 'categorias_libres' ? 'active' : '' ?>" href="?tab=categorias_libres"><i class="bi bi-bookmark-fill"></i> Categorías libres</a></li>
</ul>

<?php if ($tab === 'moderacion'): ?>

<div class="row g-3 mb-4">
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Temas</div><div class="h4"><?= $totalTemas ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Respuestas</div><div class="h4"><?= $totalRespuestas ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Fijados</div><div class="h4"><?= $totalFijados ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Cerrados</div><div class="h4"><?= $totalCerrados ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Ocultos</div><div class="h4"><?= $totalOcultos ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Temas editados</div><div class="h4"><?= $totalTemasEditados ?></div></div></div>
  <div class="col-md-2"><div class="card p-3"><div class="text-muted small">Respuestas editadas</div><div class="h4"><?= $totalRespuestasEditadas ?></div></div></div>
</div>

<div class="card p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Temas recientes</h2>
    <form method="get" class="d-flex align-items-center gap-2">
      <input type="hidden" name="tab" value="moderacion">
      <label class="small text-muted mb-0">Visibilidad</label>
      <select name="visibilidad" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
        <option value="">Todos</option>
        <option value="ocultos" <?= $ocultoFiltro === 'ocultos' ? 'selected' : '' ?>>Solo ocultos</option>
        <option value="visibles" <?= $ocultoFiltro === 'visibles' ? 'selected' : '' ?>>Solo visibles</option>
      </select>
      <label class="small text-muted mb-0">Etiqueta</label>
      <select name="etiqueta" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
        <option value="">Todas</option>
        <?php foreach ($categoriasArbol as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $etiquetaFiltro === (int) $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['nombre']) ?><?= (int) $c['aprobada'] !== 1 ? ' (pendiente)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-responsive">
  <table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Tema</th><th>Etiquetas</th><th>Autor</th><th>Estado</th><th>Respuestas</th><th>Vistas</th><th>Fecha</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($temas as $t): ?>
        <tr>
          <td>
            <a href="../../foro/tema.php?id=<?= (int) $t['id'] ?>" target="_blank"><?= htmlspecialchars($t['titulo']) ?></a>
            <?php if ($t['editado_en']): ?><span class="badge bg-light text-muted border">editado</span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars((string) $t['etiquetas_nombres']) ?></td>
          <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $t['usuario_id'] ?>" target="_blank"><?= htmlspecialchars($t['username_cache']) ?></a></td>
          <td>
            <?php if ($t['fijado']): ?><span class="badge bg-warning text-dark">Fijado</span><?php endif; ?>
            <?php if ($t['cerrado']): ?><span class="badge bg-secondary">Cerrado</span><?php endif; ?>
            <?php if ($t['oculto']): ?><span class="badge bg-dark">Oculto</span><?php endif; ?>
          </td>
          <td><?= (int) $t['respuestas_count'] ?></td>
          <td><?= (int) $t['vistas'] ?></td>
          <td><?= htmlspecialchars($t['created_at']) ?></td>
          <td class="d-flex flex-wrap gap-1">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="moderacion">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="etiqueta_filtro" value="<?= $etiquetaFiltro ?>">
              <input type="hidden" name="visibilidad_filtro" value="<?= htmlspecialchars($ocultoFiltro) ?>">
              <input type="hidden" name="accion" value="<?= $t['fijado'] ? 'desfijar' : 'fijar' ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $t['fijado'] ? 'Desfijar' : 'Fijar' ?></button>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="moderacion">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="etiqueta_filtro" value="<?= $etiquetaFiltro ?>">
              <input type="hidden" name="visibilidad_filtro" value="<?= htmlspecialchars($ocultoFiltro) ?>">
              <input type="hidden" name="accion" value="<?= $t['cerrado'] ? 'reabrir' : 'cerrar' ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $t['cerrado'] ? 'Reabrir' : 'Cerrar' ?></button>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="moderacion">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="etiqueta_filtro" value="<?= $etiquetaFiltro ?>">
              <input type="hidden" name="visibilidad_filtro" value="<?= htmlspecialchars($ocultoFiltro) ?>">
              <input type="hidden" name="accion" value="<?= $t['oculto'] ? 'mostrar' : 'ocultar' ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $t['oculto'] ? 'Mostrar' : 'Ocultar' ?></button>
            </form>
            <form method="post" onsubmit="return confirm('¿Eliminar este tema y todas sus respuestas? No se puede deshacer.');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="moderacion">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="etiqueta_filtro" value="<?= $etiquetaFiltro ?>">
              <input type="hidden" name="visibilidad_filtro" value="<?= htmlspecialchars($ocultoFiltro) ?>">
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

<div class="card p-3 mb-4">
  <h2 class="h5 mb-3">Respuestas recientes</h2>
  <div class="table-responsive">
  <table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Tema</th><th>Contenido</th><th>Autor</th><th>Fecha</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($respuestas as $r): ?>
        <tr>
          <td><a href="../../foro/tema.php?id=<?= (int) $r['tema_id'] ?>" target="_blank"><?= htmlspecialchars($r['tema_titulo']) ?></a></td>
          <td>
            <?= htmlspecialchars(mb_substr(trim(strip_tags($r['contenido'])), 0, 120)) ?><?= mb_strlen(trim(strip_tags($r['contenido']))) > 120 ? '…' : '' ?>
            <?php if ($r['editado_en']): ?><span class="badge bg-light text-muted border">editado</span><?php endif; ?>
          </td>
          <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $r['usuario_id'] ?>" target="_blank"><?= htmlspecialchars($r['username_cache']) ?></a></td>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('¿Enviar esta respuesta a la papelera?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="moderacion">
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

<div class="card p-3 mb-4">
  <h2 class="h5 mb-3">Papelera de respuestas <span class="badge bg-secondary"><?= count($papelera) ?></span></h2>
  <p class="text-muted small">Respuestas que su propio autor envió a la papelera. Si nadie las revisa en 15 días, se borran solas.</p>
  <div class="table-responsive">
  <table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Tema</th><th>Contenido</th><th>Autor</th><th>Enviada a papelera por</th><th>Fecha</th><th>Días restantes</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($papelera as $r): ?>
        <tr>
          <td><a href="../../foro/tema.php?id=<?= (int) $r['tema_id'] ?>" target="_blank"><?= htmlspecialchars($r['tema_titulo']) ?></a></td>
          <td><?= htmlspecialchars(mb_substr(trim(strip_tags($r['contenido'])), 0, 120)) ?><?= mb_strlen(trim(strip_tags($r['contenido']))) > 120 ? '…' : '' ?></td>
          <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $r['usuario_id'] ?>" target="_blank"><?= htmlspecialchars($r['autor_username']) ?></a></td>
          <td><?php if ($r['eliminado_por']): ?><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $r['eliminado_por'] ?>" target="_blank"><?= htmlspecialchars((string) $r['eliminado_por_username']) ?></a><?php else: ?>—<?php endif; ?></td>
          <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['eliminado_en']))) ?></td>
          <td><?= (int) $r['dias_restantes'] ?></td>
          <td class="d-flex flex-wrap gap-1">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="moderacion">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="accion" value="restaurar_respuesta">
              <button class="btn btn-sm btn-outline-secondary">Restaurar</button>
            </form>
            <form method="post" onsubmit="return confirm('¿Eliminar esta respuesta definitivamente? No se puede deshacer.');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="moderacion">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="accion" value="eliminar_respuesta_definitivo">
              <button class="btn btn-sm btn-outline-danger">Eliminar definitivamente</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$papelera): ?><tr><td colspan="7" class="text-muted">La papelera está vacía.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php elseif ($tab === 'etiquetas'): ?>

<div class="d-flex justify-content-between mb-3">
  <p class="text-muted small mb-0" style="max-width:640px;">
    Vocabulario de etiquetas que los usuarios pueden ponerle a sus temas (relación N:M —
    un tema puede llevar varias). Las marcadas "pendiente" las propuso un usuario al crear
    un tema y esperan tu aprobación antes de aparecer para los demás.
  </p>
  <a href="foro_categoria_form.php" class="btn btn-success btn-sm text-nowrap">+ Nueva etiqueta</a>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Nombre</th><th>Slug</th><th>Orden</th><th>Temas</th><th>Creada</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($categorias as $c): ?>
      <tr>
        <td>
          <?= str_repeat('— ', (int) $c['nivel']) ?><a href="../../foro/etiqueta.php?slug=<?= urlencode($c['slug']) ?>" target="_blank"><?= htmlspecialchars($c['nombre']) ?></a>
          <?php if ((int) $c['aprobada'] !== 1): ?>
            <span class="badge bg-warning text-dark">pendiente</span>
            <?php if ($c['creado_por_username'] ?? null): ?><span class="text-muted small">— propuesta por <?= htmlspecialchars($c['creado_por_username']) ?></span><?php endif; ?>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($c['slug']) ?></td>
        <td><?= (int) $c['orden'] ?></td>
        <td><?= (int) $c['temas_count'] ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['created_at']))) ?></td>
        <td class="d-flex gap-2 flex-wrap">
          <?php if ((int) $c['aprobada'] !== 1): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="etiquetas">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <input type="hidden" name="accion" value="aprobar_etiqueta">
              <button class="btn btn-sm btn-outline-success">Aprobar</button>
            </form>
          <?php endif; ?>
          <a href="foro_categoria_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" onsubmit="return confirm('¿Eliminar esta etiqueta? Ya no borra los temas que la tenían — solo se les quita la etiqueta. Si tiene subcategorías, también se eliminan ellas.');">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="etiquetas">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="eliminar_categoria">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categorias): ?><tr><td colspan="6" class="text-muted">No hay categorías creadas.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<?php elseif ($tab === 'categorias_libres'): ?>

<p class="text-muted small" style="max-width:640px;">
  Tercera taxonomía de un tema, aparte de curso/evento y de las etiquetas de arriba.
  Cualquier usuario crea una nueva al publicar, sin necesitar tu aprobación — usa esta
  pestaña solo para renombrar o eliminar duplicados/spam.
</p>
<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead><tr><th>Nombre</th><th>Slug</th><th>Temas</th><th>Creada</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($categoriasLibres as $c): ?>
      <tr>
        <td>
          <form method="post" class="d-flex gap-2 align-items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="categorias_libres">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="renombrar">
            <a href="../../foro/categoria.php?slug=<?= urlencode($c['slug']) ?>" target="_blank" title="Ver categoría" class="text-muted"><i class="bi bi-box-arrow-up-right"></i></a>
            <input type="text" name="nombre" value="<?= htmlspecialchars($c['nombre']) ?>" class="form-control form-control-sm" maxlength="100">
            <button class="btn btn-sm btn-outline-secondary">Guardar</button>
          </form>
        </td>
        <td><?= htmlspecialchars($c['slug']) ?></td>
        <td><?= (int) $c['temas_count'] ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['created_at']))) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('¿Eliminar esta categoría? Los temas que la tenían se quedan sin categoría, no se borran.');">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="categorias_libres">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="accion" value="eliminar">
            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categoriasLibres): ?><tr><td colspan="5" class="text-muted">Todavía no hay categorías libres creadas.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
