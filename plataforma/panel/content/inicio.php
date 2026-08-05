<?php
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT c.id, c.titulo, c.slug,
            (SELECT COUNT(*) FROM lecciones WHERE curso_id = c.id) AS total_lecciones,
            (SELECT COUNT(*) FROM progreso WHERE curso_id = c.id AND usuario_id = ? AND completado = 1) AS completadas,
            cert.codigo AS codigo_certificado
     FROM cursos c
     LEFT JOIN pagos p ON p.curso_id = c.id AND p.usuario_id = ? AND p.estado = 'confirmado'
     LEFT JOIN certificados cert ON cert.curso_id = c.id AND cert.usuario_id = ?
     WHERE c.activo = 1 AND (c.gratuito = 1 OR p.id IS NOT NULL)
     GROUP BY c.id"
);
$stmt->bind_param('iii', $usuarioPerfilId, $usuarioPerfilId, $usuarioPerfilId);
$stmt->execute();
$misCursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare(
    "SELECT e.titulo, e.slug, e.tipo, e.ubicacion, e.fecha_inicio, ei.estado,
            cert.codigo AS codigo_reconocimiento
     FROM evento_inscripciones ei
     JOIN eventos e ON e.id = ei.evento_id
     LEFT JOIN certificados cert ON cert.evento_id = e.id AND cert.usuario_id = ei.usuario_id
     WHERE ei.usuario_id = ? AND ei.estado <> 'cancelado'
     ORDER BY e.fecha_inicio DESC"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$misEventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$misReconocimientos = $conn->prepare(
    "SELECT cert.codigo, cert.fecha_emision, cert.tipo, COALESCE(c.titulo, e.titulo) AS titulo
     FROM certificados cert
     LEFT JOIN cursos c ON c.id = cert.curso_id
     LEFT JOIN eventos e ON e.id = cert.evento_id
     WHERE cert.usuario_id = ? ORDER BY cert.fecha_emision DESC"
);
$misReconocimientos->bind_param('i', $usuarioPerfilId);
$misReconocimientos->execute();
$reconocimientos = $misReconocimientos->get_result()->fetch_all(MYSQLI_ASSOC);
$misReconocimientos->close();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Mis cursos</h1>
</div>
<div class="row row-cols-1 row-cols-md-3 g-4">
    <?php foreach ($misCursos as $curso): ?>
        <?php $porcentaje = $curso['total_lecciones'] > 0 ? (int) round($curso['completadas'] / $curso['total_lecciones'] * 100) : 0; ?>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($curso['titulo']) ?></h5>
                    <div class="progress mb-2" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: <?= $porcentaje ?>%"></div>
                    </div>
                    <p class="text-muted small"><?= $porcentaje ?>% completado</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?php if ($curso['codigo_certificado']): ?>
                            <a href="../certificado.php?codigo=<?= urlencode($curso['codigo_certificado']) ?>" class="btn btn-outline-success btn-sm">Certificado</a>
                        <?php endif; ?>
                        <a href="../index.php?action=curso&slug=<?= urlencode($curso['slug']) ?>" class="btn btn-success btn-sm">Continuar</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$misCursos): ?>
        <p class="text-muted">Aún no tienes cursos. <a href="../index.php?action=cursos">Explora el catálogo</a>.</p>
    <?php endif; ?>
</div>

<div id="mis-eventos" class="d-sm-flex align-items-center justify-content-between mb-4 mt-5">
    <h1 class="h3 mb-0 text-gray-800">Mis eventos</h1>
    <a href="../index.php?action=eventos" class="btn btn-outline-primary btn-sm">Ver próximos eventos</a>
</div>
<div class="row row-cols-1 row-cols-md-3 g-4">
    <?php foreach ($misEventos as $ev): ?>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($ev['titulo']) ?></h5>
                    <p class="text-muted small">
                        <?= $ev['tipo'] === 'online' ? '💻 En línea' : '📍 ' . htmlspecialchars((string) $ev['ubicacion']) ?>
                        · <?= htmlspecialchars(date('d/m/Y', strtotime($ev['fecha_inicio']))) ?>
                    </p>
                    <span class="badge <?= $ev['estado'] === 'asistio' ? 'bg-success' : 'bg-info' ?>">
                        <?= $ev['estado'] === 'asistio' ? 'Asististe' : 'Inscrito' ?>
                    </span>
                    <div class="mt-2">
                        <?php if ($ev['codigo_reconocimiento']): ?>
                            <a href="../certificado.php?codigo=<?= urlencode($ev['codigo_reconocimiento']) ?>" class="btn btn-outline-success btn-sm">Reconocimiento</a>
                        <?php endif; ?>
                        <a href="../index.php?action=evento&slug=<?= urlencode($ev['slug']) ?>" class="btn btn-success btn-sm">Ver evento</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$misEventos): ?>
        <p class="text-muted">Aún no te has inscrito a ningún evento. <a href="../index.php?action=eventos">Explora los próximos eventos</a>.</p>
    <?php endif; ?>
</div>

<div id="mis-reconocimientos" class="d-sm-flex align-items-center justify-content-between mb-4 mt-5">
    <h1 class="h3 mb-0 text-gray-800">Mis reconocimientos</h1>
</div>
<div class="row row-cols-1 row-cols-md-3 g-4">
    <?php foreach ($reconocimientos as $r): ?>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($r['titulo']) ?></h5>
                    <p class="text-muted small"><?= $r['tipo'] === 'evento' ? 'Evento' : 'Curso' ?> · <?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha_emision']))) ?></p>
                    <a href="../certificado.php?codigo=<?= urlencode($r['codigo']) ?>" class="btn btn-outline-success btn-sm" target="_blank">Ver</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$reconocimientos): ?>
        <p class="text-muted">Todavía no tienes reconocimientos. Completa un curso o asiste a un evento para obtener el primero.</p>
    <?php endif; ?>
</div>
