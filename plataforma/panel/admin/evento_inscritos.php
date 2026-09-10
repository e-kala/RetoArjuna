<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/certificados.php';
require_role('admin');
requerir_csrf_form();

$eventoId = (int) ($_GET['evento_id'] ?? $_POST['evento_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM eventos WHERE id = ?');
$stmt->bind_param('i', $eventoId);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$evento) {
    header('Location: eventos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_asistencia') {
    $esAjax = es_peticion_ajax();
    $inscripcionId = (int) $_POST['inscripcion_id'];
    $stmt = $conn->prepare("UPDATE evento_inscripciones SET estado = 'asistio' WHERE id = ? AND evento_id = ?");
    $stmt->bind_param('ii', $inscripcionId, $eventoId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('SELECT usuario_id FROM evento_inscripciones WHERE id = ?');
    $stmt->bind_param('i', $inscripcionId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($fila) {
        verificar_y_emitir_reconocimiento_evento((int) $fila['usuario_id'], $eventoId);
    }

    if ($esAjax) {
        echo json_encode([
            'success' => true,
            'mensaje' => 'Asistencia marcada.',
            'estado_html' => 'asistio',
            'quitar_grupo' => true,
        ]);
        exit;
    }

    header('Location: evento_inscritos.php?evento_id=' . $eventoId);
    exit;
}

$inscritos = $conn->query(
    "SELECT ei.id, ei.usuario_id, ei.estado, ei.created_at, u.username_cache, u.email_cache,
            cert.codigo AS codigo_reconocimiento
     FROM evento_inscripciones ei
     JOIN usuarios_perfil u ON u.id = ei.usuario_id
     LEFT JOIN certificados cert ON cert.usuario_id = ei.usuario_id AND cert.evento_id = ei.evento_id
     WHERE ei.evento_id = " . (int) $eventoId . "
     ORDER BY ei.created_at"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Inscritos · ' . $evento['titulo'];
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Inscritos a "<?= htmlspecialchars($evento['titulo']) ?>"</h1>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th>Usuario</th><th>Correo</th><th>Inscrito</th><th>Estado</th><th>Reconocimiento</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php foreach ($inscritos as $i): ?>
      <tr>
        <td><a href="../../index.php?action=perfil_publico&usuario=<?= (int) $i['usuario_id'] ?>" target="_blank"><?= htmlspecialchars((string) $i['username_cache']) ?></a></td>
        <td><?= htmlspecialchars((string) $i['email_cache']) ?></td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime($i['created_at']))) ?></td>
        <td data-ajax-estado><?= htmlspecialchars($i['estado']) ?></td>
        <td>
          <?php if ($i['codigo_reconocimiento']): ?>
            <a href="../../certificado.php?codigo=<?= urlencode($i['codigo_reconocimiento']) ?>" target="_blank">Ver</a>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($i['estado'] !== 'asistio'): ?>
            <form method="post" data-ajax="accion">
              <?= csrf_field() ?>
              <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
              <input type="hidden" name="inscripcion_id" value="<?= (int) $i['id'] ?>">
              <input type="hidden" name="accion" value="marcar_asistencia">
              <button class="btn btn-sm btn-success">Marcar asistencia</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$inscritos): ?>
      <tr><td colspan="6" class="text-muted">Aún no hay inscritos.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
