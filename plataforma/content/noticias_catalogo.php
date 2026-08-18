<?php
$noticias = $conn->query(
    "SELECT id, titulo, slug, resumen, imagen, publicada_at
     FROM noticias WHERE activo = 1 AND publicada_at <= NOW() ORDER BY publicada_at DESC"
)->fetch_all(MYSQLI_ASSOC);
?>
<section class="py-5">
  <div class="container py-4">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Noticias</h2>
      <p class="text-muted">Avisos y novedades de la comunidad del Reto Arjuna.</p>
    </div>
    <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
      <?php foreach ($noticias as $n): ?>
        <div class="col" style="max-width:360px;">
          <div class="card h-100 border-0 shadow-sm">
            <img src="<?= htmlspecialchars($n['imagen'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
            <div class="card-body d-flex flex-column">
              <h3 class="h5 fw-bold"><?= htmlspecialchars($n['titulo']) ?></h3>
              <p class="small text-muted flex-grow-1"><?= htmlspecialchars((string) $n['resumen']) ?></p>
              <div class="d-flex align-items-center justify-content-between mt-2">
                <span class="small text-muted"><?= htmlspecialchars(date('d/m/Y', strtotime($n['publicada_at']))) ?></span>
                <a href="?action=noticia&amp;slug=<?= urlencode($n['slug']) ?>" class="btn btn-outline-secondary btn-sm">Leer más</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$noticias): ?>
        <p class="text-muted text-center">Todavía no hay noticias publicadas.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
