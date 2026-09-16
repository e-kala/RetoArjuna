<?php
// Verificación pública de certificado: sin sesión, cualquiera con el código puede verlo.
require_once __DIR__ . '/backend/conexion.php';

$codigo = $_GET['codigo'] ?? '';
$stmt = $conn->prepare(
    "SELECT cert.codigo, cert.fecha_emision, cert.tipo, cert.nombre_certificado, u.username_cache,
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

$nombreMostrado = $cert ? trim((string) ($cert['nombre_certificado'] ?: $cert['username_cache'])) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificado · Reto Arjuna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <style>
    body { background: #f5f1e4; font-family: 'Segoe UI', Arial, sans-serif; }
    .pf-cert-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
    .pf-cert { background: #fff; border: 10px double #f7931e; border-radius: 4px; padding: 56px 48px; max-width: 680px; width: 100%; text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
    .pf-cert h1 { font-family: 'Playfair Display', Georgia, serif; color: #171717; font-size: 1.6rem; letter-spacing: .04em; text-transform: uppercase; margin-bottom: 4px; }
    .pf-cert .pf-cert-sub { color: #f7931e; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; font-size: .75rem; }
    .pf-cert .pf-cert-nombre { font-family: 'Playfair Display', Georgia, serif; font-size: 2.2rem; color: #171717; margin: 28px 0 8px; }
    .pf-cert .pf-cert-titulo { font-family: 'Playfair Display', Georgia, serif; font-size: 1.35rem; color: #171717; margin: 10px 0 24px; }
    .pf-cert .pf-cert-meta { font-size: .85rem; color: #6c757d; }
    .pf-cert-codigo { letter-spacing: .05em; }
    @media print {
      body { background: #fff; }
      .pf-no-print { display: none !important; }
      .pf-cert { box-shadow: none; border-width: 6px; }
    }
  </style>
</head>
<body>
  <div class="pf-cert-wrap">
    <?php if ($cert): ?>
      <div class="pf-cert">
        <p class="pf-cert-sub">Camino Arjuna</p>
        <h1>Certificado de <?= $cert['tipo'] === 'evento' ? 'participación' : 'finalización' ?></h1>
        <p class="mt-4 mb-0 text-muted">Se certifica que</p>
        <p class="pf-cert-nombre"><?= htmlspecialchars($nombreMostrado) ?></p>
        <p class="mb-0 text-muted"><?= $cert['tipo'] === 'evento' ? 'participó en el evento' : 'completó exitosamente el curso' ?></p>
        <p class="pf-cert-titulo"><?= htmlspecialchars($cert['titulo']) ?></p>
        <p class="pf-cert-meta mb-1">Emitido el <?= htmlspecialchars(date('d/m/Y', strtotime((string) $cert['fecha_emision']))) ?></p>
        <p class="pf-cert-meta pf-cert-codigo">Código de verificación: <strong><?= htmlspecialchars($cert['codigo']) ?></strong></p>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm mt-4 pf-no-print"><i class="bi bi-printer"></i> Imprimir</button>
      </div>
    <?php else: ?>
      <div class="pf-cert">
        <p class="text-muted mb-0">Certificado no encontrado.</p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
