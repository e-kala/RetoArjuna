<?php
// Plantillas personalizables de los correos transaccionales automáticos —
// ver email_plantilla_personalizada() en mailer.php: sin fila aquí (o con
// activo=0), el correo se manda con el texto default hardcodeado en
// mailer.php, así que esta tabla es siempre un override opcional, nunca la
// única fuente de verdad.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_role('admin');
requerir_csrf_form();

// Defaults (texto actual hardcodeado en mailer.php) — se muestran en el
// formulario cuando no hay fila personalizada todavía, para que el admin
// parta de lo que ya existe en vez de una caja vacía.
$defaults = [
    'bienvenida' => [
        'etiqueta' => 'Bienvenida (cuenta nueva)',
        'asunto' => 'Bienvenido a Reto Arjuna',
        'titulo' => '¡Bienvenido a Reto Arjuna!',
        'mensaje_html' => '<p>Tu cuenta ya está lista. Explora el catálogo de cursos cuando quieras.</p>',
        'boton_texto' => 'Ver cursos',
        'placeholders' => [],
    ],
    'inscripcion' => [
        'etiqueta' => 'Inscripción confirmada (curso/evento/producto)',
        'asunto' => 'Inscripción confirmada',
        'titulo' => '¡Tu pago fue confirmado!',
        'mensaje_html' => '<p>Tu acceso a <strong>%curso%</strong> ya está activo.</p>',
        'boton_texto' => 'Ir al curso',
        'placeholders' => ['%curso%' => 'título del curso/evento/producto'],
    ],
    'membresia' => [
        'etiqueta' => 'Membresía activada',
        'asunto' => 'Tu membresía Camino Arjuna está activa',
        'titulo' => '¡Tu membresía ya está activa!',
        'mensaje_html' => '<p>Tu membresía <strong>%membresia%</strong> fue activada — ya tienes acceso a todos los cursos y a los eventos exclusivos para miembros.</p>',
        'boton_texto' => 'Entrar a mi membresía',
        'placeholders' => ['%membresia%' => 'nombre de la membresía'],
    ],
    'recuperar_contrasena' => [
        'etiqueta' => 'Recuperar contraseña',
        'asunto' => 'Recupera tu contraseña — Reto Arjuna',
        'titulo' => 'Recupera tu contraseña',
        'mensaje_html' => '<p>Recibimos una solicitud para restablecer tu contraseña. Si no fuiste tú, ignora este correo.</p><p>El enlace vence en 1 hora.</p>',
        'boton_texto' => 'Restablecer contraseña',
        'placeholders' => [],
    ],
    'finalizacion' => [
        'etiqueta' => 'Curso completado (certificado)',
        'asunto' => 'Certificado emitido',
        'titulo' => '¡Completaste el curso!',
        'mensaje_html' => '<p>Terminaste <strong>%curso%</strong>. Tu certificado ya está disponible.</p>',
        'boton_texto' => 'Ver certificado',
        'placeholders' => ['%curso%' => 'título del curso'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $esAjax = es_peticion_ajax();
    $tipo = $_POST['tipo'] ?? '';
    if (!isset($defaults[$tipo])) {
        if ($esAjax) { echo json_encode(['success' => false, 'mensaje' => 'Tipo de correo desconocido.']); exit; }
        header('Location: email_plantillas.php');
        exit;
    }
    $asunto = trim($_POST['asunto'] ?? '');
    $titulo = trim($_POST['titulo'] ?? '');
    $mensajeHtml = trim($_POST['mensaje_html'] ?? '');
    $botonTexto = trim($_POST['boton_texto'] ?? '');
    if ($asunto === '' || $titulo === '' || $mensajeHtml === '') {
        if ($esAjax) { echo json_encode(['success' => false, 'mensaje' => 'Asunto, título y mensaje son obligatorios.']); exit; }
        header('Location: email_plantillas.php');
        exit;
    }
    $stmt = $conn->prepare(
        'INSERT INTO email_plantillas (tipo, asunto, titulo, mensaje_html, boton_texto, activo) VALUES (?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE asunto = VALUES(asunto), titulo = VALUES(titulo), mensaje_html = VALUES(mensaje_html), boton_texto = VALUES(boton_texto), activo = 1'
    );
    $stmt->bind_param('sssss', $tipo, $asunto, $titulo, $mensajeHtml, $botonTexto);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) { echo json_encode(['success' => true, 'mensaje' => 'Plantilla guardada.']); exit; }
    header('Location: email_plantillas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'restablecer') {
    $esAjax = es_peticion_ajax();
    $tipo = $_POST['tipo'] ?? '';
    $stmt = $conn->prepare('DELETE FROM email_plantillas WHERE tipo = ?');
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $stmt->close();
    if ($esAjax) { echo json_encode(['success' => true, 'mensaje' => 'Se restableció al texto original.']); exit; }
    header('Location: email_plantillas.php');
    exit;
}

$personalizadas = $conn->query('SELECT * FROM email_plantillas')->fetch_all(MYSQLI_ASSOC);
$personalizadasPorTipo = array_column($personalizadas, null, 'tipo');

$pageTitle = 'Plantillas de correo';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Plantillas de correo</h1>
<p class="text-muted small">
  Personaliza el texto de los correos automáticos que la plataforma envía. Si no editas una plantilla, se sigue
  usando el texto original. El diseño (colores, logo, botón) es el mismo para todos — aquí solo se edita el
  contenido.
</p>

<div class="accordion" id="acordeonPlantillas">
  <?php foreach ($defaults as $tipo => $def): ?>
    <?php
    $personalizada = $personalizadasPorTipo[$tipo] ?? null;
    $valorAsunto = $personalizada['asunto'] ?? $def['asunto'];
    $valorTitulo = $personalizada['titulo'] ?? $def['titulo'];
    $valorMensaje = $personalizada['mensaje_html'] ?? $def['mensaje_html'];
    $valorBoton = $personalizada['boton_texto'] ?? $def['boton_texto'];
    ?>
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colapso-<?= $tipo ?>">
          <?= htmlspecialchars($def['etiqueta']) ?>
          <?php if ($personalizada): ?><span class="badge bg-success ms-2">Personalizado</span><?php else: ?><span class="badge bg-secondary ms-2">Original</span><?php endif; ?>
        </button>
      </h2>
      <div id="colapso-<?= $tipo ?>" class="accordion-collapse collapse" data-bs-parent="#acordeonPlantillas">
        <div class="accordion-body">
          <form method="post" data-ajax-form>
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
            <div class="mb-3">
              <label class="form-label">Asunto del correo</label>
              <input class="form-control" name="asunto" value="<?= htmlspecialchars($valorAsunto, ENT_QUOTES) ?>" maxlength="200" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Título dentro del correo</label>
              <input class="form-control" name="titulo" value="<?= htmlspecialchars($valorTitulo, ENT_QUOTES) ?>" maxlength="200" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Mensaje (HTML permitido)</label>
              <textarea class="form-control" name="mensaje_html" rows="4" required><?= htmlspecialchars($valorMensaje) ?></textarea>
              <?php if ($def['placeholders']): ?>
                <div class="form-text">
                  Puedes usar: <?php foreach ($def['placeholders'] as $ph => $desc): ?><code><?= htmlspecialchars($ph) ?></code> (<?= htmlspecialchars($desc) ?>) <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label">Texto del botón</label>
              <input class="form-control" name="boton_texto" value="<?= htmlspecialchars((string) $valorBoton, ENT_QUOTES) ?>" maxlength="80">
            </div>
            <button class="btn btn-success btn-sm">Guardar</button>
          </form>
          <?php if ($personalizada): ?>
            <form method="post" data-ajax-form data-confirm="¿Restablecer al texto original? Se pierde la personalización." class="mt-2">
              <?= csrf_field() ?>
              <input type="hidden" name="accion" value="restablecer">
              <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
              <button class="btn btn-outline-secondary btn-sm">Restablecer al original</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
