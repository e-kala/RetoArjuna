<?php
require_once __DIR__ . '/../backend/ofertas.php';
$usuarioCatalogo = current_user();
$usuarioIdCatalogo = (int) ($usuarioCatalogo['id'] ?? 0);

// total_lecciones/completadas por tarjeta — para decidir "Comenzar a
// estudiar" (0% o sin acceso todavía) vs "Seguir estudiando" (ya avanzó
// algo), mismo patrón de subquery correlacionada que ya usa
// eventos_catalogo.php/mis_cursos.php. usuario_id=0 (sin sesión) no
// matchea ninguna fila de `progreso`, así que $porcentaje sale null.
$stmtCursosCatalogo = $conn->prepare(
    "SELECT id, titulo, slug, descripcion, precio, imagen_portada, gratuito, incluido_membresia, descuento_miembro_pct, created_at, 'curso' AS origen,
            (SELECT COUNT(*) FROM lecciones WHERE curso_id = cursos.id AND estado_publicacion = 'publicado') AS total_lecciones,
            (SELECT COUNT(*) FROM progreso WHERE curso_id = cursos.id AND usuario_id = ? AND completado = 1) AS lecciones_completadas
     FROM cursos WHERE activo = 1"
);
$stmtCursosCatalogo->bind_param('i', $usuarioIdCatalogo);
$stmtCursosCatalogo->execute();
$cursos = $stmtCursosCatalogo->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCursosCatalogo->close();

// Un evento ya pasado con grabación puede marcarse "también mostrar en el
// catálogo de Cursos" (panel/admin/contenido_form.php) — se consume igual
// que un curso, bajo demanda. Sigue viviendo en `eventos`: no se copia nada,
// solo se lista aquí y el botón "Ver curso" enlaza a su propia página de
// evento (?action=evento, no ?action=curso). solo_miembros se excluye —
// mismo criterio que el resto del sitio (H02): contenido exclusivo de
// membresía nunca es el anzuelo público de un catálogo.
$stmtEventosComoCursos = $conn->prepare(
    "SELECT id, titulo, slug, descripcion, precio, imagen_portada, gratuito, incluido_membresia, descuento_miembro_pct, created_at, 'evento' AS origen,
            (SELECT COUNT(*) FROM lecciones WHERE evento_id = eventos.id AND estado_publicacion = 'publicado') AS total_lecciones,
            (SELECT COUNT(*) FROM progreso WHERE evento_id = eventos.id AND usuario_id = ? AND completado = 1) AS lecciones_completadas
     FROM eventos WHERE activo = 1 AND mostrar_en_cursos = 1 AND solo_miembros = 0
       AND video_grabado_url IS NOT NULL AND video_grabado_url <> '' AND fecha_inicio < NOW()"
);
$stmtEventosComoCursos->bind_param('i', $usuarioIdCatalogo);
$stmtEventosComoCursos->execute();
$eventosComoCursos = $stmtEventosComoCursos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEventosComoCursos->close();

$cursos = array_merge($cursos, $eventosComoCursos);
usort($cursos, fn($a, $b) => [(int) $b['gratuito'], $b['created_at']] <=> [(int) $a['gratuito'], $a['created_at']]);

