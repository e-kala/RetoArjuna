<?php
// Footer único para todo el sitio (raíz, plataforma/, panel/ y foro/) — mismo
// patrón que content/navbar.php: quien incluye este archivo define, ANTES
// del include, esta variable opcional:
//   $navPrefijo  ruta relativa hasta la raíz del sitio: '' en index.php (raíz),
//                '../' desde plataforma/ o foro/inc/, '../../' desde panel/.
$navPrefijo = $navPrefijo ?? '';
$usuarioFooter = current_user();
?>
<footer class="pf-footer">
  <div class="pf-container">
    <nav class="pf-footer-links">
      <?php foreach (obtener_navbar_links('footer') as $footerLink): ?>
        <a href="<?= htmlspecialchars(navbar_href($footerLink['url'], $navPrefijo)) ?>" <?= (int) $footerLink['abre_nueva_pestana'] === 1 ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($footerLink['texto']) ?></a>
      <?php endforeach; ?>
      <?php if (!$usuarioFooter): ?>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=ingreso">Iniciar sesión</a>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=registro">Crear Mi Cuenta</a>
      <?php endif; ?>
    </nav>
    <div class="pf-footer-contacto">
      <span class="pf-footer-contacto-label">¿Dudas o quieres platicar?</span>
      <a href="https://wa.me/5213321868372?text=Hola%2C%20tengo%20dudas%20sobre%20Reto%20Arjuna" target="_blank" rel="noopener">
        <i class="bi bi-whatsapp"></i> Escríbenos por WhatsApp
      </a>
    </div>
    <div class="pf-footer-social">
      <img src="<?= htmlspecialchars($navPrefijo) ?>plataforma/img/logoIskon.png" alt="ISKCON">
      <img src="<?= htmlspecialchars($navPrefijo) ?>plataforma/img/logoKWM/LOGO-KRISHNA-WESTNEGRO.gif" alt="Krishna West México">
      <a href="https://www.facebook.com/KrsnaWestMexico/" target="_blank" rel="noopener" aria-label="Facebook">Facebook</a>
      <a href="https://www.tiktok.com/@krishnaenmexico/" target="_blank" rel="noopener" aria-label="TikTok">TikTok</a>
      <a href="https://www.youtube.com/@krishnawestmexico" target="_blank" rel="noopener" aria-label="YouTube">YouTube</a>
    </div>
    <p class="pf-footer-copy">&copy; <?= date('Y') ?> Reto Arjuna</p>
  </div>
</footer>
