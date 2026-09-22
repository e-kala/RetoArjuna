<?php
$cursos = $conn->query(
    "SELECT id, titulo, slug, descripcion, precio, imagen_portada, gratuito, incluido_membresia, created_at, 'curso' AS origen
     FROM cursos WHERE activo = 1"
)->fetch_all(MYSQLI_ASSOC);

// Un evento ya pasado con grabación puede marcarse "también mostrar en el
// catálogo de Cursos" (panel/admin/contenido_form.php) — se consume igual
// que un curso, bajo demanda. Sigue viviendo en `eventos`: no se copia nada,
// solo se lista aquí y el botón "Ver curso" enlaza a su propia página de
// evento (?action=evento, no ?action=curso). solo_miembros se excluye —
// mismo criterio que el resto del sitio (H02): contenido exclusivo de
// membresía nunca es el anzuelo público de un catálogo.
$eventosComoCursos = $conn->query(
    "SELECT id, titulo, slug, descripcion, precio, imagen_portada, gratuito, incluido_membresia, created_at, 'evento' AS origen
     FROM eventos WHERE activo = 1 AND mostrar_en_cursos = 1 AND solo_miembros = 0
       AND video_grabado_url IS NOT NULL AND video_grabado_url <> '' AND fecha_inicio < NOW()"
)->fetch_all(MYSQLI_ASSOC);

$cursos = array_merge($cursos, $eventosComoCursos);
usort($cursos, fn($a, $b) => [(int) $b['gratuito'], $b['created_at']] <=> [(int) $a['gratuito'], $a['created_at']]);

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
              <?php if ((int) $curso['gratuito'] === 1): ?>
                <div class="mb-2">
                  <span class="badge align-self-start" style="background:#F6C500;color:#171717;">🎉 Gratis</span>
                </div>
              <?php endif; ?>
              <h3 class="h5 fw-bold"><?= htmlspecialchars($curso['titulo']) ?></h3>
              <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth(trim(strip_tags((string) $curso['descripcion'])), 0, 120, '…')) ?></p>
              <div class="d-flex align-items-center justify-content-between mt-2">
                <?php if ((int) $curso['gratuito'] === 1): ?>
                  <span class="badge rounded-pill" style="background:#fff3e0;color:#c96a00;">Gratuito</span>
                <?php elseif ($esMiembroCatalogo && (int) $curso['incluido_membresia'] === 1): ?>
                  <span class="badge rounded-pill" style="background:#6f42c1;color:#fff;"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido</span>
                <?php elseif ($usuarioCatalogo && ($curso['origen'] === 'curso'
                    ? usuario_tiene_acceso_curso($usuarioCatalogo['id'], (int) $curso['id'])
                    : usuario_esta_inscrito_evento($usuarioCatalogo['id'], (int) $curso['id']))): ?>
                  <span class="badge rounded-pill" style="background:#e6f4ea;color:#1e7d3c;">✔ Adquirido</span>
                <?php else: ?>
                  <span class="badge rounded-pill" style="background:#fff3e0;color:#c96a00;">$<?= number_format((float) $curso['precio'], 2) ?> MXN</span>
                  <?php if ($usuarioCatalogo && (int) $curso['incluido_membresia'] === 1): ?>
                    <span class="badge rounded-pill d-inline-flex align-items-center" style="background:#6f42c1;color:#fff;" title="Incluido con membresía"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" style="margin-right:0;" alt="Incluido con membresía"></span>
                  <?php endif; ?>
                <?php endif; ?>
                <a href="?action=<?= $curso['origen'] === 'curso' ? 'curso' : 'evento' ?>&amp;slug=<?= urlencode($curso['slug']) ?>" class="btn btn-outline-secondary btn-sm">Ver curso</a>
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
