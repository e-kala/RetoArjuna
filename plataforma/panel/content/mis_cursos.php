<?php
// "Mis cursos" — antes vivía combinado con eventos/reconocimientos/explorar
// bajo la pestaña "Mi aprendizaje"; separado en pestañas propias a pedido del
// usuario (2026-09-08). El widget "Explora el contenido" se queda aquí, al
// pie, porque es el lugar más natural para descubrir el siguiente curso.
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$esMiembro = usuario_tiene_membresia_activa($usuarioPerfilId);

$stmt = $conn->prepare(
    "SELECT c.id, c.titulo, c.slug, c.imagen_portada,
            (SELECT COUNT(*) FROM lecciones WHERE curso_id = c.id) AS total_lecciones,
            (SELECT COUNT(*) FROM progreso WHERE curso_id = c.id AND usuario_id = ? AND completado = 1) AS completadas,
            cert.codigo AS codigo_certificado
     FROM cursos c
     LEFT JOIN pagos p ON p.curso_id = c.id AND p.usuario_id = ? AND p.estado = 'confirmado'
     LEFT JOIN curso_inscripciones ci ON ci.curso_id = c.id AND ci.usuario_id = ?
     LEFT JOIN certificados cert ON cert.curso_id = c.id AND cert.usuario_id = ?
     WHERE c.activo = 1 AND (p.id IS NOT NULL OR ci.id IS NOT NULL)
     GROUP BY c.id"
);
$stmt->bind_param('iiii', $usuarioPerfilId, $usuarioPerfilId, $usuarioPerfilId, $usuarioPerfilId);
$stmt->execute();
$misCursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// "Explora el contenido": catálogo completo (cursos/eventos/tienda/noticias)
// combinado en una sola lista para buscar/filtrar del lado del cliente — el
// volumen de contenido de esta plataforma es chico, no hace falta ida y
// vuelta al servidor por cada búsqueda o filtro.
$contenidoExplorable = [];
$res = $conn->query("SELECT titulo, slug, imagen_portada AS imagen, created_at FROM cursos WHERE activo = 1");
foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
    $contenidoExplorable[] = ['tipo' => 'Cursos', 'icono' => '🎓', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=curso&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
}
$res = $conn->query("SELECT titulo, slug, imagen_portada AS imagen, created_at FROM eventos WHERE activo = 1 AND fecha_inicio >= NOW()" . ($esMiembro ? '' : ' AND solo_miembros = 0'));
foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
    $contenidoExplorable[] = ['tipo' => 'Eventos', 'icono' => '📅', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=evento&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
}
$res = $conn->query("SELECT nombre AS titulo, slug, imagen, created_at FROM productos WHERE activo = 1");
foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
    $contenidoExplorable[] = ['tipo' => 'Tienda', 'icono' => '🛍️', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=producto&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
}
$res = $conn->query("SELECT titulo, slug, imagen, publicada_at AS created_at FROM noticias WHERE activo = 1 AND publicada_at <= NOW()");
foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
    $contenidoExplorable[] = ['tipo' => 'Noticias', 'icono' => '📰', 'titulo' => $row['titulo'], 'imagen' => $row['imagen'], 'href' => '../index.php?action=noticia&slug=' . urlencode($row['slug']), 'fecha' => $row['created_at']];
}
usort($contenidoExplorable, fn($a, $b) => strtotime($b['fecha']) <=> strtotime($a['fecha']));
?>
<h1 class="h3 fw-bold mb-4">Mis cursos</h1>

<?php if ($misCursos): ?>
  <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
    <?php foreach ($misCursos as $curso): ?>
      <?php $porcentaje = $curso['total_lecciones'] > 0 ? (int) round($curso['completadas'] / $curso['total_lecciones'] * 100) : 0; ?>
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <img src="<?= htmlspecialchars($curso['imagen_portada'] ? BASE_URL . '/' . $curso['imagen_portada'] : BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:140px;object-fit:cover;" alt="">
          <div class="card-body d-flex flex-column">
            <h3 class="h6 fw-bold"><?= htmlspecialchars($curso['titulo']) ?></h3>
            <div class="progress mb-2" style="height:6px;">
              <div class="progress-bar" style="width:<?= $porcentaje ?>%;background:#F6C500;"></div>
            </div>
            <p class="small text-muted mb-3"><?= $porcentaje ?>% completado</p>
            <div class="mt-auto d-flex gap-2">
              <?php if ($curso['codigo_certificado']): ?>
                <a href="../certificado.php?codigo=<?= urlencode($curso['codigo_certificado']) ?>" class="btn btn-outline-secondary btn-sm">Certificado</a>
              <?php endif; ?>
              <a href="../index.php?action=curso&slug=<?= urlencode($curso['slug']) ?>" class="btn btn-sm fw-bold" style="background:#F6C500;color:#171717;">Continuar</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="text-muted mb-5">Aún no tienes cursos. <a href="../index.php?action=cursos" style="color:#B8860B;">Explora el catálogo</a>.</p>
