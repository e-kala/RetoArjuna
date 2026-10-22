<?php
// Selector de imagen compartido: arrastrar-y-soltar / hacer clic para subir,
// o elegir una ya subida antes (evita duplicar la misma imagen muchas veces).
// Se usa en cualquier form del admin que suba una imagen — un solo lugar para
// esta UI en vez de repetir un <input type="file"> distinto en cada form
// (ver [[feedback_forms_modal_unificados]]).
//
// Variables que espera quien lo incluya:
//   $imgPickerId      string  sufijo único para los ids del DOM (ej. 'portada')
//   $imgPickerCampo   string  name del <input type="file"> real (ej. 'imagen_portada_file')
//   $imgPickerSubdir  string  subcarpeta de uploads/ a listar/validar (ej. 'cursos')
//   $imgPickerActual  string  ruta ya guardada (relativa a la raíz del sitio), o ''
//   $imgPickerLabel   string  etiqueta visible
$campoExistente = $imgPickerCampo . '_existente';
$normalizarUrlImagenAdmin = static function (string $ruta): string {
    $ruta = trim($ruta);
    if ($ruta === '' || preg_match('~^https?://~i', $ruta) || str_starts_with($ruta, '/')) {
        return $ruta;
    }
    // Algunas imágenes históricas se guardaron como plataforma/uploads/… y
    // las nuevas como uploads/…. Desde panel/admin, ../../ siempre apunta a
    // la raíz pública de la plataforma. No dependemos de BASE_URL porque en
    // hostings donde DOCUMENT_ROOT es un symlink su cálculo puede quedar vacío
    // y convertir la ruta correcta en /uploads/… (fuera de /plataforma).
    $ruta = preg_replace('~^plataforma/~', '', $ruta);
    return '../../' . ltrim($ruta, '/');
};
$imgPickerActualUrl = $normalizarUrlImagenAdmin((string) $imgPickerActual);
$imgPickerImagenes = array_map(
    static fn (string $ruta): array => ['ruta' => $ruta, 'url' => $normalizarUrlImagenAdmin($ruta)],
    listar_imagenes_subidas($imgPickerSubdir)
);
?>
<div class="col-md-6">
  <label class="form-label"><?= htmlspecialchars($imgPickerLabel) ?></label>
  <input type="hidden" name="<?= htmlspecialchars($campoExistente) ?>" id="imgExistente_<?= htmlspecialchars($imgPickerId) ?>" value="">
  <input type="file" class="d-none" name="<?= htmlspecialchars($imgPickerCampo) ?>" id="imgFile_<?= htmlspecialchars($imgPickerId) ?>" accept="image/png,image/jpeg,image/webp,image/gif">

  <div class="pf-dropzone" id="imgDropzone_<?= htmlspecialchars($imgPickerId) ?>">
    <div id="imgPreview_<?= htmlspecialchars($imgPickerId) ?>" class="pf-dropzone-preview">
      <?php if ($imgPickerActual): ?>
        <img src="<?= htmlspecialchars($imgPickerActualUrl) ?>" alt="">
      <?php else: ?>
        <i class="bi bi-image text-muted" style="font-size:28px;"></i>
      <?php endif; ?>
    </div>
    <div class="pf-dropzone-texto">
      <strong>Arrastra una imagen aquí</strong> o haz clic para buscar en tu computadora
    </div>
  </div>
  <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="imgBtnGaleria_<?= htmlspecialchars($imgPickerId) ?>">
    <i class="bi bi-images"></i> Elegir de las ya subidas
  </button>
  <div class="pf-galeria" id="imgGaleria_<?= htmlspecialchars($imgPickerId) ?>"></div>
</div>

<?php if (empty($GLOBALS['_imagen_picker_assets_impresos'])): ?>
  <?php $GLOBALS['_imagen_picker_assets_impresos'] = true; ?>
  <style>
    .pf-dropzone {
      border: 2px dashed var(--pf-line, #ccc);
      border-radius: var(--pf-radius-md, 10px);
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 14px;
      cursor: pointer;
      background: var(--pf-bg, #fafafa);
      transition: border-color .15s, background .15s;
    }
    .pf-dropzone:hover, .pf-dropzone.pf-dropzone-hover {
      border-color: var(--pf-accent, #f7931e);
      background: #fff8ee;
    }
    .pf-dropzone-preview { flex: 0 0 auto; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; }
    .pf-dropzone-preview img { max-width: 60px; max-height: 60px; border-radius: 6px; object-fit: cover; }
    .pf-dropzone-texto { font-size: 13.5px; color: var(--pf-muted, #666); }
    .pf-galeria {
      display: none;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
      padding: 10px;
      border: 1px solid var(--pf-line, #eee);
      border-radius: var(--pf-radius-md, 10px);
      max-height: 220px;
      overflow-y: auto;
    }
    .pf-galeria.show { display: flex; }
    .pf-galeria-thumb { width: 64px; height: 64px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid transparent; }
    .pf-galeria-thumb:hover { border-color: var(--pf-accent, #f7931e); }
    .pf-galeria-vacio { font-size: 13px; color: var(--pf-muted, #666); padding: 6px; }
  </style>
  <script>
  function pfInitImagePicker(id, imagenesIniciales) {
    var dropzone = document.getElementById('imgDropzone_' + id);
    var fileInput = document.getElementById('imgFile_' + id);
    var existenteInput = document.getElementById('imgExistente_' + id);
    var preview = document.getElementById('imgPreview_' + id);
    var btnGaleria = document.getElementById('imgBtnGaleria_' + id);
    var galeria = document.getElementById('imgGaleria_' + id);
    var galeriaCargada = false;

    function mostrarPreview(url) {
      preview.innerHTML = '';
      var img = document.createElement('img');
      img.src = url;
      preview.appendChild(img);
    }

    dropzone.addEventListener('click', function () { fileInput.click(); });
    dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('pf-dropzone-hover'); });
    dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('pf-dropzone-hover'); });
    dropzone.addEventListener('drop', function (e) {
      e.preventDefault();
      dropzone.classList.remove('pf-dropzone-hover');
      if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
      }
    });
    fileInput.addEventListener('change', function () {
      if (!fileInput.files.length) return;
      existenteInput.value = '';
      var reader = new FileReader();
      reader.onload = function (e) { mostrarPreview(e.target.result); };
      reader.readAsDataURL(fileInput.files[0]);
    });

    btnGaleria.addEventListener('click', function () {
      if (galeria.classList.contains('show')) {
        galeria.classList.remove('show');
        return;
      }
      galeria.classList.add('show');
      if (galeriaCargada) return;
      galeriaCargada = true;
      galeria.innerHTML = '';
      var imagenes = imagenesIniciales || [];
      if (!imagenes.length) {
        galeria.innerHTML = '<div class="pf-galeria-vacio">Todavía no hay imágenes subidas aquí.</div>';
        return;
      }
      imagenes.forEach(function (imagen) {
            var thumb = document.createElement('img');
            thumb.src = imagen.url;
            thumb.className = 'pf-galeria-thumb';
            thumb.title = 'Usar esta imagen';
            thumb.addEventListener('click', function () {
              existenteInput.value = imagen.ruta;
              fileInput.value = '';
              mostrarPreview(imagen.url);
              galeria.classList.remove('show');
            });
            galeria.appendChild(thumb);
      });
    });
  }
  </script>
<?php endif; ?>
<script>pfInitImagePicker(<?= json_encode($imgPickerId) ?>, <?= json_encode($imgPickerImagenes, JSON_UNESCAPED_SLASHES) ?>);</script>
