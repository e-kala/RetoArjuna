<footer class="pf-footer">
  <div class="pf-container">
    <nav class="pf-footer-links">
      <?php foreach (obtener_navbar_links('footer') as $footerLink): ?>
        <a href="<?= htmlspecialchars(navbar_href($footerLink['url'], '../')) ?>" <?= (int) $footerLink['abre_nueva_pestana'] === 1 ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($footerLink['texto']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="pf-footer-social">
      <img src="img/logoIskon.png" alt="ISKCON">
      <img src="img/logoKWM/LOGO-KRISHNA-WESTNEGRO.gif" alt="Krishna West México">
      <a href="https://www.facebook.com/KrsnaWestMexico/" aria-label="Facebook">Facebook</a>
      <a href="https://www.tiktok.com/@krishnaenmexico/" aria-label="TikTok">TikTok</a>
      <a href="https://www.youtube.com/@krishnawestmexico" aria-label="YouTube">YouTube</a>
    </div>
    <p class="pf-footer-copy">&copy; 2026 Reto Arjuna</p>
  </div>
</footer>