<?php endif; ?>

<h2 class="h5 fw-bold mb-3">Explora el contenido</h2>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <input type="search" id="buscarContenido" class="form-control" style="max-width:280px;" placeholder="Buscar cursos, eventos, tienda, noticias...">
  <div class="btn-group flex-wrap" role="group" id="filtrosTipoContenido">
    <button type="button" class="btn btn-sm filtro-tipo active" data-tipo="Todos" style="background:#171717;color:#fff;">Todos</button>
    <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Cursos">🎓 Cursos</button>
    <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Eventos">📅 Eventos</button>
    <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Tienda">🛍️ Tienda</button>
    <button type="button" class="btn btn-sm btn-outline-secondary filtro-tipo" data-tipo="Noticias">📰 Noticias</button>
  </div>
  <select id="ordenContenido" class="form-select form-select-sm ms-auto" style="max-width:160px;">
    <option value="fecha">Más nuevo</option>
    <option value="alfabetico">A-Z</option>
  </select>
</div>
<div class="row row-cols-1 row-cols-md-3 g-4" id="gridContenido">
  <?php foreach ($contenidoExplorable as $item): ?>
    <div class="col item-contenido" data-tipo="<?= htmlspecialchars($item['tipo']) ?>" data-titulo="<?= htmlspecialchars(mb_strtolower($item['titulo'])) ?>" data-fecha="<?= htmlspecialchars($item['fecha']) ?>">
      <a href="<?= htmlspecialchars($item['href']) ?>" class="card h-100 text-decoration-none border-0 shadow-sm">
        <img src="<?= htmlspecialchars($item['imagen'] ? BASE_URL . '/' . $item['imagen'] : BASE_URL . '/../banner.png') ?>" class="card-img-top" style="height:140px;object-fit:cover;" alt="">
        <div class="card-body">
          <span class="badge rounded-pill mb-2" style="background:#fff3e0;color:#c96a00;"><?= $item['icono'] ?> <?= htmlspecialchars($item['tipo']) ?></span>
          <h3 class="h6 fw-bold text-body mb-0"><?= htmlspecialchars($item['titulo']) ?></h3>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>
<p id="sinResultadosContenido" class="text-muted text-center mt-4 d-none">No encontramos contenido que coincida con tu búsqueda.</p>

<script>
  (function () {
    const buscar = document.getElementById('buscarContenido');
    const orden = document.getElementById('ordenContenido');
    const grid = document.getElementById('gridContenido');
    const sinResultados = document.getElementById('sinResultadosContenido');
    const items = Array.from(grid.querySelectorAll('.item-contenido'));
    let tipoActivo = 'Todos';

    function aplicarFiltro() {
      const q = buscar.value.trim().toLowerCase();
      let visibles = 0;
      items.forEach(function (item) {
        const coincideTipo = tipoActivo === 'Todos' || item.dataset.tipo === tipoActivo;
        const coincideTexto = item.dataset.titulo.includes(q);
        const visible = coincideTipo && coincideTexto;
        item.classList.toggle('d-none', !visible);
        if (visible) visibles++;
      });
      sinResultados.classList.toggle('d-none', visibles > 0);
    }

    function aplicarOrden() {
      const criterio = orden.value;
      items.sort(function (a, b) {
        if (criterio === 'alfabetico') return a.dataset.titulo.localeCompare(b.dataset.titulo);
        return new Date(b.dataset.fecha) - new Date(a.dataset.fecha);
      });
      items.forEach(function (item) { grid.appendChild(item); });
    }

    buscar.addEventListener('input', aplicarFiltro);
    orden.addEventListener('change', aplicarOrden);
    document.querySelectorAll('.filtro-tipo').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.filtro-tipo').forEach(function (b) {
          b.classList.remove('active');
          b.classList.add('btn-outline-secondary');
          b.style.background = '';
          b.style.color = '';
        });
        btn.classList.add('active');
        btn.classList.remove('btn-outline-secondary');
        btn.style.background = '#171717';
        btn.style.color = '#fff';
        tipoActivo = btn.dataset.tipo;
        aplicarFiltro();
      });
    });
  })();
</script>
