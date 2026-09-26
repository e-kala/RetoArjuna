<?php
// Snippet de tracking de Matomo (analítica self-hosted, sin cookies por
// defecto, no invasiva). Cada entorno (local/pruebas.arjuna.mx/producción)
// tiene su propia instalación de Matomo con su propia URL/Site ID — ver
// MATOMO_URL/MATOMO_SITE_ID en config.local.php (plantilla en
// config.example.php). Si no están definidas, no imprime nada — el sitio
// funciona igual sin analítica instalada, mismo criterio que
// EMAIL_SMTP_HOST/WHATSAPP_TOKEN en el resto del proyecto.

/**
 * Imprime el <script> de tracking de Matomo, o nada si el entorno no lo
 * tiene configurado. Se llama justo antes de </body> — mismo lugar en los
 * 3 puntos de cierre del sitio (content/footer.php, foro/inc/footer.php,
 * panel/index.php y panel/dashboard.php).
 */
function matomo_snippet(): void
{
    if (!defined('MATOMO_URL') || MATOMO_URL === '' || !defined('MATOMO_SITE_ID') || MATOMO_SITE_ID === '') {
        return;
    }
    $url = htmlspecialchars(MATOMO_URL, ENT_QUOTES);
    $siteId = htmlspecialchars(MATOMO_SITE_ID, ENT_QUOTES);
    ?>
    <!-- Matomo -->
    <script>
      var _paq = window._paq = window._paq || [];
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      (function () {
        var u = <?= json_encode($url) ?>;
        _paq.push(['setTrackerUrl', u + 'matomo.php']);
        _paq.push(['setSiteId', <?= json_encode($siteId) ?>]);
        var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
        g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
      })();
    </script>
    <!-- End Matomo Code -->
    <?php
}
