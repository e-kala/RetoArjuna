    </div>
  </main>

  <?php $navPrefijo = '../../'; require __DIR__ . '/../../content/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
  <script>
  // Notificaciones del foro con notify.js (plataforma/content/notify.min.js,
  // cargado en inc/header.php junto con jQuery) en vez del toast casero de
  // antes — más confiable que reinventar la animación/posicionamiento a mano.
  // Cualquier página del foro puede llamar a pfMostrarToast('texto') desde
  // dentro de un manejador de evento.
  window.pfMostrarToast = function (mensaje) {
    $.notify(mensaje, { className: 'success', position: 'top right', autoHideDelay: 3000 });
  };
  // Para cuando el guardado sí necesita recargar la página (reasignación de
  // categoría o de curso/evento/etiquetas en tema.php): un toast mostrado
  // justo antes de navegar desaparecería con la propia navegación, así que
  // el mensaje viaja como parámetro en la URL de destino — mismo mecanismo
  // ya probado que usa "sesión cerrada" en content/navbar.php (?sesion=
  // cerrada), en vez de sessionStorage (puede venir bloqueado por el propio
  // navegador/extensiones en modos de privacidad estrictos, sin avisar).
  <?php if (!empty($toastMensaje)): ?>
    window.pfMostrarToast(<?= json_encode($toastMensaje) ?>);
    (function () {
      var params = new URLSearchParams(window.location.search);
      params.delete('guardado');
      var resto = params.toString();
      window.history.replaceState({}, '', window.location.pathname + (resto ? '?' + resto : ''));
    })();
  <?php endif; ?>
  </script>
  <script>
  (function () {
    document.querySelectorAll('form[action="buscar.php"]').forEach(function (form) {
      var input = form.querySelector('input[type="search"]');
      if (!input) return;
      form.classList.add('position-relative');

      var dropdown = document.createElement('div');
      dropdown.className = 'dropdown-menu pf-search-dropdown';
      form.appendChild(dropdown);

      var timer = null;

      function cerrar() {
        dropdown.classList.remove('show');
        dropdown.innerHTML = '';
      }

      function render(temas) {
        dropdown.innerHTML = '';
        if (!temas.length) {
          var vacio = document.createElement('span');
          vacio.className = 'dropdown-item-text text-muted small';
          vacio.textContent = 'Sin resultados';
          dropdown.appendChild(vacio);
          return;
        }
        temas.forEach(function (t) {
          var a = document.createElement('a');
          a.className = 'dropdown-item pf-search-dropdown-item';
          a.href = 'tema.php?id=' + encodeURIComponent(t.id);
          var titulo = document.createElement('span');
          titulo.className = 'pf-search-dropdown-titulo';
          titulo.textContent = t.titulo;
          a.appendChild(titulo);
          if (t.etiquetas_nombres) {
            var cat = document.createElement('span');
            cat.className = 'pf-search-dropdown-cat';
            cat.textContent = t.etiquetas_nombres;
            a.appendChild(cat);
          }
          dropdown.appendChild(a);
        });
      }

      input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) {
          cerrar();
          return;
        }
        timer = setTimeout(function () {
          fetch('backend/buscar_ajax.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (input.value.trim() !== q) return;
              render(data.temas || []);
              dropdown.classList.add('show');
            })
            .catch(cerrar);
        }, 200);
      });

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') cerrar();
      });

      document.addEventListener('click', function (e) {
        if (!form.contains(e.target)) cerrar();
      });
    });
  })();
  </script>
  <?php if (current_user()): ?>
  <script src="../content/session_watch.js" data-check-url="../backend/session_check.php"></script>
  <?php endif; ?>
</body>
</html>
