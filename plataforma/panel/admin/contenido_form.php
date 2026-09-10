<?php
// Editor unificado de Cursos y Eventos: al crear contenido nuevo eliges arriba
// si es "Curso" o "Evento" y el formulario cambia para pedir los campos que
// correspondan — así se evita registrar algo en la tabla equivocada desde el
// principio. Para contenido YA existente que quedó mal clasificado, usa el
// botón "Convertir a evento/curso" en las listas (cursos.php/eventos.php),
// que migra los datos en vez de tener que volver a capturarlos aquí.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/uploads.php';
require_role('admin');
requerir_csrf_form();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'curso';
$tipo = in_array($tipo, ['curso', 'evento'], true) ? $tipo : 'curso';

$curso = ['titulo' => '', 'slug' => '', 'descripcion' => '', 'nivel' => 'principiante', 'duracion_horas' => '',
          'precio' => 0, 'imagen_portada' => '', 'foro_url' => '', 'gratuito' => 0, 'incluido_membresia' => 0,
          'descuento_miembro_pct' => '', 'activo' => 1, 'mostrar_codigo_promocion' => 0];
$evento = ['titulo' => '', 'slug' => '', 'descripcion' => '', 'tipo' => 'online', 'ubicacion' => '',
           'fecha_inicio' => '', 'fecha_fin' => '', 'cupo_maximo' => '', 'precio' => 0,
           'imagen_portada' => '', 'foro_url' => '', 'video_grabado_url' => '', 'gratuito' => 0,
           'solo_miembros' => 0, 'incluido_membresia' => 0, 'descuento_miembro_pct' => '', 'activo' => 1, 'mostrar_codigo_promocion' => 0];

