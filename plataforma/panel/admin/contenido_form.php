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
           'imagen_portada' => '', 'foro_url' => '', 'video_grabado_url' => '', 'mostrar_en_cursos' => 0, 'gratuito' => 0,
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
// nunca activa por default. "Regalar descuento" y "Regalar acceso" son
// prestaciones independientes; "Regalar descuento" además admite varios
// tipos de % (regalo['tipos_descuento'], una fila de tabla por cada uno).
$regaloDefault = ['activo_descuento' => 0, 'activo_acceso' => 0, 'acceso_max_usuarios_habilitados' => '', 'acceso_enlaces_por_usuario' => 1, 'acceso_vigencia_dias' => '', 'tipos_descuento' => []];
$regalo = $regaloDefault;
if ($id) {
    $regalo = $tipo === 'curso'
        ? regalo_configuracion_obtener($id, null)
        : regalo_configuracion_obtener(null, $id);
    $regalo = $regalo ?: $regaloDefault;
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

    // "Regalar descuento" y "Regalar acceso" son dos prestaciones
    // independientes (cada una con su interruptor y límites propios) — ver
    // backend/regalos.php. "Regalar descuento" además admite varios tipos de
    // % (uno por fila del formulario, ver regalo_tipos[] más abajo).
    $regaloActivoDescuento = isset($_POST['regalo_activo_descuento']) ? 1 : 0;
    $regaloActivoAcceso = isset($_POST['regalo_activo_acceso']) ? 1 : 0;
    $regaloAccesoMaxUsuarios = trim($_POST['regalo_acceso_max_usuarios'] ?? '') !== '' ? (int) $_POST['regalo_acceso_max_usuarios'] : null;
    $regaloAccesoEnlacesPorUsuario = max(1, (int) ($_POST['regalo_acceso_enlaces_por_usuario'] ?? 1));
    $regaloAccesoVigenciaDias = trim($_POST['regalo_acceso_vigencia_dias'] ?? '') !== '' ? (int) $_POST['regalo_acceso_vigencia_dias'] : null;

    // Cada fila enviada por el formulario es un tipo de descuento distinto —
    // ids negativos/vacíos ("nuevo-N") se insertan, ids existentes se
    // actualizan, y cualquier id existente que ya NO venga en el POST se
    // borra (el admin lo quitó con el botón de la fila).
    $regaloTiposDescuento = [];
    $tiposPct = $_POST['regalo_tipo_descuento_pct'] ?? [];
    $tiposId = $_POST['regalo_tipo_id'] ?? [];
    $tiposMax = $_POST['regalo_tipo_max_usuarios'] ?? [];
    $tiposEnlaces = $_POST['regalo_tipo_enlaces_por_usuario'] ?? [];
    $tiposVigencia = $_POST['regalo_tipo_vigencia_dias'] ?? [];
    foreach ($tiposPct as $i => $pctCrudo) {
        if (trim((string) $pctCrudo) === '') {
            continue; // fila vacía (ej. la plantilla de "agregar tipo" sin llenar) — se ignora
        }
        $regaloTiposDescuento[] = [
            'id' => (int) ($tiposId[$i] ?? 0) ?: null,
            'descuento_pct' => min(99.99, max(0.01, (float) $pctCrudo)),
            'max_usuarios_habilitados' => trim((string) ($tiposMax[$i] ?? '')) !== '' ? (int) $tiposMax[$i] : null,
            'enlaces_por_usuario' => max(1, (int) ($tiposEnlaces[$i] ?? 1)),
            'vigencia_dias' => trim((string) ($tiposVigencia[$i] ?? '')) !== '' ? (int) $tiposVigencia[$i] : null,
        ];
    }

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
     * Upsert de regalo_configuracion + sus tipos de descuento para el
     * curso/evento recién guardado — compartido entre ambas ramas de abajo.
     */
    $guardarRegaloConfig = function (int $itemId, bool $esCurso) use (
        $conn, $regaloActivoDescuento, $regaloActivoAcceso,
        $regaloAccesoMaxUsuarios, $regaloAccesoEnlacesPorUsuario, $regaloAccesoVigenciaDias,
        $regaloTiposDescuento
    ): void {
        $columna = $esCurso ? 'curso_id' : 'evento_id';
        $stmt = $conn->prepare("SELECT id FROM regalo_configuracion WHERE {$columna} = ?");
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($fila) {
            $configId = (int) $fila['id'];
            $stmt = $conn->prepare('UPDATE regalo_configuracion SET activo_descuento=?, activo_acceso=?, acceso_max_usuarios_habilitados=?, acceso_enlaces_por_usuario=?, acceso_vigencia_dias=? WHERE id=?');
            $stmt->bind_param('iiiiii', $regaloActivoDescuento, $regaloActivoAcceso, $regaloAccesoMaxUsuarios, $regaloAccesoEnlacesPorUsuario, $regaloAccesoVigenciaDias, $configId);
        } else {
            $stmt = $conn->prepare("INSERT INTO regalo_configuracion ({$columna}, activo_descuento, activo_acceso, acceso_max_usuarios_habilitados, acceso_enlaces_por_usuario, acceso_vigencia_dias, descuento_pct) VALUES (?,?,?,?,?,?,0)");
            $stmt->bind_param('iiiiii', $itemId, $regaloActivoDescuento, $regaloActivoAcceso, $regaloAccesoMaxUsuarios, $regaloAccesoEnlacesPorUsuario, $regaloAccesoVigenciaDias);
        }
        $stmt->execute();
        $stmt->close();
        if (!$fila) {
            $configId = $conn->insert_id;
        }

        // Reemplazo total de los tipos de descuento: se borran los que ya no
        // vinieron en el POST (el admin quitó esa fila) y se hace upsert del
        // resto — más simple y confiable que diffear fila por fila, y el
        // volumen por curso/evento es siempre pequeño (unas pocas filas).
        $idsEnviados = array_values(array_filter(array_column($regaloTiposDescuento, 'id')));
        if ($idsEnviados) {
            $placeholders = implode(',', array_fill(0, count($idsEnviados), '?'));
            $tipos = str_repeat('i', count($idsEnviados));
            $stmt = $conn->prepare("DELETE FROM regalo_tipos_descuento WHERE configuracion_id = ? AND id NOT IN ({$placeholders})");
            $stmt->bind_param('i' . $tipos, $configId, ...$idsEnviados);
        } else {
            $stmt = $conn->prepare('DELETE FROM regalo_tipos_descuento WHERE configuracion_id = ?');
            $stmt->bind_param('i', $configId);
        }
        $stmt->execute();
        $stmt->close();

        foreach ($regaloTiposDescuento as $orden => $t) {
            if ($t['id']) {
                $stmt = $conn->prepare('UPDATE regalo_tipos_descuento SET descuento_pct=?, max_usuarios_habilitados=?, enlaces_por_usuario=?, vigencia_dias=?, orden=? WHERE id=? AND configuracion_id=?');
                $stmt->bind_param('diiiiii', $t['descuento_pct'], $t['max_usuarios_habilitados'], $t['enlaces_por_usuario'], $t['vigencia_dias'], $orden, $t['id'], $configId);
            } else {
                $stmt = $conn->prepare('INSERT INTO regalo_tipos_descuento (configuracion_id, descuento_pct, max_usuarios_habilitados, enlaces_por_usuario, vigencia_dias, orden) VALUES (?,?,?,?,?,?)');
                $stmt->bind_param('idiiii', $configId, $t['descuento_pct'], $t['max_usuarios_habilitados'], $t['enlaces_por_usuario'], $t['vigencia_dias'], $orden);
            }
            $stmt->execute();
            $stmt->close();
        }
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
        // Solo tiene efecto real si además hay video_grabado_url — un evento
        // sin grabación no encaja en el catálogo de cursos (se consumiría
        // "bajo demanda" algo que en realidad no existe todavía). No se
        // fuerza aquí a 0 si falta el video (el admin puede subir el video
        // después sin perder la marca), content/cursos_catalogo.php es quien
        // aplica ese filtro al leer.
        $mostrarEnCursos = isset($_POST['mostrar_en_cursos']) ? 1 : 0;

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
                $stmt = $conn->prepare('UPDATE eventos SET titulo=?, slug=?, descripcion=?, tipo=?, ubicacion=?, fecha_inicio=?, fecha_fin=?, cupo_maximo=?, precio=?, imagen_portada=?, foro_url=?, video_grabado_url=?, gratuito=?, solo_miembros=?, incluido_membresia=?, descuento_miembro_pct=?, activo=?, mostrar_codigo_promocion=?, landing_page_id=?, mostrar_en_cursos=? WHERE id=?');
                $stmt->bind_param('sssssssidsssiiidiiiii', $titulo, $slug, $descripcion, $tipoEvento, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $videoGrabado, $gratuito, $soloMiembros, $incluidoMembresia, $descuentoMiembroPct, $activo, $mostrarCodigoPromocion, $landingPageId, $mostrarEnCursos, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO eventos (titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, fecha_fin, cupo_maximo, precio, imagen_portada, foro_url, video_grabado_url, gratuito, solo_miembros, incluido_membresia, descuento_miembro_pct, activo, mostrar_codigo_promocion, landing_page_id, mostrar_en_cursos) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->bind_param('sssssssidsssiiidiiii', $titulo, $slug, $descripcion, $tipoEvento, $ubicacion, $fechaInicio, $fechaFin, $cupoMaximo, $precio, $imagen, $foroUrl, $videoGrabado, $gratuito, $soloMiembros, $incluidoMembresia, $descuentoMiembroPct, $activo, $mostrarCodigoPromocion, $landingPageId, $mostrarEnCursos);
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
    <div class="col-md-6">
      <label class="form-label">Video grabado (para "pasados/grabados")</label>
      <input class="form-control" name="video_grabado_url" id="video_grabado_url" value="<?= htmlspecialchars((string) $evento['video_grabado_url']) ?>" placeholder="https://www.youtube.com/embed/...">
    </div>
    <div class="col-md-6 form-check form-switch mt-4">
      <input type="checkbox" class="form-check-input" role="switch" name="mostrar_en_cursos" id="mostrar_en_cursos" <?= (int) $evento['mostrar_en_cursos'] === 1 ? 'checked' : '' ?> <?= $evento['video_grabado_url'] ? '' : 'disabled' ?>>
      <label class="form-check-label" for="mostrar_en_cursos"> También mostrar en el catálogo de Cursos</label>
      <div class="form-text" id="mostrarEnCursosAyuda"><?= $evento['video_grabado_url'] ? 'Aparecerá también junto a los cursos, enlazando a este mismo evento.' : 'Agrega un video grabado arriba para poder activarlo — solo tiene sentido una vez que se consume bajo demanda.' ?></div>
    </div>
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
    <p class="text-muted small">Quien tenga acceso a este <?= $tipo === 'curso' ? 'curso' : 'evento' ?> podrá generar enlaces para regalarlo.</p>
  </div>

  <div class="col-12">
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <h3 class="h6 mb-1">% Regalar descuento</h3>
            <p class="text-muted small mb-0">Permite generar enlaces con un descuento sobre el precio del <?= $tipo === 'curso' ? 'curso' : 'evento' ?>.</p>
          </div>
          <div class="form-check form-switch flex-shrink-0">
            <input type="checkbox" class="form-check-input" role="switch" name="regalo_activo_descuento" id="regalo_activo_descuento" <?= (int) ($regalo['activo_descuento'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="regalo_activo_descuento">Habilitado</label>
          </div>
        </div>
        <div class="mt-3">
          <p class="fw-bold small mb-1">Descuentos configurados</p>
          <p class="text-muted small">Puedes crear varios tipos de descuento para que el estudiante elija al generar un enlace.</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered bg-white align-middle" id="tablaRegaloTipos">
              <thead><tr><th>Descuento (%)</th><th>Máx. cuentas</th><th>Enlaces por persona</th><th>Vigencia (días)</th><th></th></tr></thead>
              <tbody id="tablaRegaloTiposBody">
                <?php foreach ($regalo['tipos_descuento'] as $i => $t): ?>
                  <tr>
                    <td>
                      <input type="hidden" name="regalo_tipo_id[]" value="<?= (int) $t['id'] ?>">
                      <input type="number" step="0.01" min="0.01" max="99.99" class="form-control form-control-sm" name="regalo_tipo_descuento_pct[]" value="<?= htmlspecialchars((string) $t['descuento_pct']) ?>" required>
                    </td>
                    <td><input type="number" min="1" class="form-control form-control-sm" name="regalo_tipo_max_usuarios[]" value="<?= htmlspecialchars((string) ($t['max_usuarios_habilitados'] ?? '')) ?>" placeholder="Sin tope"></td>
                    <td><input type="number" min="1" class="form-control form-control-sm" name="regalo_tipo_enlaces_por_usuario[]" value="<?= htmlspecialchars((string) $t['enlaces_por_usuario']) ?>"></td>
                    <td><input type="number" min="1" class="form-control form-control-sm" name="regalo_tipo_vigencia_dias[]" value="<?= htmlspecialchars((string) ($t['vigencia_dias'] ?? '')) ?>" placeholder="No vence"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger pf-regalo-tipo-quitar"><i class="bi bi-trash"></i></button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarRegaloTipo"><i class="bi bi-plus-lg"></i> Agregar tipo de descuento</button>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <h3 class="h6 mb-1">🎁 Regalar acceso</h3>
            <p class="text-muted small mb-0">Permite generar enlaces para otorgar acceso completo y gratuito al <?= $tipo === 'curso' ? 'curso' : 'evento' ?>.</p>
          </div>
          <div class="form-check form-switch flex-shrink-0">
            <input type="checkbox" class="form-check-input" role="switch" name="regalo_activo_acceso" id="regalo_activo_acceso" <?= (int) ($regalo['activo_acceso'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="regalo_activo_acceso">Habilitado</label>
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-4">
            <label class="form-label small">Máximo de cuentas que pueden regalar</label>
            <input type="number" min="1" class="form-control form-control-sm" name="regalo_acceso_max_usuarios" value="<?= htmlspecialchars((string) ($regalo['acceso_max_usuarios_habilitados'] ?? '')) ?>" placeholder="Vacío = sin tope">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Enlaces por persona</label>
            <input type="number" min="1" class="form-control form-control-sm" name="regalo_acceso_enlaces_por_usuario" value="<?= htmlspecialchars((string) ($regalo['acceso_enlaces_por_usuario'] ?? 1)) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Vigencia del enlace (días)</label>
            <input type="number" min="1" class="form-control form-control-sm" name="regalo_acceso_vigencia_dias" value="<?= htmlspecialchars((string) ($regalo['acceso_vigencia_dias'] ?? '')) ?>" placeholder="Vacío = no vence">
          </div>
        </div>
      </div>
    </div>
    <div class="alert alert-info small">El estudiante podrá elegir, al generar un enlace, el tipo de descuento que desee o regalar acceso completo.</div>
  </div>

  <template id="plantillaRegaloTipoFila">
    <tr>
      <td><input type="hidden" name="regalo_tipo_id[]" value="0"><input type="number" step="0.01" min="0.01" max="99.99" class="form-control form-control-sm" name="regalo_tipo_descuento_pct[]" required></td>
      <td><input type="number" min="1" class="form-control form-control-sm" name="regalo_tipo_max_usuarios[]" placeholder="Sin tope"></td>
      <td><input type="number" min="1" class="form-control form-control-sm" name="regalo_tipo_enlaces_por_usuario[]" value="1"></td>
      <td><input type="number" min="1" class="form-control form-control-sm" name="regalo_tipo_vigencia_dias[]" placeholder="No vence"></td>
      <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger pf-regalo-tipo-quitar"><i class="bi bi-trash"></i></button></td>
    </tr>
  </template>
  <script>
    (function () {
      const cuerpo = document.getElementById('tablaRegaloTiposBody');
      const plantilla = document.getElementById('plantillaRegaloTipoFila');
      document.getElementById('btnAgregarRegaloTipo').addEventListener('click', function () {
        cuerpo.appendChild(plantilla.content.cloneNode(true));
      });
      cuerpo.addEventListener('click', function (e) {
        const boton = e.target.closest('.pf-regalo-tipo-quitar');
        if (boton) boton.closest('tr').remove();
      });
    })();
  </script>

  <div class="col-12 d-flex gap-2 align-items-center">
    <button class="btn btn-success">Guardar</button>
  </div>
</form>

<!-- Editor de texto completo (Quill — ya se usa en el foro, aquí con una
     barra más completa: encabezados, imágenes, etc.) para la Descripción de
     curso/evento. Reusa procesar_subida_imagen() (uploads.php) para las
     imágenes que se insertan dentro del contenido — mismo subdir
     "contenido" para todas, sin importar si es curso o evento. -->
<script src="../../assets/pf_editor.js?v=3"></script>
<script>
  const editor = PfEditor.crear({
    contenedor: '#editorDescripcion',
    contexto: 'admin',
    placeholder: 'Descripción completa — puedes usar encabezados, listas, imágenes, etc.',
    csrfToken: <?= json_encode(csrf_token()) ?>,
    contenidoInicialHtml: <?= json_encode((string) $item['descripcion']) ?>,
    capacidades: { colapsables: true, tamanoAlineacion: true, undoRedo: true },
  });
  const quillDescripcion = editor.quill;

  function sincronizarDescripcion() {
    document.getElementById('descripcionOculta').value = editor.sincronizar();
  }
  // Corta el guardado (y a sincronizarDescripcion, el siguiente listener) si
  // todavía falta una imagen pegada por subir.
  document.querySelector('form[data-ajax-form]').addEventListener('submit', function (e) {
    if (editor.bloquearSiHayCargasPendientes()) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  });
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

  // Pequeño retraso para dejar que la asignación inicial de
  // contenidoInicialHtml (arriba) termine de disparar su propio
  // "text-change" antes de vigilar cambios reales. requiereIdExistente=true
  // porque este formulario no tiene concepto de borrador — el autoguardado
  // solo actualiza un curso/evento YA guardado, nunca crea uno nuevo (eso
  // podría dejarlo visible en el catálogo a medio llenar si "Publicado" ya
  // viene marcado).
  setTimeout(function () {
    PfEditor.conectarAutosaveServidor(editor, {
      formSelector: 'form[data-ajax-form]',
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

  // El switch "También mostrar en el catálogo de Cursos" solo tiene sentido
  // con un video grabado capturado — se habilita/deshabilita en vivo según
  // el campo de video, sin necesitar recargar. Si se deshabilita, el
  // checkbox no se envía en el POST (comportamiento nativo del navegador),
  // así que el flag se pierde solo si de verdad se quitó el video — no
  // queda un estado inconsistente marcado sin video real que lo respalde.
  (function () {
    const campoVideo = document.getElementById('video_grabado_url');
    const switchMostrarEnCursos = document.getElementById('mostrar_en_cursos');
    const ayuda = document.getElementById('mostrarEnCursosAyuda');
    if (!campoVideo || !switchMostrarEnCursos) return;
    campoVideo.addEventListener('input', function () {
      const hayVideo = campoVideo.value.trim() !== '';
      switchMostrarEnCursos.disabled = !hayVideo;
      if (!hayVideo) switchMostrarEnCursos.checked = false;
      ayuda.textContent = hayVideo
        ? 'Aparecerá también junto a los cursos, enlazando a este mismo evento.'
        : 'Agrega un video grabado arriba para poder activarlo — solo tiene sentido una vez que se consume bajo demanda.';
    });
  })();
</script>
<script src="_autosave.js"></script>
<?php include __DIR__ . '/_footer.php'; ?>
