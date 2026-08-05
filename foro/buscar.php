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

<div class="pf-forum-head">
  <div>
    <h1>Resultados para «<?= htmlspecialchars($q) ?>»</h1>
    <p><?= count($temas) + count($respuestas) ?> resultado(s)</p>
  </div>
</div>

<?php if ($q === ''): ?>
  <p class="pf-forum-empty">Escribe algo en el buscador para empezar.</p>
<?php else: ?>
  <?php if ($temas): ?>
    <h2 style="font-size:17px;font-weight:800;margin-bottom:12px;">Temas</h2>
    <div class="pf-forum-list" style="margin-bottom:32px;">
      <?php foreach ($temas as $t): ?>
        <a class="pf-forum-tema-row" href="tema.php?id=<?= (int) $t['id'] ?>">
          <div>
            <div class="pf-forum-tema-titulo"><?= htmlspecialchars($t['titulo']) ?></div>
            <div class="pf-forum-tema-meta">
              en <?= htmlspecialchars($t['categoria_nombre']) ?> · por <?= htmlspecialchars($t['username_cache']) ?>
              · <?= foro_tiempo_relativo($t['created_at']) ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($respuestas): ?>
    <h2 style="font-size:17px;font-weight:800;margin-bottom:12px;">Respuestas</h2>
    <div class="pf-forum-list">
      <?php foreach ($respuestas as $r): ?>
        <a class="pf-forum-tema-row" href="tema.php?id=<?= (int) $r['tema_id'] ?>#respuesta-<?= (int) $r['id'] ?>">
          <div>
            <div class="pf-forum-tema-titulo">en «<?= htmlspecialchars($r['tema_titulo']) ?>»</div>
            <div class="pf-forum-tema-meta">
              por <?= htmlspecialchars($r['username_cache']) ?> · <?= foro_tiempo_relativo($r['created_at']) ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$temas && !$respuestas): ?>
    <p class="pf-forum-empty">No se encontró nada con esa búsqueda.</p>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
