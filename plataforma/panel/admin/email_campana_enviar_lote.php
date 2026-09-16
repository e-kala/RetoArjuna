<?php
// Envía un lote de correos de una campaña ya creada (ver email_campana_form.php)
// — el navegador llama este endpoint repetidamente con offset creciente hasta
// cubrir todos los destinatarios, para que un lote nunca sea tan grande como
// para exceder el timeout del servidor.
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/mailer.php';
require_once __DIR__ . '/_email_campanas_segmentos.php';
require_role('admin');
header('Content-Type: application/json');
requerir_csrf_json();

$campanaId = (int) ($_POST['campana_id'] ?? 0);
$offset = max(0, (int) ($_POST['offset'] ?? 0));
$limite = max(1, min(50, (int) ($_POST['limite'] ?? 15)));

$stmt = $conn->prepare('SELECT asunto, cuerpo_html, segmento FROM email_campanas WHERE id = ?');
$stmt->bind_param('i', $campanaId);
$stmt->execute();
$campana = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$campana) {
    echo json_encode(['success' => false, 'mensaje' => 'Campaña no encontrada.']);
    exit;
}

$sqlSegmento = email_campana_segmento_sql($campana['segmento']);
$sqlLote = "SELECT id, email_cache FROM ({$sqlSegmento}) t LIMIT {$limite} OFFSET {$offset}";
$destinatarios = $conn->query($sqlLote)->fetch_all(MYSQLI_ASSOC);

$htmlCorreo = plantilla_email($campana['asunto'], $campana['cuerpo_html']);
$tipoLog = 'campana_' . $campanaId;
$enviadosEnLote = 0;
foreach ($destinatarios as $dest) {
    if (enviar_email((int) $dest['id'], $dest['email_cache'], $campana['asunto'], $htmlCorreo, $tipoLog)) {
        $enviadosEnLote++;
    }
}

if ($enviadosEnLote > 0) {
    $stmtUpd = $conn->prepare('UPDATE email_campanas SET total_enviados = total_enviados + ? WHERE id = ?');
    $stmtUpd->bind_param('ii', $enviadosEnLote, $campanaId);
    $stmtUpd->execute();
    $stmtUpd->close();
}

echo json_encode(['success' => true, 'enviados_en_lote' => $enviadosEnLote, 'procesados_en_lote' => count($destinatarios)]);
