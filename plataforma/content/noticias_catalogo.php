<?php
$noticias = $conn->query(
    "SELECT id, titulo, slug, resumen, imagen, publicada_at
     FROM noticias WHERE activo = 1 AND publicada_at <= NOW() ORDER BY publicada_at DESC"
)->fetch_all(MYSQLI_ASSOC);
?>
<section class="pf-section">
  <div class="pf-container">
    <div class="pf-section-title">
      <h2>Noticias</h2>
      <p>Avisos y novedades de la comunidad del Reto Arjuna.</p>
    </div>
    <div class="pf-grid-3">
      <?php foreach ($noticias as $n): ?>
        <div class="pf-course-card">
          <?php if ($n['imagen']): ?>
            <img src="<?= htmlspecialchars($n['imagen']) ?>" alt="">
          <?php endif; ?>
          <div class="pf-course-card-body">
            <h3><?= htmlspecialchars($n['titulo']) ?></h3>
            <p><?= htmlspecialchars((string) $n['resumen']) ?></p>
            <div class="pf-course-card-footer">
              <span style="color:var(--pf-muted);font-size:13px;"><?= htmlspecialchars(date('d/m/Y', strtotime($n['publicada_at']))) ?></span>
              <a href="?action=noticia&amp;slug=<?= urlencode($n['slug']) ?>" class="pf-btn pf-btn-outline">Leer más</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$noticias): ?>
        <p style="color:var(--pf-muted);">Todavía no hay noticias publicadas.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
