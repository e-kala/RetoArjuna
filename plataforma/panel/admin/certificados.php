<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/_filtro_tipo_usuario.php';
require_role('admin');
requerir_csrf_form();

// Eliminar certificado: para corregir emisiones equivocadas (ej. progreso
// reseteado tras "reiniciar cuenta" en usuarios.php, o datos erróneos) sin
// tener que tocar la base de datos a mano. No revoca nada más (el acceso al
// curso/evento no depende de esta tabla) — solo borra el reconocimiento.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_certificado') {
    $certificadoId = (int) ($_POST['certificado_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM certificados WHERE id = ?');
    $stmt->bind_param('i', $certificadoId);
    $stmt->execute();
    $stmt->close();
    if (es_peticion_ajax()) {
        echo json_encode(['success' => true, 'eliminado' => true, 'mensaje' => 'Certificado eliminado.']);
        exit;
    }
    header('Location: certificados.php');
    exit;
}

$tipoFiltro = $_GET['tipo'] ?? 'todos';
if (!in_array($tipoFiltro, ['curso', 'evento', 'todos'], true)) {
    $tipoFiltro = 'todos';
}
$tipoUsuario = pf_tipo_usuario_actual('tipo_usuario');
$busqueda = trim((string) ($_GET['q'] ?? ''));

$condiciones = [];
$parametros = [];
$tipos = '';
if ($tipoFiltro !== 'todos') {
    $condiciones[] = 'cert.tipo = ?';
    $parametros[] = $tipoFiltro;
    $tipos .= 's';
}
$filtroTipoUsuarioSql = pf_filtro_tipo_usuario_sql($tipoUsuario, 'u');
if ($filtroTipoUsuarioSql) {
    $condiciones[] = $filtroTipoUsuarioSql;
}
if ($busqueda !== '') {
    $condiciones[] = '(u.username_cache LIKE ? OR u.email_cache LIKE ? OR c.titulo LIKE ? OR e.titulo LIKE ? OR cert.codigo LIKE ?)';
    $like = '%' . $busqueda . '%';
    array_push($parametros, $like, $like, $like, $like, $like);
    $tipos .= 'sssss';
}
$whereSql = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

$sql = "SELECT cert.id, cert.codigo, cert.tipo, cert.nombre_certificado, cert.fecha_emision,
               u.id AS usuario_id, u.username_cache, u.email_cache,
               COALESCE(c.titulo, e.titulo) AS titulo
        FROM certificados cert
        JOIN usuarios_perfil u ON u.id = cert.usuario_id
        LEFT JOIN cursos c ON c.id = cert.curso_id
        LEFT JOIN eventos e ON e.id = cert.evento_id
        {$whereSql}
        ORDER BY cert.fecha_emision DESC
        LIMIT 300";
$stmt = $conn->prepare($sql);
if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$certificados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalCertificados = (int) $conn->query('SELECT COUNT(*) t FROM certificados')->fetch_assoc()['t'];

$pageTitle = 'Certificados';
include __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Certificados <span class="text-muted small">(<?= $totalCertificados ?> emitidos en total)</span></h1>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto">
    <div class="btn-group btn-group-sm" role="group">
      <a href="?tipo=todos&amp;tipo_usuario=<?= $tipoUsuario ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>" class="btn <?= $tipoFiltro === 'todos' ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
      <a href="?tipo=curso&amp;tipo_usuario=<?= $tipoUsuario ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>" class="btn <?= $tipoFiltro === 'curso' ? 'btn-primary' : 'btn-outline-primary' ?>">Cursos</a>
      <a href="?tipo=evento&amp;tipo_usuario=<?= $tipoUsuario ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>" class="btn <?= $tipoFiltro === 'evento' ? 'btn-primary' : 'btn-outline-primary' ?>">Eventos</a>
    </div>
  </div>
  <div class="col-auto flex-grow-1" style="max-width:320px;">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipoFiltro) ?>">
    <input type="hidden" name="tipo_usuario" value="<?= htmlspecialchars($tipoUsuario) ?>">
    <input type="search" name="q" class="form-control form-control-sm" placeholder="Buscar por usuario, email, título o código…" value="<?= htmlspecialchars($busqueda) ?>">
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Buscar</button>
  </div>
</form>
<?= pf_filtro_tipo_usuario_botones($tipoUsuario, 'certificados.php', 'tipo=' . $tipoFiltro . ($busqueda !== '' ? '&q=' . urlencode($busqueda) : ''), 'tipo_usuario') ?>

<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Tipo</th><th>Curso/Evento</th><th>Nombre en certificado</th><th>Código</th><th>Emitido</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($certificados as $c): ?>
      <tr>
        <td>
          <a href="pagos_usuario.php?usuario_id=<?= (int) $c['usuario_id'] ?>"><?= htmlspecialchars((string) $c['username_cache']) ?></a>
          <br><span class="text-muted small"><?= htmlspecialchars((string) $c['email_cache']) ?></span>
        </td>
        <td><?= $c['tipo'] === 'evento' ? 'Evento' : 'Curso' ?></td>
        <td><?= htmlspecialchars((string) $c['titulo']) ?></td>
        <td><?= htmlspecialchars((string) ($c['nombre_certificado'] ?: $c['username_cache'])) ?></td>
        <td><span class="small text-muted"><?= htmlspecialchars($c['codigo']) ?></span></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['fecha_emision']))) ?></td>
        <td class="d-flex gap-1">
          <a href="../../certificado.php?codigo=<?= urlencode($c['codigo']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>
          <form method="post" data-ajax="eliminar_certificado" data-confirm="¿Eliminar este certificado? Esto no revoca el acceso del usuario al curso/evento, solo borra el reconocimiento.">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar_certificado">
            <input type="hidden" name="certificado_id" value="<?= (int) $c['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$certificados): ?><tr><td colspan="7" class="text-muted">No hay certificados que coincidan.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
