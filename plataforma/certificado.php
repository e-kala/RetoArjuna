<?php
// Verificación pública de certificado: sin sesión, cualquiera con el código puede verlo.
require_once __DIR__ . '/backend/conexion.php';

$codigo = $_GET['codigo'] ?? '';
$stmt = $conn->prepare(
    "SELECT cert.codigo, cert.fecha_emision, cert.tipo, u.username_cache,
            COALESCE(c.titulo, e.titulo) AS titulo
     FROM certificados cert
     LEFT JOIN cursos c ON c.id = cert.curso_id
     LEFT JOIN eventos e ON e.id = cert.evento_id
     JOIN usuarios_perfil u ON u.id = cert.usuario_id
     WHERE cert.codigo = ? LIMIT 1"
);
$stmt->bind_param('s', $codigo);
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Certificado · Reto Arjuna</title>
  <style>
    body { font-family: Georgia, 'Times New Roman', serif; background: #f5f1e4; display: flex; justify-content: center; padding: 60px 20px; }
    .certificado { background: #fff; border: 6px double #f7931e; padding: 50px; max-width: 640px; text-align: center; }
    h1 { color: #f7931e; }
    @media print { body { background: #fff; } }
  </style>
</head>
<body>
  <?php if ($cert): ?>
    <div class="certificado">
      <h1>Certificado de finalización</h1>
      <p>Se certifica que</p>
      <h2><?= htmlspecialchars((string) $cert['username_cache']) ?></h2>
      <p><?= $cert['tipo'] === 'evento' ? 'participó en el evento' : 'completó exitosamente el curso' ?></p>
      <h3><?= htmlspecialchars($cert['titulo']) ?></h3>
      <p>Código: <strong><?= htmlspecialchars($cert['codigo']) ?></strong></p>
      <p>Emitido el <?= htmlspecialchars($cert['fecha_emision']) ?></p>
      <button onclick="window.print()">Imprimir</button>
    </div>
  <?php else: ?>
    <div class="certificado"><p>Certificado no encontrado.</p></div>
  <?php endif; ?>
</body>
</html>
