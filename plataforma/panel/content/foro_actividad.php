<?php
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];

$stmt = $conn->prepare(
    "SELECT t.id, t.titulo, t.slug, t.fijado, t.cerrado, t.respuestas_count, t.vistas, t.editado_en, t.created_at,
            c.nombre AS categoria_nombre
     FROM foro_temas t
     JOIN foro_categorias c ON c.id = t.categoria_id
     WHERE t.usuario_id = ?
     ORDER BY t.created_at DESC LIMIT 30"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$misTemas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare(
    "SELECT r.id, r.tema_id, r.contenido, r.editado_en, r.created_at, t.titulo AS tema_titulo
     FROM foro_respuestas r
     JOIN foro_temas t ON t.id = r.tema_id
     WHERE r.usuario_id = ?
     ORDER BY r.created_at DESC LIMIT 30"
);
$stmt->bind_param('i', $usuarioPerfilId);
$stmt->execute();
$misRespuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare(
    "SELECT 'tema' AS tipo, th.tema_id AS tema_id, t.titulo AS item_titulo, th.editado_en
     FROM foro_temas_historial th
     JOIN foro_temas t ON t.id = th.tema_id
     WHERE th.editado_por = ?
     UNION ALL
     SELECT 'respuesta', t2.id AS tema_id, t2.titulo, rh.editado_en
     FROM foro_respuestas_historial rh
     JOIN foro_respuestas r ON r.id = rh.respuesta_id
     JOIN foro_temas t2 ON t2.id = r.tema_id
     WHERE rh.editado_por = ?
     ORDER BY editado_en DESC LIMIT 30"
);
$stmt->bind_param('ii', $usuarioPerfilId, $usuarioPerfilId);
$stmt->execute();
$misEdiciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Mi actividad en el foro</h1>
    <a href="<?= htmlspecialchars(BASE_URL) ?>/foro/index.php" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;" target="_blank">Ir al foro</a>
</div>

<h2 class="h5 mb-3">Mis temas</h2>
<div class="table-responsive mb-4">
<table class="table table-bordered bg-white">
    <thead><tr><th>Tema</th><th>Categoría</th><th>Estado</th><th>Respuestas</th><th>Vistas</th><th>Fecha</th></tr></thead>
    <tbody>
        <?php foreach ($misTemas as $t): ?>
            <tr>
                <td>
                    <a href="<?= htmlspecialchars(BASE_URL) ?>/foro/tema.php?id=<?= (int) $t['id'] ?>" target="_blank"><?= htmlspecialchars($t['titulo']) ?></a>
                    <?php if ($t['editado_en']): ?><span class="badge bg-light text-muted border">editado</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($t['categoria_nombre']) ?></td>
                <td>
                    <?php if ($t['fijado']): ?><span class="badge bg-warning text-dark">Fijado</span><?php endif; ?>
                    <?php if ($t['cerrado']): ?><span class="badge bg-secondary">Cerrado</span><?php endif; ?>
                    <?php if (!$t['fijado'] && !$t['cerrado']): ?><span class="text-muted small">Abierto</span><?php endif; ?>
                </td>
                <td><?= (int) $t['respuestas_count'] ?></td>
                <td><?= (int) $t['vistas'] ?></td>
                <td><?= htmlspecialchars($t['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$misTemas): ?>
            <tr><td colspan="6" class="text-muted">Todavía no has publicado ningún tema.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<h2 class="h5 mb-3">Mis respuestas</h2>
<div class="table-responsive mb-4">
<table class="table table-bordered bg-white">
    <thead><tr><th>Tema</th><th>Respuesta</th><th>Fecha</th></tr></thead>
    <tbody>
        <?php foreach ($misRespuestas as $r): ?>
            <?php $textoPlano = trim(strip_tags($r['contenido'])); ?>
            <tr>
                <td><a href="<?= htmlspecialchars(BASE_URL) ?>/foro/tema.php?id=<?= (int) $r['tema_id'] ?>" target="_blank"><?= htmlspecialchars($r['tema_titulo']) ?></a></td>
                <td>
                    <?= htmlspecialchars(mb_substr($textoPlano, 0, 120)) ?><?= mb_strlen($textoPlano) > 120 ? '…' : '' ?>
                    <?php if ($r['editado_en']): ?><span class="badge bg-light text-muted border">editado</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$misRespuestas): ?>
            <tr><td colspan="3" class="text-muted">Todavía no has respondido ningún tema.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<h2 class="h5 mb-3">Mi historial de ediciones</h2>
<div class="table-responsive">
<table class="table table-bordered bg-white">
    <thead><tr><th>Tipo</th><th>Tema</th><th>Editado</th></tr></thead>
    <tbody>
        <?php foreach ($misEdiciones as $e): ?>
            <tr>
                <td><?= $e['tipo'] === 'tema' ? 'Tema' : 'Respuesta' ?></td>
                <td><a href="<?= htmlspecialchars(BASE_URL) ?>/foro/tema.php?id=<?= (int) $e['tema_id'] ?>" target="_blank"><?= htmlspecialchars($e['item_titulo']) ?></a></td>
                <td><?= htmlspecialchars($e['editado_en']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$misEdiciones): ?>
            <tr><td colspan="3" class="text-muted">Todavía no has editado ningún tema o respuesta.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
