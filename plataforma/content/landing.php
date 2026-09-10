<?php
// Landing page subida por el admin como HTML (panel/admin/landing_form.php)
// — se muestra envuelta en el navbar/footer de siempre (ya los pone
// plataforma/index.php alrededor de este include), con solo el contenido
// subido en medio. El HTML no se escapa/sanitiza: ya se validó como
// contenido de confianza al subirse (solo un admin puede llegar ahí).
require_once __DIR__ . '/../backend/embeds.php';
$slug = $_GET['slug'] ?? '';
$stmt = $conn->prepare('SELECT * FROM landing_pages WHERE slug = ? AND activo = 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$landing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$landing) {
    echo '<div class="container" style="margin-top:143px;margin-bottom:60px;"><p>Página no encontrada.</p></div>';
    return;
}

echo reemplazar_embeds_curso_evento($conn, (string) $landing['contenido_html']);
