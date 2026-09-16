<?php
// "Mis reconocimientos" — pestaña propia (2026-09-08), antes limitada a 3
// dentro de "Mi aprendizaje". Ya como pestaña propia muestra el historial
// completo (certificados de curso + reconocimientos de evento).
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

// Guardar nombre personalizado (el que se imprime en el certificado, en vez
// del username) — se procesa aquí porque este archivo se incluye tanto en
// la carga completa como en el fragmento Ajax; dashboard.php ya resolvió el
// único caso que necesita header() antes de tiempo (eliminar_compra).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_nombre_certificado') {
    requerir_csrf_form();
    $certificadoId = (int) ($_POST['certificado_id'] ?? 0);
    $nombrePersonalizado = trim((string) ($_POST['nombre_certificado'] ?? ''));
    if ($nombrePersonalizado === '') {
        $nombrePersonalizado = null;
    }
    $stmt = $conn->prepare('UPDATE certificados SET nombre_certificado = ? WHERE id = ? AND usuario_id = ?');
    $stmt->bind_param('sii', $nombrePersonalizado, $certificadoId, $usuarioPerfilId);
    $stmt->execute();
    $stmt->close();
    if (es_peticion_ajax()) {
        echo json_encode(['success' => true, 'mensaje' => 'Nombre guardado.']);
        exit;
    }
}

$stmt = $conn->prepare(
    "SELECT cert.id, cert.codigo, cert.fecha_emision, cert.tipo, cert.nombre_certificado,
            COALESCE(c.titulo, e.titulo) AS titulo
     FROM certificados cert
     LEFT JOIN cursos c ON c.id = cert.curso_id
     LEFT JOIN eventos e ON e.id = cert.evento_id
     WHERE cert.usuario_id = ? ORDER BY cert.fecha_emision DESC"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$reconocimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<h1 class="h3 fw-bold mb-4">Mis reconocimientos</h1>
<div class="row row-cols-1 row-cols-md-3 g-4">
  <?php foreach ($reconocimientos as $r): ?>
    <div class="col">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <h3 class="h6 fw-bold"><?= htmlspecialchars($r['titulo']) ?></h3>
          <p class="small text-muted mb-2"><?= $r['tipo'] === 'evento' ? 'Evento' : 'Curso' ?> · <?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha_emision']))) ?></p>
          <form method="post" class="d-flex gap-1 mb-2" data-ajax="guardar_nombre_certificado">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar_nombre_certificado">
            <input type="hidden" name="certificado_id" value="<?= (int) $r['id'] ?>">
            <input type="text" name="nombre_certificado" class="form-control form-control-sm" placeholder="Nombre en el certificado"
                   value="<?= htmlspecialchars((string) ($r['nombre_certificado'] ?? '')) ?>" maxlength="255">
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Guardar nombre"><i class="bi bi-check-lg"></i></button>
          </form>
          <a href="../certificado.php?codigo=<?= urlencode($r['codigo']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Ver</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$reconocimientos): ?>
    <p class="text-muted">Todavía no tienes reconocimientos. Completa un curso o asiste a un evento para obtener el primero.</p>
  <?php endif; ?>
</div>
