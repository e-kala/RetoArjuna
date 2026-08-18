<?php
$slug = $_GET['slug'] ?? '';
$stmt = $conn->prepare('SELECT * FROM noticias WHERE slug = ? AND activo = 1 AND publicada_at <= NOW() LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$noticia = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$noticia) {
    echo '<div class="container" style="margin-top:143px;"><p>Noticia no encontrada.</p></div>';
    return;
}
?>
<section class="pf-section">
  <div class="pf-container" style="max-width:760px;">
    <a href="?action=noticias" class="d-inline-block mb-3">&larr; Volver a noticias</a>
    <p style="color:var(--pf-muted);font-size:13px;margin-bottom:6px;"><?= htmlspecialchars(date('d/m/Y', strtotime($noticia['publicada_at']))) ?></p>
    <h1 style="font-size:30px;font-weight:800;margin-bottom:20px;"><?= htmlspecialchars($noticia['titulo']) ?></h1>
    <img src="<?= htmlspecialchars($noticia['imagen'] ?: BASE_URL . '/../banner.png') ?>" alt="" style="width:100%;border-radius:16px;margin-bottom:24px;">
    <div style="font-size:16px;line-height:1.7;"><?= $noticia['contenido'] ?></div>
  </div>
</section>
