<?php
$cursos = $conn->query(
    'SELECT id, titulo, slug, descripcion, nivel, precio, imagen_portada, gratuito, incluido_membresia
     FROM cursos WHERE activo = 1 ORDER BY gratuito DESC, created_at DESC'
)->fetch_all(MYSQLI_ASSOC);
$usuarioCatalogo = current_user();
$esMiembroCatalogo = $usuarioCatalogo && usuario_tiene_membresia_activa($usuarioCatalogo['id']);
?>
<section class="py-5">
  <div class="container py-4">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Cursos</h2>
      <p class="text-muted">Aprende a tu ritmo, con certificado al completar cada curso.</p>
    </div>
    <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
      <?php foreach ($cursos as $curso): ?>
        <div class="col" style="max-width:360px;">
          <div class="card h-100 border-0 shadow-sm">
            <img src="<?= htmlspecialchars($curso['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
            <div class="card-body d-flex flex-column">
              <h3 class="h5 fw-bold"><?= htmlspecialchars($curso['titulo']) ?></h3>
              <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth((string) $curso['descripcion'], 0, 120, '…')) ?></p>
              <div class="d-flex align-items-center justify-content-between mt-2">
                <?php if ((int) $curso['gratuito'] === 1): ?>
                  <span class="badge rounded-pill" style="background:#fff3e0;color:#c96a00;">Gratuito</span>
                <?php elseif ($esMiembroCatalogo && (int) $curso['incluido_membresia'] === 1): ?>
                  <span class="badge rounded-pill" style="background:#6f42c1;color:#fff;">👑 Incluido</span>
                <?php elseif ($usuarioCatalogo && usuario_tiene_acceso_curso($usuarioCatalogo['id'], (int) $curso['id'])): ?>
                  <span class="badge rounded-pill" style="background:#fff3e0;color:#c96a00;">Ya inscrito</span>
                <?php else: ?>
                  <span class="badge rounded-pill" style="background:#fff3e0;color:#c96a00;">$<?= number_format((float) $curso['precio'], 2) ?> MXN</span>
                  <?php if ((int) $curso['incluido_membresia'] === 1): ?>
                    <span class="badge rounded-pill" style="background:#6f42c1;color:#fff;">👑</span>
                  <?php endif; ?>
                <?php endif; ?>
                <a href="?action=curso&amp;slug=<?= urlencode($curso['slug']) ?>" class="btn btn-outline-secondary btn-sm">Ver curso</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$cursos): ?>
        <p class="text-muted text-center">Aún no hay cursos publicados.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
