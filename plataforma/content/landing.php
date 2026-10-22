<?php
// Landing page subida por el admin como HTML (panel/admin/landing_form.php)
// — se muestra envuelta en el navbar/footer de siempre (ya los pone
// plataforma/index.php alrededor de este include), con solo el contenido
// subido en medio. El HTML no se escapa/sanitiza: ya se validó como
// contenido de confianza al subirse (solo un admin puede llegar ahí).
require_once __DIR__ . '/../backend/embeds.php';
$slug = $_GET['slug'] ?? '';

// La membresía tiene una landing base versionada junto al proyecto para que
// el enlace del checkout nunca dependa de que exista primero un registro en
// landing_pages. Se sirve como HTML de confianza igual que las landings que
// sube un admin; si el archivo faltara, se conserva el flujo normal de BD.
$landingMembresiaPath = dirname(__DIR__, 2) . '/landing-membresia-camino-arjuna.html';
if ($slug === 'membresia-camino-arjuna' && is_file($landingMembresiaPath)) {
    readfile($landingMembresiaPath);
    return;
}

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
