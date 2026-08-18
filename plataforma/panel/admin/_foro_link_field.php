<?php
// Campo "Enlace al foro" con buscador en vivo — mientras se escribe, muestra
// los títulos de temas ya creados en el foro (reusa el mismo endpoint que
// el buscador público del foro, foro/backend/buscar_ajax.php) y al elegir
// uno arma el link directo a esa discusión (foro/tema.php?id=X). Compartido
// entre contenido_form.php (cursos/eventos) y leccion_form.php — un solo
// lugar para esta UI en vez de repetirla (ver [[feedback_forms_modal_unificados]]).
//
// Variables que espera quien lo incluya:
//   $foroFieldId     string  sufijo único para los ids del DOM (ej. 'curso', 'evento', 'leccion')
//   $foroFieldValor  string  valor ya guardado (URL o vacío)
$foroFieldValor = $foroFieldValor ?? '';
?>
<div class="col-md-6">
  <label class="form-label">Enlace al foro</label>
  <div class="pf-foro-buscador">
    <input type="text" class="form-control" name="foro_url" id="foroUrl_<?= htmlspecialchars($foroFieldId) ?>" value="<?= htmlspecialchars($foroFieldValor) ?>" placeholder="Escribe para buscar un tema del foro, o pega un link" autocomplete="off">
    <div class="pf-foro-dropdown" id="foroDropdown_<?= htmlspecialchars($foroFieldId) ?>"></div>
  </div>
  <div class="form-text">Busca por título para enlazar a una discusión ya creada, o pega cualquier link a mano.</div>
</div>

<?php if (empty($GLOBALS['_foro_link_field_assets_impresos'])): ?>
  <?php $GLOBALS['_foro_link_field_assets_impresos'] = true; ?>
  <style>
    .pf-foro-buscador { position: relative; }
    .pf-foro-dropdown {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 20;
      background: #fff;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      box-shadow: 0 8px 20px rgba(0,0,0,.08);
      margin-top: 4px;
      max-height: 260px;
      overflow-y: auto;
    }
    .pf-foro-dropdown.show { display: block; }
    .pf-foro-dropdown-item {
      display: block;
      padding: 8px 12px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13.5px;
    }
    .pf-foro-dropdown-item:last-child { border-bottom: none; }
    .pf-foro-dropdown-item:hover { background: #f7f7f7; }
    .pf-foro-dropdown-item small { display: block; color: #888; font-size: 11.5px; }
    .pf-foro-dropdown-vacio { padding: 10px 12px; font-size: 13px; color: #888; }
  </style>
  <script>
  function pfInitForoField(id) {
    var input = document.getElementById('foroUrl_' + id);
    var dropdown = document.getElementById('foroDropdown_' + id);
    var timer = null;

    function cerrar() {
      dropdown.classList.remove('show');
      dropdown.innerHTML = '';
    }

    function render(temas) {
      dropdown.innerHTML = '';
      if (!temas.length) {
        var vacio = document.createElement('div');
        vacio.className = 'pf-foro-dropdown-vacio';
        vacio.textContent = 'Sin resultados en el foro.';
        dropdown.appendChild(vacio);
        return;
      }
      temas.forEach(function (t) {
        var item = document.createElement('div');
        item.className = 'pf-foro-dropdown-item';
        var titulo = document.createElement('span');
        titulo.textContent = t.titulo;
        var categoria = document.createElement('small');
        categoria.textContent = t.categoria_nombre;
        item.appendChild(titulo);
        item.appendChild(categoria);
        item.addEventListener('click', function () {
          input.value = 'foro/tema.php?id=' + t.id;
          cerrar();
        });
        dropdown.appendChild(item);
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
        fetch('../../../foro/backend/buscar_ajax.php?q=' + encodeURIComponent(q))
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
      if (e.target !== input && !dropdown.contains(e.target)) cerrar();
    });
  }
  </script>
<?php endif; ?>
<script>pfInitForoField('<?= htmlspecialchars($foroFieldId) ?>');</script>