if ($id && $tipo === 'curso') {
    $stmt = $conn->prepare('SELECT * FROM cursos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc() ?: $curso;
    $stmt->close();
} elseif ($id && $tipo === 'evento') {
    $stmt = $conn->prepare('SELECT * FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc() ?: $evento;
    $stmt->close();
}

// Prestaciones y regalos (ver backend/regalos.php) — configuración opcional,
// nunca activa por default.
$regalo = ['activo' => 0, 'descuento_pct' => 100, 'max_usuarios_habilitados' => '', 'enlaces_por_usuario' => 1, 'vigencia_dias' => ''];
if ($id) {
    $regalo = $tipo === 'curso'
        ? regalo_configuracion_obtener($id, null)
        : regalo_configuracion_obtener(null, $id);
    $regalo = $regalo ?: ['activo' => 0, 'descuento_pct' => 100, 'max_usuarios_habilitados' => '', 'enlaces_por_usuario' => 1, 'vigencia_dias' => ''];
}

// Flujo de venta: landing comercial a la que se redirige a un visitante sin
// acceso (ver curso_detalle.php/evento_detalle.php) — solo landings activas,
// para no poder enlazar una que ni siquiera se puede visitar hoy.
$landingsDisponibles = $conn->query('SELECT id, titulo, slug FROM landing_pages WHERE activo = 1 ORDER BY titulo')->fetch_all(MYSQLI_ASSOC);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $titulo = trim($_POST['titulo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $titulo), '-'));
    }
    $descripcion = trim($_POST['descripcion'] ?? '');
    // El editor Quill vacío manda "<p><br></p>" en vez de una cadena vacía
    // — se normaliza para no guardar ese HTML como si fuera una descripción real.
    if (trim(str_replace(['<p><br></p>', '<p><br/></p>'], '', $descripcion)) === '') {
        $descripcion = '';
    }
    $precio = (float) ($_POST['precio'] ?? 0);
    $gratuito = isset($_POST['gratuito']) ? 1 : 0;
    $incluidoMembresia = isset($_POST['incluido_membresia']) ? 1 : 0;
    // El campo ya no trae min/max en el HTML (evitaba un bloqueo de
    // validación del navegador en un campo opcional) — el rango [0,100] se
    // fuerza aquí en vez de ahí.
    $descuentoMiembroPct = trim($_POST['descuento_miembro_pct'] ?? '') !== '' ? min(100.0, max(0.0, (float) $_POST['descuento_miembro_pct'])) : null;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $mostrarCodigoPromocion = isset($_POST['mostrar_codigo_promocion']) ? 1 : 0;
    $landingPageId = trim($_POST['landing_page_id'] ?? '') !== '' ? (int) $_POST['landing_page_id'] : null;

    $regaloActivo = isset($_POST['regalo_activo']) ? 1 : 0;
    $regaloDescuentoPct = min(100.0, max(0.01, (float) ($_POST['regalo_descuento_pct'] ?? 100)));
    $regaloMaxUsuarios = trim($_POST['regalo_max_usuarios'] ?? '') !== '' ? (int) $_POST['regalo_max_usuarios'] : null;
    $regaloEnlacesPorUsuario = max(1, (int) ($_POST['regalo_enlaces_por_usuario'] ?? 1));
    $regaloVigenciaDias = trim($_POST['regalo_vigencia_dias'] ?? '') !== '' ? (int) $_POST['regalo_vigencia_dias'] : null;

    // Autoguardado del editor (ver panel/admin/_autosave.js) — actualiza el
    // registro ya existente sin difundir notificaciones de "nuevo curso/evento"
    // (el admin todavía está editando) y sin redirigir al listado, para poder
    // seguir editando. El JS nunca dispara esto para un $id=0 (no crea
    // contenido nuevo por autoguardado, evita publicar algo a medio llenar).
    $esAutosave = ($_POST['accion_autosave'] ?? '') === '1';
    if ($esAutosave && !$id) {
        // Defensa por si algo dispara el autoguardado sin id (el JS ya lo
        // evita) — nunca crear contenido nuevo desde aquí.
        if ($esAjax) {
            echo json_encode(['success' => true, 'id' => 0]);
            exit;
        }
    } else {

    /**
     * Upsert de regalo_configuracion para el curso/evento recién guardado —
     * compartido entre ambas ramas de abajo para no repetirlo dos veces.
     */
    $guardarRegaloConfig = function (int $itemId, bool $esCurso) use ($conn, $regaloActivo, $regaloDescuentoPct, $regaloMaxUsuarios, $regaloEnlacesPorUsuario, $regaloVigenciaDias): void {
        $columna = $esCurso ? 'curso_id' : 'evento_id';
        // regalo_configuracion_obtener() solo trae config activa=1 — aquí se
        // busca sin ese filtro, para decidir UPDATE vs INSERT sin importar
        // si la config existente está apagada.
        $stmt = $conn->prepare("SELECT id FROM regalo_configuracion WHERE {$columna} = ?");
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($fila) {
            $stmt = $conn->prepare('UPDATE regalo_configuracion SET activo=?, descuento_pct=?, max_usuarios_habilitados=?, enlaces_por_usuario=?, vigencia_dias=? WHERE id=?');
            $stmt->bind_param('idiiii', $regaloActivo, $regaloDescuentoPct, $regaloMaxUsuarios, $regaloEnlacesPorUsuario, $regaloVigenciaDias, $fila['id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO regalo_configuracion ({$columna}, activo, descuento_pct, max_usuarios_habilitados, enlaces_por_usuario, vigencia_dias) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('iidiii', $itemId, $regaloActivo, $regaloDescuentoPct, $regaloMaxUsuarios, $regaloEnlacesPorUsuario, $regaloVigenciaDias);
        }
        $stmt->execute();
        $stmt->close();
    };

    if ($tipo === 'curso') {
        $nivel = $_POST['nivel'] ?? 'principiante';
        $duracion = $_POST['duracion_horas'] !== '' ? (float) $_POST['duracion_horas'] : null;
        $imagen = (string) $curso['imagen_portada'];
        $foroUrl = trim($_POST['foro_url'] ?? '');
        try {
            $imagen = procesar_imagen_form('imagen_portada_file', 'cursos', $imagen);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($titulo === '') {
            $error = 'El título es obligatorio.';
        } elseif ($error === '') {
            $esNuevo = !$id;
            if ($id) {
                $stmt = $conn->prepare('UPDATE cursos SET titulo=?, slug=?, descripcion=?, nivel=?, duracion_horas=?, precio=?, imagen_portada=?, foro_url=?, gratuito=?, incluido_membresia=?, descuento_miembro_pct=?, activo=?, mostrar_codigo_promocion=?, landing_page_id=? WHERE id=?');
                $stmt->bind_param('ssssddssiidiiii', $titulo, $slug, $descripcion, $nivel, $duracion, $precio, $imagen, $foroUrl, $gratuito, $incluidoMembresia, $descuentoMiembroPct, $activo, $mostrarCodigoPromocion, $landingPageId, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO cursos (titulo, slug, descripcion, nivel, duracion_horas, precio, imagen_portada, foro_url, gratuito, incluido_membresia, descuento_miembro_pct, activo, mostrar_codigo_promocion, landing_page_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->bind_param('ssssddssiidiii', $titulo, $slug, $descripcion, $nivel, $duracion, $precio, $imagen, $foroUrl, $gratuito, $incluidoMembresia, $descuentoMiembroPct, $activo, $mostrarCodigoPromocion, $landingPageId);
            }
            if ($stmt->execute()) {
                $itemId = $id ?: $stmt->insert_id;
                $guardarRegaloConfig($itemId, true);
                if ($esNuevo && $activo && !$esAutosave) {
                    notificacion_difundir('nuevo_curso', 'Nuevo curso: ' . $titulo, $descripcion !== '' ? mb_strimwidth(trim(strip_tags($descripcion)), 0, 140, '…') : null, 'index.php?action=curso&slug=' . urlencode($slug));
                }
                if ($esAjax) {
                    echo json_encode($esAutosave ? ['success' => true, 'id' => $itemId] : ['success' => true, 'redirect' => 'cursos.php']);
                    exit;
                }
                header('Location: cursos.php');
                exit;
            }
            $error = '¿El slug ya existe? Prueba con otro.';
            $stmt->close();
        }
    } else {
        $tipoEvento = $_POST['tipo_evento'] ?? 'online';
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
        $fechaFin = $_POST['fecha_fin'] !== '' ? $_POST['fecha_fin'] : null;
        $cupoMaximo = $_POST['cupo_maximo'] !== '' ? (int) $_POST['cupo_maximo'] : null;
        $imagen = (string) $evento['imagen_portada'];
        $foroUrl = trim($_POST['foro_url'] ?? '');
        // normalizar_url_youtube() convierte watch?v=/shorts/live a la forma
        // /embed/ que sí se puede meter en un <iframe> — sin esto, un link
        // "youtube.com/live/..." (común al pegar el link de un directo ya
        // terminado) se guardaba tal cual, y el navegador lo bloqueaba al
        // mostrarlo (esa página manda X-Frame-Options: sameorigin, /embed/ no).
        $videoGrabado = normalizar_url_youtube(trim($_POST['video_grabado_url'] ?? ''));
        $soloMiembros = isset($_POST['solo_miembros']) ? 1 : 0;

        try {
            $imagen = procesar_imagen_form('imagen_portada_file', 'eventos', $imagen);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($error === '' && ($titulo === '' || $fechaInicio === '')) {
            $error = 'Título y fecha de inicio son obligatorios.';
        }

        if ($error === '') {
            $esNuevo = !$id;
            if ($id) {
                $stmt = $conn->prepare('UPDATE eventos SET titulo=?, slug=?, descripcion=?, tipo=?, ubicacion=?, fecha_inicio=?, fecha_fin=?, cupo_maximo=?, precio=?, imagen_portada=?, foro_url=?, video_grabado_url=?, gratuito=?, solo_miembros=?, incluido_membresia=?, descuento_miembro_pct=?, activo=?, mostrar_codigo_promocion=?, landing_page_id=? WHERE id=?');
                $stmt->bind_param('sssssssidsssiiidiiii', $titulo, $slug, $descripcion, $tipoEvento, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $videoGrabado, $gratuito, $soloMiembros, $incluidoMembresia, $descuentoMiembroPct, $activo, $mostrarCodigoPromocion, $landingPageId, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO eventos (titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, fecha_fin, cupo_maximo, precio, imagen_portada, foro_url, video_grabado_url, gratuito, solo_miembros, incluido_membresia, descuento_miembro_pct, activo, mostrar_codigo_promocion, landing_page_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->bind_param('sssssssidsssiiidiii', $titulo, $slug, $descripcion, $tipoEvento, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $videoGrabado, $gratuito, $soloMiembros, $incluidoMembresia, $descuentoMiembroPct, $activo, $mostrarCodigoPromocion, $landingPageId);
            }
            if ($stmt->execute()) {
                $itemId = $id ?: $stmt->insert_id;
                $guardarRegaloConfig($itemId, false);
                // No se difunde si es exclusivo para miembros — igual que en
                // el resto de la plataforma (H02/E01), no se usa contenido de
                // membresía como anzuelo para quien no es miembro.
                if ($esNuevo && $activo && !$soloMiembros && !$esAutosave) {
                    notificacion_difundir('nuevo_evento', 'Nuevo evento: ' . $titulo, $descripcion !== '' ? mb_strimwidth(trim(strip_tags($descripcion)), 0, 140, '…') : null, 'index.php?action=evento&slug=' . urlencode($slug));
                }
                if ($esAjax) {
                    echo json_encode($esAutosave ? ['success' => true, 'id' => $itemId] : ['success' => true, 'redirect' => 'eventos.php']);
                    exit;
                }
                header('Location: eventos.php');
                exit;
            }
            $error = '¿El slug ya existe? Prueba con otro.';
            $stmt->close();
        }
    }
    }
    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$item = $tipo === 'curso' ? $curso : $evento;
$pageTitle = ($id ? 'Editar ' : 'Nuevo ') . ($tipo === 'curso' ? 'curso' : 'evento');
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3"><?= htmlspecialchars($pageTitle) ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mb-4">
  <label class="form-label fw-bold">¿Qué tipo de contenido es?</label>
  <?php if ($id): ?>
    <p class="text-muted small mb-0">
      Este contenido ya existe como <strong><?= $tipo === 'curso' ? 'curso' : 'evento' ?></strong>.
      Para cambiarlo de tipo, ciérralo aquí y usa el botón "Convertir a <?= $tipo === 'curso' ? 'evento' : 'curso' ?>" en la lista de <?= $tipo === 'curso' ? 'cursos.php' : 'eventos.php' ?>.
    </p>
  <?php else: ?>
    <div class="btn-group" role="group">
      <a href="?tipo=curso" class="btn <?= $tipo === 'curso' ? 'btn-dark' : 'btn-outline-secondary' ?>">🎓 Curso</a>
      <a href="?tipo=evento" class="btn <?= $tipo === 'evento' ? 'btn-dark' : 'btn-outline-secondary' ?>">📅 Evento</a>
    </div>
  <?php endif; ?>
</div>

<form method="post" class="row g-3" enctype="multipart/form-data" data-ajax-form>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

  <div class="col-md-6"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?= htmlspecialchars($item['titulo']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Slug (opcional)</label><input class="form-control" name="slug" value="<?= htmlspecialchars($item['slug']) ?>"></div>

  <?php if ($id && $item['slug']): ?>
    <div class="col-12">
      <label class="form-label">Código para landing pages</label>
      <div class="input-group">
        <input type="text" class="form-control" id="codigoEmbed" readonly value="[<?= $tipo ?>:<?= htmlspecialchars($item['slug']) ?>]">
        <button type="button" class="btn btn-outline-secondary" id="btnCopiarCodigoEmbed">Copiar</button>
      </div>
      <p class="form-text mb-0">
        Pega este código en cualquier parte del HTML de una
        <a href="landing_pages.php" target="_blank">landing page</a> — al mostrarse se
        reemplaza por un temario (acordeón) con el título de cada lección PUBLICADA y un
        resumen de su contenido. Si no hay ninguna lección publicada, no se muestra nada ahí.
      </p>
    </div>
  <?php endif; ?>

  <div class="col-12">
    <label class="form-label">Landing comercial (flujo de venta)</label>
    <select class="form-select" name="landing_page_id">
      <option value="">— Ninguna (mostrar esta página de venta normal) —</option>
      <?php foreach ($landingsDisponibles as $landing): ?>
        <option value="<?= (int) $landing['id'] ?>" <?= (int) ($item['landing_page_id'] ?? 0) === (int) $landing['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($landing['titulo']) ?> (<?= htmlspecialchars($landing['slug']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <p class="form-text mb-0">
      Si eliges una landing aquí, cualquier visitante que llegue a la página de este
      <?= $tipo ?> SIN acceso todavía (no comprado, ni incluido por membresía) se redirige
      automáticamente a esa landing en su lugar. Quien ya tiene acceso, y los admins, siguen
      viendo esta página normal.
    </p>
  </div>

  <div class="col-12">
    <label class="form-label">Descripción</label>
    <div id="editorDescripcion" style="background:#fff;height:220px;"></div>
    <textarea name="descripcion" id="descripcionOculta" class="d-none"></textarea>
  </div>

  <?php if ($tipo === 'curso'): ?>
    <div class="col-md-4">
      <label class="form-label">Nivel</label>
      <select class="form-select" name="nivel">
        <?php foreach (['principiante', 'intermedio', 'avanzado'] as $n): ?>
          <option value="<?= $n ?>" <?= $curso['nivel'] === $n ? 'selected' : '' ?>><?= ucfirst($n) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Duración (horas)</label><input type="number" step="0.5" class="form-control" name="duracion_horas" value="<?= htmlspecialchars((string) $curso['duracion_horas']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $curso['precio']) ?>"></div>
    <?php
    $imgPickerId = 'curso';
    $imgPickerCampo = 'imagen_portada_file';
    $imgPickerSubdir = 'cursos';
    $imgPickerActual = (string) $curso['imagen_portada'];
    $imgPickerLabel = 'Imagen de portada';
    include __DIR__ . '/_imagen_picker.php';
    ?>
    <?php
    $foroFieldId = 'curso';
    $foroFieldValor = (string) $curso['foro_url'];
    include __DIR__ . '/_foro_link_field.php';
    ?>
    <?php if ($id): ?>
      <div class="col-12">
        <p class="text-muted small mb-0">
          Las lecciones de este curso se gestionan aparte.
          <a href="lecciones.php?curso_id=<?= (int) $id ?>">Gestionar lecciones</a>.
        </p>
      </div>
    <?php endif; ?>
    <div class="col-md-6 form-check form-switch">
      <input type="checkbox" class="form-check-input" role="switch" name="gratuito" id="gratuito" <?= (int) $curso['gratuito'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="gratuito">Curso gratuito</label>
    </div>
    <div class="col-md-6 form-check form-switch">
      <input type="checkbox" class="form-check-input" role="switch" name="incluido_membresia" id="incluido_membresia" <?= (int) $curso['incluido_membresia'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="incluido_membresia"> Incluido con membresía (los miembros lo obtienen gratis)</label>
    </div>
    <div class="col-md-6">
      <label class="form-label">Descuento para miembros (% opcional)</label>
      <input type="number" step="0.01" class="form-control" name="descuento_miembro_pct" value="<?= htmlspecialchars((string) $curso['descuento_miembro_pct']) ?>" placeholder="Ej. 20 — vacío = sin descuento de miembro">
      <div class="form-text">Solo aplica si NO está marcado "Incluido con membresía" (ese ya lo da gratis).</div>
    </div>
  <?php else: ?>
    <div class="col-md-4">
      <label class="form-label">Tipo</label>
      <select class="form-select" name="tipo_evento">
        <option value="online" <?= $evento['tipo'] === 'online' ? 'selected' : '' ?>>En línea</option>
        <option value="presencial" <?= $evento['tipo'] === 'presencial' ? 'selected' : '' ?>>Presencial</option>
      </select>
    </div>
    <div class="col-md-8"><label class="form-label">Ubicación (dirección o liga de Zoom)</label><input class="form-control" name="ubicacion" value="<?= htmlspecialchars((string) $evento['ubicacion']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Fecha y hora de inicio</label><input type="datetime-local" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $evento['fecha_inicio'], 0, 16))) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Fecha y hora de fin (opcional)</label><input type="datetime-local" class="form-control" name="fecha_fin" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string) $evento['fecha_fin'], 0, 16))) ?>"></div>
    <div class="col-md-4"><label class="form-label">Cupo máximo (opcional)</label><input type="number" class="form-control" name="cupo_maximo" value="<?= htmlspecialchars((string) $evento['cupo_maximo']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Precio (MXN)</label><input type="number" step="0.01" class="form-control" name="precio" value="<?= htmlspecialchars((string) $evento['precio']) ?>"></div>
    <div class="col-md-4 form-check form-switch mt-4">
      <input type="checkbox" class="form-check-input" role="switch" name="gratuito" id="gratuito" <?= (int) $evento['gratuito'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="gratuito">Evento gratuito</label>
    </div>
    <?php
    $imgPickerId = 'evento';
    $imgPickerCampo = 'imagen_portada_file';
    $imgPickerSubdir = 'eventos';
    $imgPickerActual = (string) $evento['imagen_portada'];
    $imgPickerLabel = 'Imagen de portada';
    include __DIR__ . '/_imagen_picker.php';
    ?>
    <?php
    $foroFieldId = 'evento';
    $foroFieldValor = (string) $evento['foro_url'];
    include __DIR__ . '/_foro_link_field.php';
    ?>
    <div class="col-md-6"><label class="form-label">Video grabado (para "pasados/grabados")</label><input class="form-control" name="video_grabado_url" value="<?= htmlspecialchars((string) $evento['video_grabado_url']) ?>" placeholder="https://www.youtube.com/embed/..."></div>
    <div class="col-md-6 form-check form-switch mt-4">
      <input type="checkbox" class="form-check-input" role="switch" name="solo_miembros" id="solo_miembros" <?= (int) $evento['solo_miembros'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="solo_miembros"> Exclusivo para miembros (masterclass)</label>
    </div>
    <div class="col-md-6 form-check form-switch mt-4">
      <input type="checkbox" class="form-check-input" role="switch" name="incluido_membresia" id="incluido_membresia" <?= (int) $evento['incluido_membresia'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="incluido_membresia"> Incluido con membresía (gratis para miembros, los demás lo pueden comprar)</label>
    </div>
    <div class="col-md-6">
      <label class="form-label">Descuento para miembros (% opcional)</label>
      <input type="number" step="0.01" class="form-control" name="descuento_miembro_pct" value="<?= htmlspecialchars((string) $evento['descuento_miembro_pct']) ?>" placeholder="Ej. 20 — vacío = sin descuento de miembro">
      <div class="form-text">Solo aplica si NO es "solo miembros" ni "incluido con membresía" (esos ya dan acceso gratis).</div>
    </div>
  <?php endif; ?>

  <div class="col-md-6 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="activo" id="activo" <?= (int) $item['activo'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="activo">Publicado</label>
  </div>
  <div class="col-md-6 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="mostrar_codigo_promocion" id="mostrar_codigo_promocion" <?= (int) $item['mostrar_codigo_promocion'] === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="mostrar_codigo_promocion">Mostrar campo de "código de cupón" en el checkout</label>
  </div>

  <div class="col-12"><hr></div>
  <div class="col-12">
    <h2 class="h6">🎁 Prestaciones y regalos</h2>
    <p class="text-muted small">Quien tenga acceso a este <?= $tipo === 'curso' ? 'curso' : 'evento' ?> podrá generar enlaces para regalarlo — apagado por default.</p>
  </div>
  <div class="col-12 form-check form-switch">
    <input type="checkbox" class="form-check-input" role="switch" name="regalo_activo" id="regalo_activo" <?= (int) ($regalo['activo'] ?? 0) === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="regalo_activo">Permitir que quien tenga acceso pueda regalarlo</label>
  </div>
  <div class="col-md-3">
    <label class="form-label">Descuento del regalo (%)</label>
    <input type="number" step="0.01" min="0.01" max="100" class="form-control" name="regalo_descuento_pct" value="<?= htmlspecialchars((string) ($regalo['descuento_pct'] ?? 100)) ?>">
    <div class="form-text">100 = acceso completo gratis.</div>
  </div>
  <div class="col-md-3">
    <label class="form-label">Máximo de cuentas que pueden regalar</label>
    <input type="number" min="1" class="form-control" name="regalo_max_usuarios" value="<?= htmlspecialchars((string) ($regalo['max_usuarios_habilitados'] ?? '')) ?>" placeholder="Vacío = sin tope">
  </div>
  <div class="col-md-3">
    <label class="form-label">Enlaces por persona</label>
    <input type="number" min="1" class="form-control" name="regalo_enlaces_por_usuario" value="<?= htmlspecialchars((string) ($regalo['enlaces_por_usuario'] ?? 1)) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Vigencia del enlace (días)</label>
    <input type="number" min="1" class="form-control" name="regalo_vigencia_dias" value="<?= htmlspecialchars((string) ($regalo['vigencia_dias'] ?? '')) ?>" placeholder="Vacío = no vence">
  </div>

  <div class="col-12"><button class="btn btn-success">Guardar</button></div>
</form>

<!-- Editor de texto completo (Quill — ya se usa en el foro, aquí con una
     barra más completa: encabezados, imágenes, etc.) para la Descripción de
     curso/evento. Reusa procesar_subida_imagen() (uploads.php) para las
     imágenes que se insertan dentro del contenido — mismo subdir
     "contenido" para todas, sin importar si es curso o evento. -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css">
<style>
  /* El picker de tamaño de Quill solo trae texto ("Small"/"Large"/"Huge")
     para su propia lista por defecto — con valores en px propios, sin esto
     cae en la regla genérica de abajo y todos se ven como "Normal". */
  .ql-picker.ql-size .ql-picker-label[data-value]:not([data-value=""])::before,
  .ql-picker.ql-size .ql-picker-item[data-value]:not([data-value=""])::before {
    content: attr(data-value);
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
  // Aviso VISIBLE de cualquier error de JS sin capturar en esta página — la
  // vez pasada un bug real en el editor quedó invisible hasta que se abrió
  // la consola a mano (F12) y se copió el error; con esto se nota de
  // inmediato con un toast, en vez de fallar en silencio. Un solo aviso por
  // carga de página, así no inunda de toasts si el mismo error se repite en
  // cada tecla.
  (function () {
    let avisadoDeError = false;
    window.addEventListener('error', function (e) {
      console.error('[Editor] Error no capturado:', e.error || e.message, e);
      if (avisadoDeError || !window.jQuery || !jQuery.notify) return;
      avisadoDeError = true;
      jQuery.notify(
        'Ocurrió un error inesperado en el editor. Abre la consola (F12) y copia el error para reportarlo: ' + (e.message || 'error desconocido'),
        { className: 'error', position: 'top right', autoHide: false }
      );
    });
  })();

  // Tamaño de fuente y alineación con estilo inline (no clases) — así el HTML
  // guardado se ve igual en cualquier página que lo renderice
  // (.pf-contenido-html) sin depender del CSS propio del editor.
  const TamanoEstilo = Quill.import('attributors/style/size');
  TamanoEstilo.whitelist = ['12px', '14px', '16px', '18px', '20px', '24px', '32px', '48px'];
  Quill.register(TamanoEstilo, true);
  Quill.register(Quill.import('attributors/style/align'), true);
  const Delta = Quill.import('delta');

  // Bloque colapsable estilo Notion, como un <details>/<summary> NATIVO del
  // navegador, insertado como un embed OPACO de Quill (ver leccion_form.php,
  // mismo patrón exacto): Quill lo trata como una sola "unidad" en su
  // documento y nunca mira ni toca lo que hay adentro. El título y el cuerpo
  // son sus propias islas contenteditable="true" DENTRO de un contenedor
  // contenteditable="false" — typing/Enter/Backspace/listas ahí dentro son
  // edición nativa del navegador, no del modelo de Quill. Esto reemplaza un
  // intento anterior con formatos de línea propios que competía con el
  // manejo de teclado interno de Quill de forma impredecible.
  const BlockEmbedColapsable = Quill.import('blots/block/embed');
  class ColapsableEmbedBlot extends BlockEmbedColapsable {
    static create(value) {
      const node = super.create();
      node.setAttribute('contenteditable', 'false');
      node.setAttribute('open', '');
      const resumen = document.createElement('summary');
      resumen.setAttribute('contenteditable', 'true');
      resumen.innerHTML = (value && value.titulo) || 'Título del colapsable';
      const cuerpo = document.createElement('div');
      cuerpo.className = 'pf-colapsable-body-editable';
      cuerpo.setAttribute('contenteditable', 'true');
      cuerpo.innerHTML = (value && value.cuerpo) || '<p><br></p>';
      node.appendChild(resumen);
      node.appendChild(cuerpo);
      return node;
    }
    static value(node) {
      const resumen = node.querySelector('summary');
      const cuerpo = node.querySelector('.pf-colapsable-body-editable');
      return {
        titulo: resumen ? resumen.innerHTML : '',
        cuerpo: cuerpo ? cuerpo.innerHTML : '',
      };
    }
  }
  ColapsableEmbedBlot.blotName = 'colapsable-embed';
  ColapsableEmbedBlot.tagName = 'details';
  ColapsableEmbedBlot.className = 'pf-colapsable-embed';
  Quill.register(ColapsableEmbedBlot);

  // Cualquier tecla/entrada dentro del título o el cuerpo del colapsable
  // nunca debe llegarle a Quill — se intercepta en fase de CAPTURA sobre el
  // contenedor (un ancestro de quill.root) para garantizar que se detiene
  // ANTES de que el evento alcance a Quill. Un solo keydown/stopPropagation
  // no bastaba: Quill 2 reacciona a 'beforeinput' (así detecta y aplica lo
  // que el usuario escribió) y también observa la SELECCIÓN del documento
  // completo — al escribir dentro de una isla contenteditable anidada,
  // Quill no sabe traducir esa posición a su propio modelo, la confunde con
  // "todo el embed está seleccionado" y termina borrándolo con la primera
  // tecla (confirmado con un stack trace real: Editor.deleteText llamado
  // desde adentro de quill.js). 'copy'/'cut'/'paste' se agregaron después:
  // Quill los intercepta con su propio módulo de portapapeles
  // (this.quill.root.addEventListener('copy'|'cut'|'paste', ...)) para
  // armar el contenido desde SU modelo de Delta en vez del DOM real —
  // dentro del colapsable ese modelo no ve nada, así que Ctrl+C terminaba
  // copiando vacío aunque la selección nativa sí tuviera el texto correcto
  // (confirmado: getSelection().toString() traía el texto bien, pero el
  // portapapeles llegaba vacío). 'mousedown'/'click' también están en la
  // lista: Quill los escucha en quill.root para su propio manejo de
  // selección/formato del toolbar, y eso interfería con el doble-click
  // nativo del navegador para seleccionar una palabra (confirmado:
  // funcionaba en una página aislada sin Quill, pero no aquí). Frenar
  // TODOS estos tipos de evento aquí es lo que de verdad aísla al
  // colapsable de Quill; edición nativa runs.
  ['beforeinput', 'input', 'compositionstart', 'compositionupdate', 'compositionend', 'keyup', 'keypress', 'copy', 'cut', 'paste', 'mousedown', 'mouseup', 'click', 'dblclick'].forEach(function (tipo) {
    document.getElementById('editorDescripcion').addEventListener(tipo, function (e) {
      if (e.target.closest && e.target.closest('.pf-colapsable-embed')) {
        e.stopPropagation();
      }
    }, true);
  });
  // El keydown se maneja aparte porque además necesita casos especiales
  // dentro del <summary>: por ser un elemento nativamente "interactivo",
  // Espacio y Enter activan su comportamiento propio de abrir/cerrar el
  // <details> en vez de escribir texto — Espacio incluso se "come" el
  // carácter (preventDefault bloquea también la inserción nativa, van
  // pegados), así que se inserta a mano con execCommand. Enter no inserta
  // salto de línea en el título (es de una sola línea) — mueve el cursor al
  // cuerpo, para seguir la expectativa original de "Enter avanza".
  document.getElementById('editorDescripcion').addEventListener('keydown', function (e) {
    if (!(e.target.closest && e.target.closest('.pf-colapsable-embed'))) return;
    const resumen = e.target.closest('summary');
    const cuerpoDelKeydown = e.target.closest('.pf-colapsable-body-editable');
    const isla = resumen || cuerpoDelKeydown;
    if (isla && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
      // "Seleccionar todo" nativo (Ctrl/Cmd+A) no funciona en un
      // contenteditable=true anidado dentro de uno false anidado dentro de
      // otro true — confirmado hasta en una página aislada sin Quill de
      // por medio, es una limitación real del navegador con este triple
      // anidado, no un bug de este código. Se arma la selección a mano,
      // acotada nada más a la isla enfocada (título o cuerpo).
      e.preventDefault();
      const rango = document.createRange();
      rango.selectNodeContents(isla);
      const seleccion = window.getSelection();
      seleccion.removeAllRanges();
      seleccion.addRange(rango);
    } else if (resumen && e.key === ' ') {
      e.preventDefault();
      document.execCommand('insertText', false, ' ');
    } else if (resumen && e.key === 'Enter') {
      e.preventDefault();
      const cuerpo = resumen.parentElement.querySelector('.pf-colapsable-body-editable');
      if (cuerpo) {
        cuerpo.focus();
        const seleccion = window.getSelection();
        const rango = document.createRange();
        rango.selectNodeContents(cuerpo);
        rango.collapse(true);
        seleccion.removeAllRanges();
        seleccion.addRange(rango);
      }
    } else if (cuerpoDelKeydown && e.key === ' ') {
      // Convierte "- "/"* " o "1. " al inicio de una línea del cuerpo en
      // lista con viñetas/numerada — igual que Notion/Quill, pero armado a
      // mano en vez de con document.execCommand('insertUnorderedList'):
      // ese comando devuelve false (no hace nada) dentro de esta isla
      // contenteditable anidada, aparentemente porque el navegador no
      // logra resolver bien el "editing host" cuando hay contenteditable
      // true→false→true encajados (confirmado probando ambas rutas). Una
      // vez que existe un <ul>/<ol>/<li> real, Enter para seguir la lista o
      // salir de ella al dar Enter en un item vacío es comportamiento
      // nativo del navegador — no hace falta más JS para eso.
      const seleccion = window.getSelection();
      if (seleccion.rangeCount) {
        const rango = seleccion.getRangeAt(0);
        if (rango.collapsed) {
          const nodo = rango.startContainer;
          const textoAntes = nodo.nodeType === Node.TEXT_NODE ? nodo.textContent.slice(0, rango.startOffset) : '';
          const esVineta = textoAntes === '-' || textoAntes === '*';
          const numerada = textoAntes.match(/^(\d+)\.$/);
          if (esVineta || numerada) {
            e.preventDefault();
            let linea = nodo.nodeType === Node.TEXT_NODE ? nodo.parentElement : nodo;
            while (linea && linea.parentElement !== cuerpoDelKeydown) {
              linea = linea.parentElement;
            }
            if (linea) {
              rango.setStart(nodo, 0);
              rango.deleteContents();
              const tipoLista = numerada ? 'ol' : 'ul';
              let lista = linea.previousElementSibling;
              if (!lista || lista.tagName.toLowerCase() !== tipoLista) {
                lista = document.createElement(tipoLista);
                linea.parentNode.insertBefore(lista, linea);
              }
              const item = document.createElement('li');
              item.innerHTML = '<br>';
              lista.appendChild(item);
              linea.remove();
              const nuevoRango = document.createRange();
              nuevoRango.selectNodeContents(item);
              nuevoRango.collapse(true);
              seleccion.removeAllRanges();
              seleccion.addRange(nuevoRango);
            }
          }
        }
      }
    }
    e.stopPropagation();
  }, true);

  // Poder anidar un colapsable dentro de otro necesita saber en qué
  // colapsable/qué punto exacto estaba el cursor justo ANTES de que el
  // clic en el botón de la barra mueva el foco fuera (ver el mismo
  // mecanismo, con comentario completo, en leccion_form.php).
  let ultimoContextoColapsable = null;
  document.getElementById('editorDescripcion').addEventListener('mouseup', actualizarContextoColapsable, true);
  document.getElementById('editorDescripcion').addEventListener('keyup', actualizarContextoColapsable, true);
  function actualizarContextoColapsable(e) {
    const isla = e.target.closest && (e.target.closest('summary') || e.target.closest('.pf-colapsable-body-editable'));
    if (!isla || !isla.closest('.pf-colapsable-embed')) {
      ultimoContextoColapsable = null;
      return;
    }
    const seleccion = window.getSelection();
    if (!seleccion.rangeCount) return;
    ultimoContextoColapsable = { isla: isla, rango: seleccion.getRangeAt(0).cloneRange() };
  }

  function insertarBloqueEnColapsable(elementoNuevo, contextoExplicito) {
    const contexto = contextoExplicito || ultimoContextoColapsable;
    if (!contexto || !contexto.isla.isConnected) return false;
    let cuerpo = contexto.isla.closest('.pf-colapsable-body-editable');
    if (!cuerpo && contexto.isla.tagName === 'SUMMARY') {
      cuerpo = contexto.isla.parentElement.querySelector('.pf-colapsable-body-editable');
    }
    if (!cuerpo) return false;
    let linea = contexto.rango && cuerpo.contains(contexto.rango.startContainer)
      ? (contexto.rango.startContainer.nodeType === Node.TEXT_NODE ? contexto.rango.startContainer.parentElement : contexto.rango.startContainer)
      : null;
    while (linea && linea.parentElement !== cuerpo) {
      linea = linea.parentElement;
    }
    if (linea) {
      linea.parentNode.insertBefore(elementoNuevo, linea.nextSibling);
    } else {
      cuerpo.appendChild(elementoNuevo);
    }
    return true;
  }

  function focusIslaColapsable(el) {
    // Sin collapse(): se deja el placeholder ("Título del colapsable")
    // SELECCIONADO, no solo con el cursor detrás — así la primera tecla que
    // el usuario escriba lo reemplaza en vez de agregarse después.
    el.focus();
    const seleccion = window.getSelection();
    const rango = document.createRange();
    rango.selectNodeContents(el);
    seleccion.removeAllRanges();
    seleccion.addRange(rango);
  }

  Quill.import('ui/icons')['colapsable-embed'] = '▾';
  Quill.import('ui/icons')['undo'] = '↶';
  Quill.import('ui/icons')['redo'] = '↷';

  const quillDescripcion = new Quill('#editorDescripcion', {
    theme: 'snow',
    placeholder: 'Descripción completa — puedes usar encabezados, listas, imágenes, etc.',
    modules: {
      toolbar: {
        container: [
          [{ header: [2, 3, false] }, { size: TamanoEstilo.whitelist }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          [{ align: [] }],
          ['blockquote', 'link', 'image'],
          ['colapsable-embed'],
          ['undo', 'redo'],
          ['clean'],
        ],
        handlers: {
          image: subirImagenDescripcion,
          'colapsable-embed': insertarColapsable,
          undo: function () { quillDescripcion.history.undo(); },
          redo: function () { quillDescripcion.history.redo(); },
        },
      },
    },
  });
  quillDescripcion.root.innerHTML = <?= json_encode((string) $item['descripcion']) ?>;

  // Pegar una imagen (Ctrl+V, o copiada de Word/Google Docs/captura) no pasa
  // por subirImagenDescripcion() de abajo — Quill la pega tal cual como
  // <img src="data:...;base64,...">, que puede pesar varios MB e inflar el
  // POST de guardar/autoguardar hasta que el servidor lo rechace (ver mismo
  // fix en leccion_form.php). Se intercepta, se sube por el mismo endpoint
  // que ya usa el botón de imagen del toolbar, y se reemplaza por una URL
  // normal en cuanto termina.
  quillDescripcion.clipboard.addMatcher('IMG', function (node, delta) {
    const src = node.getAttribute('src') || '';
    if (!src.startsWith('data:')) {
      return delta;
    }
    subirImagenPegadaDescripcion(src);
    return new Delta();
  });

  async function subirImagenPegadaDescripcion(dataUrl) {
    try {
      const blob = await (await fetch(dataUrl)).blob();
      const extension = (blob.type.split('/')[1] || 'png').split('+')[0];
      const datos = new FormData();
      datos.append('imagen', blob, 'pegado.' + extension);
      datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
      const res = await fetch('../../backend/quill_imagen_subir.php', { method: 'POST', body: datos });
      const data = await res.json();
      if (data.success) {
        const rango = quillDescripcion.getSelection(true) || { index: quillDescripcion.getLength() };
        quillDescripcion.insertEmbed(rango.index, 'image', data.url, 'user');
      } else {
        $.notify(data.message || 'No se pudo subir una imagen pegada.', { className: 'error', position: 'top right' });
      }
    } catch (e) {
      $.notify('Error de conexión subiendo una imagen pegada.', { className: 'error', position: 'top right' });
    }
  }

  // Inserta un colapsable nuevo en el cursor y enfoca su título para
  // empezar a escribir de inmediato (ver leccion_form.php, mismo patrón).
  function insertarColapsable() {
    const nodoAnidado = ultimoContextoColapsable ? ColapsableEmbedBlot.create({ titulo: '', cuerpo: '' }) : null;
    if (nodoAnidado && insertarBloqueEnColapsable(nodoAnidado)) {
      setTimeout(function () { focusIslaColapsable(nodoAnidado.querySelector('summary')); }, 0);
      return;
    }
    const rango = quillDescripcion.getSelection(true);
    if (!rango) return;
    quillDescripcion.insertEmbed(rango.index, 'colapsable-embed', { titulo: '', cuerpo: '' }, 'user');
    quillDescripcion.setSelection(rango.index + 1, 0, 'user');
    setTimeout(function () {
      const resumenes = quillDescripcion.root.querySelectorAll('.pf-colapsable-embed summary');
      const ultimo = resumenes[resumenes.length - 1];
      if (ultimo) focusIslaColapsable(ultimo);
    }, 0);
  }

  function subirImagenDescripcion() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp,image/gif';
    input.addEventListener('change', async function () {
      const archivo = input.files[0];
      if (!archivo) return;
      const rango = quillDescripcion.getSelection(true);
      const datos = new FormData();
      datos.append('imagen', archivo);
      datos.append('csrf_token', <?= json_encode(csrf_token()) ?>);
      try {
        const res = await fetch('../../backend/quill_imagen_subir.php', { method: 'POST', body: datos });
        const data = await res.json();
        if (data.success) {
          quillDescripcion.insertEmbed(rango.index, 'image', data.url);
          quillDescripcion.setSelection(rango.index + 1);
        } else {
          alert(data.message || 'No se pudo subir la imagen.');
        }
      } catch (e) {
        alert('Error de conexión subiendo la imagen.');
      }
    });
    input.click();
  }

  // Debe fijarse directo en el <form> (no delegado en document, como hace
  // _footer.php) para garantizar que corra ANTES de que ese handler arme el
  // FormData a partir del <textarea> — un listener en el propio elemento
  // siempre dispara antes que uno delegado en un ancestro, sin importar el
  // orden en que se registraron.
  function sincronizarDescripcion() {
    document.getElementById('descripcionOculta').value = quillDescripcion.root.innerHTML;
  }
  document.querySelector('form[data-ajax-form]').addEventListener('submit', sincronizarDescripcion);

  const btnCopiarCodigoEmbed = document.getElementById('btnCopiarCodigoEmbed');
  if (btnCopiarCodigoEmbed) {
    btnCopiarCodigoEmbed.addEventListener('click', async function () {
      const campo = document.getElementById('codigoEmbed');
      try {
        await navigator.clipboard.writeText(campo.value);
      } catch (e) {
        campo.select();
        document.execCommand('copy');
      }
      if (window.jQuery && jQuery.notify) {
        jQuery.notify('Código copiado.', { className: 'success', position: 'top right', autoHideDelay: 2000 });
      }
    });
  }

  // Igual que en leccion_form.php: pequeño retraso para dejar que la
  // asignación inicial de innerHTML (arriba) termine de disparar su propio
  // "text-change" antes de vigilar cambios reales. requiereIdExistente=true
  // porque este formulario no tiene concepto de borrador — el autoguardado
  // solo actualiza un curso/evento YA guardado, nunca crea uno nuevo (eso
  // podría dejarlo visible en el catálogo a medio llenar si "Publicado" ya
  // viene marcado).
  setTimeout(function () {
    PfAutosave.iniciar({
      formSelector: 'form[data-ajax-form]',
      quills: [quillDescripcion],
      campoBandera: 'accion_autosave',
      valorBandera: '1',
      requiereIdExistente: true,
      antesDeGuardar: sincronizarDescripcion,
      onGuardadoOk: function (data) {
        document.querySelector('input[name="id"]').value = data.id;
      },
      verificarSesionUrl: '../../backend/session_check.php',
    });
  }, 300);
</script>
<script src="_autosave.js"></script>
<?php include __DIR__ . '/_footer.php'; ?>
