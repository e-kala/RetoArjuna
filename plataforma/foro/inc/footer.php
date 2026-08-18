    </div>
  </main>

  <footer class="pf-footer">
    <div class="pf-container">
      <nav class="pf-footer-links">
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=cursos">Cursos</a>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=eventos">Eventos</a>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php?action=tienda">Tienda</a>
        <a href="index.php">Foro</a>
        <a href="../../reto-arjuna.html">Reto Arjuna</a>
      </nav>
      <p class="pf-footer-copy">&copy; 2026 Reto Arjuna</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
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
          var cat = document.createElement('span');
          cat.className = 'pf-search-dropdown-cat';
          cat.textContent = t.categoria_nombre;
          a.appendChild(titulo);
          a.appendChild(cat);
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
</body>
</html>
