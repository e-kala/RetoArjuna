<?php
require_once __DIR__ . '/backend/foro_helpers.php';

$q = trim($_GET['q'] ?? '');
$page_title = 'Buscar: ' . $q;

$temas = [];
$respuestas = [];

if ($q !== '') {
    $stmt = $conn->prepare(
        "SELECT t.id, t.titulo, t.contenido, t.created_at, u.username_cache, c.nombre AS categoria_nombre
         FROM foro_temas t
         JOIN usuarios_perfil u ON u.id = t.usuario_id
         JOIN foro_categorias c ON c.id = t.categoria_id
         WHERE MATCH(t.titulo, t.contenido) AGAINST (? IN NATURAL LANGUAGE MODE)
         ORDER BY t.created_at DESC LIMIT 20"
    );
    $stmt->bind_param('s', $q);
    $stmt->execute();
    $temas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT r.id, r.tema_id, r.contenido, r.created_at, u.username_cache, t.titulo AS tema_titulo
         FROM foro_respuestas r
         JOIN usuarios_perfil u ON u.id = r.usuario_id
         JOIN foro_temas t ON t.id = r.tema_id
         WHERE MATCH(r.contenido) AGAINST (? IN NATURAL LANGUAGE MODE)
         ORDER BY r.created_at DESC LIMIT 20"
    );
    $stmt->bind_param('s', $q);
    $stmt->execute();
    $respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

require __DIR__ . '/inc/header.php';
?>

<div class="mb-4">
  <h1 class="h3 fw-bold mb-1">Resultados para «<?= htmlspecialchars($q) ?>»</h1>
  <p class="text-muted mb-0"><?= count($temas) + count($respuestas) ?> resultado(s)</p>
</div>

<?php if ($q === ''): ?>
  <div class="text-center text-muted py-5"><i class="bi bi-search fs-2 d-block mb-2"></i>Escribe algo en el buscador para empezar.</div>
<?php else: ?>
  <?php if ($temas): ?>
    <h2 class="h6 fw-bold mb-3">Temas</h2>
    <div class="list-group shadow-sm mb-4">
      <?php foreach ($temas as $t): ?>
        <a class="list-group-item list-group-item-action" href="tema.php?id=<?= (int) $t['id'] ?>">
          <div class="fw-bold"><?= htmlspecialchars($t['titulo']) ?></div>
          <div class="text-muted small mt-1">
            en <?= htmlspecialchars($t['categoria_nombre']) ?> · por <?= htmlspecialchars($t['username_cache']) ?>
            · <?= foro_tiempo_relativo($t['created_at']) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($respuestas): ?>
    <h2 class="h6 fw-bold mb-3">Respuestas</h2>
    <div class="list-group shadow-sm">
      <?php foreach ($respuestas as $r): ?>
        <a class="list-group-item list-group-item-action" href="tema.php?id=<?= (int) $r['tema_id'] ?>#respuesta-<?= (int) $r['id'] ?>">
          <div class="fw-bold">en «<?= htmlspecialchars($r['tema_titulo']) ?>»</div>
          <div class="text-muted small mt-1">
            por <?= htmlspecialchars($r['username_cache']) ?> · <?= foro_tiempo_relativo($r['created_at']) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$temas && !$respuestas): ?>
    <div class="text-center text-muted py-5"><i class="bi bi-search fs-2 d-block mb-2"></i>No se encontró nada con esa búsqueda.</div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
