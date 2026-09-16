<?php
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_once __DIR__ . '/_email_campanas_segmentos.php';
require_role('admin');
requerir_csrf_form();

$segmentoLabel = $GLOBALS['email_campana_segmento_label'];

// El envío real NO ocurre aquí — este POST solo crea la fila de la campaña
// (asunto/mensaje/segmento ya fijos, no se pueden editar después de esto) y
// el navegador la manda en lotes pequeños a email_campana_enviar_lote.php,
// sin límite de tiempo por lote y con progreso visible — con listas grandes,
// un solo envío síncrono podía exceder el timeout del servidor y cortarse a
// la mitad sin terminar de avisarle a todos.
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esAjax = es_peticion_ajax();
    $asunto = trim($_POST['asunto'] ?? '');
    $mensajeHtml = trim($_POST['mensaje_html'] ?? '');
    $segmento = $_POST['segmento'] ?? 'todos';
    if (!isset($segmentoLabel[$segmento])) {
        $segmento = 'todos';
    }

    if ($asunto === '' || $mensajeHtml === '') {
        $error = 'El asunto y el mensaje son obligatorios.';
    } else {
        $miId = (int) $_SESSION['usuario_perfil_id'];
        $totalDestinatarios = email_campana_contar($conn, $segmento);

        $stmtCampana = $conn->prepare('INSERT INTO email_campanas (asunto, cuerpo_html, segmento, creado_por, total_destinatarios) VALUES (?, ?, ?, ?, ?)');
        $stmtCampana->bind_param('sssii', $asunto, $mensajeHtml, $segmento, $miId, $totalDestinatarios);
        $stmtCampana->execute();
        $campanaId = $stmtCampana->insert_id;
        $stmtCampana->close();

        if ($esAjax) {
            echo json_encode(['success' => true, 'campana_id' => $campanaId, 'total' => $totalDestinatarios]);
            exit;
        }
    }

    if ($esAjax && $error !== '') {
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit;
    }
}

$conteosPorSegmento = [];
foreach (array_keys($segmentoLabel) as $seg) {
    $conteosPorSegmento[$seg] = email_campana_contar($conn, $seg);
}

$pageTitle = 'Nueva campaña de correo';
include __DIR__ . '/_header.php';
?>
<h1 class="h4 mb-3">Nueva campaña de correo</h1>
<p class="text-muted small">
  Se enviará por correo real de inmediato al segmento elegido, en lotes pequeños con progreso visible. Revisa bien
  antes de mandar — no hay forma de cancelar un envío ya en curso.
</p>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="post" id="formCampana" class="row g-3" style="max-width:640px;">
  <?= csrf_field() ?>
  <div class="col-12">
    <label class="form-label">Enviar a</label>
    <select class="form-select" name="segmento" id="selectSegmento" required>
      <?php foreach ($segmentoLabel as $seg => $etiqueta): ?>
        <option value="<?= htmlspecialchars($seg) ?>" data-conteo="<?= (int) $conteosPorSegmento[$seg] ?>">
          <?= htmlspecialchars($etiqueta) ?> (<?= (int) $conteosPorSegmento[$seg] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <div class="form-text">Destinatarios: <strong id="conteoSegmento"><?= (int) $conteosPorSegmento['todos'] ?></strong></div>
  </div>
  <div class="col-12">
    <label class="form-label">Asunto</label>
    <input class="form-control" name="asunto" maxlength="200" required autofocus>
  </div>
  <div class="col-12">
    <label class="form-label">Mensaje (HTML permitido)</label>
    <textarea class="form-control" name="mensaje_html" rows="8" required></textarea>
  </div>
  <div class="col-12">
    <button type="submit" id="btnEnviarCampana" class="btn btn-success">Enviar campaña</button>
    <a href="email_campanas.php" class="btn btn-outline-secondary">Cancelar</a>
  </div>
  <div class="col-12 d-none" id="bloqueProgreso">
    <div class="progress" style="height:20px;">
      <div class="progress-bar" id="barraProgreso" role="progressbar" style="width:0%">0%</div>
    </div>
    <p class="small text-muted mt-1" id="textoProgreso"></p>
  </div>
</form>
<script>
  var select = document.getElementById('selectSegmento');
  var conteo = document.getElementById('conteoSegmento');
  select.addEventListener('change', function () {
    conteo.textContent = select.options[select.selectedIndex].dataset.conteo;
  });

  var TAMANO_LOTE = 15;

  document.getElementById('formCampana').addEventListener('submit', async function (e) {
    e.preventDefault();
    var total = parseInt(conteo.textContent, 10);
    if (!confirm('¿Enviar este correo a ' + total + ' destinatario(s)? Esta acción no se puede deshacer.')) return;

    var $form = this;
    var boton = document.getElementById('btnEnviarCampana');
    var bloqueProgreso = document.getElementById('bloqueProgreso');
    var barra = document.getElementById('barraProgreso');
    var textoProgreso = document.getElementById('textoProgreso');
    boton.disabled = true;
    bloqueProgreso.classList.remove('d-none');

    // Paso 1: crear la campaña (sin enviar nada todavía).
    var resCrear = await fetch($form.action || window.location.href, {
      method: 'POST',
      body: new FormData($form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).catch(function () { return null; });
    if (!resCrear) {
      textoProgreso.textContent = 'No se pudo crear la campaña — revisa tu conexión.';
      boton.disabled = false;
      return;
    }
    var dataCrear = await resCrear.json();
    if (!dataCrear.success) {
      textoProgreso.textContent = dataCrear.mensaje || 'No se pudo crear la campaña.';
      boton.disabled = false;
      return;
    }

    // Paso 2: pedir el envío en lotes hasta terminar.
    var campanaId = dataCrear.campana_id;
    var totalReal = dataCrear.total;
    var enviados = 0;
    var offset = 0;
    while (offset < totalReal) {
      var resLote = await fetch('email_campana_enviar_lote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
          campana_id: campanaId,
          offset: offset,
          limite: TAMANO_LOTE,
          csrf_token: <?= json_encode(csrf_token()) ?>,
        }),
      }).catch(function () { return null; });
      if (!resLote) {
        textoProgreso.textContent = 'Se envió parcialmente (' + enviados + ' de ' + totalReal + ') — se perdió la conexión. Puedes volver a intentar más tarde (los ya enviados no se repiten aquí, pero tampoco se descartan del conteo).';
        break;
      }
      var dataLote = await resLote.json();
      if (!dataLote.success) {
        textoProgreso.textContent = dataLote.mensaje || 'Error enviando el lote.';
        break;
      }
      enviados += dataLote.enviados_en_lote;
      offset += TAMANO_LOTE;
      var pct = Math.min(100, Math.round((Math.min(offset, totalReal) / totalReal) * 100));
      barra.style.width = pct + '%';
      barra.textContent = pct + '%';
      textoProgreso.textContent = 'Enviados ' + Math.min(offset, totalReal) + ' de ' + totalReal + '…';
    }

    if (offset >= totalReal) {
      textoProgreso.textContent = '¡Listo! ' + enviados + ' de ' + totalReal + ' correos entregados.';
      setTimeout(function () {
        window.location.href = 'email_campanas.php?enviada=1&total=' + enviados + '&de=' + totalReal;
      }, 1200);
    } else {
      boton.disabled = false;
    }
  });
</script>
<?php include __DIR__ . '/_footer.php'; ?>