$esMiembroCatalogo = $usuarioCatalogo && usuario_tiene_membresia_activa($usuarioCatalogo['id']);
?>
<section class="py-5">
  <div class="container py-4">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Cursos</h2>
      <p class="text-muted">Aprende a tu ritmo, con certificado al completar cada curso.</p>
    </div>
    <?php $buscadorId='buscarCursos'; $buscadorPlaceholder='Buscar cursos por título o descripción…'; include __DIR__.'/_catalogo_buscador.php'; ?>
    <div class="row row-cols-1 row-cols-md-3 justify-content-center g-4">
      <?php foreach ($cursos as $curso): ?>
        <?php
        $tieneAccesoDirectoCurso = $usuarioCatalogo && ($curso['origen'] === 'curso'
            ? usuario_tiene_acceso_curso($usuarioCatalogo['id'], (int) $curso['id'])
            : usuario_esta_inscrito_evento($usuarioCatalogo['id'], (int) $curso['id']));
        // Acceso por membresía: el producto en sí está incluido Y el usuario
        // es miembro ahora — igual criterio que curso_detalle.php
        // ($incluidoPorMembresia). Se evalúa aparte de la inscripción/compra
        // real porque evento_inscripciones no guarda de dónde vino el
        // acceso (compra vs. gratis-por-membresía) — la pregunta correcta
        // no es "cómo llegó esta fila" sino "¿este producto es de los que
        // la membresía incluye, para un miembro actual?".
        $ofertaCurso = resolver_oferta($conn, $curso['origen'], [
            'id' => (int) $curso['id'], 'precio' => (float) $curso['precio'],
            'gratuito' => (bool) $curso['gratuito'], 'incluido_membresia' => (bool) $curso['incluido_membresia'],
            'solo_miembros' => false, 'descuento_miembro_pct' => $curso['descuento_miembro_pct'],
            'ya_tiene_acceso' => (bool) $tieneAccesoDirectoCurso,
        ], $usuarioCatalogo);
        $accesoPorMembresiaCurso = $ofertaCurso['estado'] === 'incluido_membresia';
        $tieneAccesoCurso = $tieneAccesoDirectoCurso || $accesoPorMembresiaCurso;
        $totalLeccionesCurso = (int) ($curso['total_lecciones'] ?? 0);
        $tieneProgresoCurso = $totalLeccionesCurso > 0 && (int) ($curso['lecciones_completadas'] ?? 0) > 0;
        // ?ver=1 salta la landing comercial (si el curso/evento tiene una
        // vinculada) — quien ya tiene acceso va directo al contenido, mismo
        // criterio que el botón "Enlace directo" del admin.
        $hrefCurso = '?action=' . ($curso['origen'] === 'curso' ? 'curso' : 'evento') . '&amp;slug=' . urlencode($curso['slug']) . ($tieneAccesoCurso ? '&amp;ver=1' : '');
        $labelCtaCurso = $tieneAccesoCurso
            ? ($curso['origen'] === 'evento'
                ? ($tieneProgresoCurso ? 'Seguir viendo' : 'Comenzar a ver')
                : ($tieneProgresoCurso ? 'Seguir estudiando' : 'Comenzar a estudiar'))
            : ($curso['origen'] === 'evento' ? 'Saber más de las grabaciones y los materiales' : 'Saber más del curso');
        $esPromoCurso = $ofertaCurso['estado'] === 'oferta' && in_array($ofertaCurso['oferta_modalidad'], ['promocion', 'preventa'], true);
        $esDescuentoMiembroCurso = $ofertaCurso['estado'] === 'oferta'
            && in_array($ofertaCurso['oferta_tipo'], ['descuento_miembro', 'combinado'], true);
        ?>
        <div class="col pf-item-buscable" data-busqueda="<?=htmlspecialchars($curso['titulo'].' '.strip_tags((string)$curso['descripcion']),ENT_QUOTES)?>" style="max-width:360px;">
          <div class="card h-100 border-0 shadow-sm pf-card-clicable" style="cursor:pointer;" data-href="<?= $hrefCurso ?>">
            <img src="<?= htmlspecialchars($curso['imagen_portada'] ?: BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
            <div class="card-body d-flex flex-column">
              <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                <?php if ($tieneAccesoDirectoCurso): ?>
                  <span class="badge rounded-pill pf-badge-acceso">✔ Ya tienes acceso</span>
                <?php elseif ($accesoPorMembresiaCurso): ?>
                  <span class="badge rounded-pill pf-badge-incluido d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con tu membresía</span>
                <?php elseif ((int) $curso['gratuito'] === 1): ?>
                  <span class="badge rounded-pill pf-badge-gratis">Gratuito</span>
                <?php elseif ($esPromoCurso): ?>
                  <span class="badge rounded-pill <?= $ofertaCurso['oferta_modalidad'] === 'preventa' ? 'pf-badge-preventa' : 'pf-badge-promocion' ?>"><?= $ofertaCurso['oferta_modalidad'] === 'preventa' ? 'Preventa' : '🏷️ Promoción' ?> · <?= htmlspecialchars((string) $ofertaCurso['oferta_nombre']) ?></span>
                <?php elseif ($esDescuentoMiembroCurso): ?>
                  <span class="badge rounded-pill pf-badge-precio-miembro d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Precio especial con membresía</span>
                <?php elseif ((int) $curso['incluido_membresia'] === 1): ?>
                  <span class="badge rounded-pill pf-badge-incluido d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Incluido con Membresía</span>
                <?php endif; ?>
                <?php if ($usuarioCatalogo && !$esMiembroCatalogo && (float) ($curso['descuento_miembro_pct'] ?? 0) > 0): ?>
                  <span class="badge rounded-pill pf-badge-descuento-miembro d-inline-flex align-items-center gap-1"><img src="<?= htmlspecialchars(BASE_URL) ?>/img/logo-membresia-camino-arjuna-icono.png" class="pf-icono-membresia" alt=""> Descuento con membresía <?= number_format((float) $curso['descuento_miembro_pct'], 0) ?>%</span>
                <?php endif; ?>
              </div>
              <h3 class="h5 fw-bold"><?= htmlspecialchars($curso['titulo']) ?></h3>
              <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth(trim(strip_tags((string) $curso['descripcion'])), 0, 120, '…')) ?></p>
              <a href="<?= $hrefCurso ?>" class="small fw-semibold text-decoration-none d-block w-100 text-end mt-2" style="color:#171717;"><?= htmlspecialchars($labelCtaCurso) ?> →</a>
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
