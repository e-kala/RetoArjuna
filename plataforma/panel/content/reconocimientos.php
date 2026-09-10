<?php
// "Mis reconocimientos" — pestaña propia (2026-09-08), antes limitada a 3
// dentro de "Mi aprendizaje". Ya como pestaña propia muestra el historial
// completo (certificados de curso + reconocimientos de evento).
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT cert.codigo, cert.fecha_emision, cert.tipo, COALESCE(c.titulo, e.titulo) AS titulo
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
          <a href="../certificado.php?codigo=<?= urlencode($r['codigo']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Ver</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$reconocimientos): ?>
    <p class="text-muted">Todavía no tienes reconocimientos. Completa un curso o asiste a un evento para obtener el primero.</p>
  <?php endif; ?>
</div>
