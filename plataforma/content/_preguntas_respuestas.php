<?php
// Fragmento compartido por curso_detalle.php / evento_detalle.php / leccion.php
// para la pestaña "Preguntas y respuestas" (mockup del cliente: en vez de
// comentarios propios, muestra los TEMAS del foro ya vinculados a este
// curso/evento/lección, con botón "Ver todo en el foro" y un formulario para
// publicar sin salir de la página — ver foro/backend/crear_tema_leccion.php
// y foro/backend/responder.php). Espera ya definidas por quien lo incluye:
//   $temasPregyresp   array de foro_temas_de() (o [] si aún no hay ninguno)
//   $temaExistenteIdPregyresp  int|null — id del tema ya vinculado a este
//                     contexto exacto (curso/evento + lección si aplica)
//   $usuario          current_user() ya resuelto
//   $verForoUrlPregyresp  string — a dónde lleva "Ver todo en el foro"
if (!isset($temasPregyresp)) {
    $temasPregyresp = [];
}
?>
<div class="pf-preguntas-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted small mb-0">Preguntas de la comunidad — respondidas por otros estudiantes o por el equipo de Arjuna.</p>
    <a href="<?= htmlspecialchars($verForoUrlPregyresp) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Ver todo en el foro</a>
  </div>

  <div id="pfListaTemas">
    <?php if ($temasPregyresp): ?>
      <div class="list-group mb-3">
        <?php foreach ($temasPregyresp as $t): ?>
          <a href="foro/tema.php?id=<?= (int) $t['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
            <span>
              <?php if ($t['fijado']): ?><i class="bi bi-pin-angle-fill text-warning" title="Fijado"></i><?php endif; ?>
              <?= htmlspecialchars($t['titulo']) ?>
              <span class="text-muted small d-block">por <?= htmlspecialchars($t['username_cache']) ?></span>
            </span>
            <span class="badge bg-secondary rounded-pill"><?= (int) $t['respuestas_count'] ?> respuesta<?= (int) $t['respuestas_count'] === 1 ? '' : 's' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted small">Todavía no hay preguntas aquí — sé el primero en preguntar.</p>
    <?php endif; ?>
  </div>

  <?php if ($usuario): ?>
    <div class="card p-3" style="max-width:560px;">
      <label for="pfNuevaPregunta" class="form-label small fw-bold mb-1">
        <?= $temaExistenteIdPregyresp ? 'Responder' : 'Hacer una pregunta' ?>
      </label>
      <textarea id="pfNuevaPregunta" class="form-control" rows="3" placeholder="Escribe tu pregunta o comentario…"></textarea>
      <button type="button" id="pfBtnPublicarPregunta" class="btn btn-sm mt-2 align-self-start" style="background:#f7931e;color:#fff;">Publicar</button>
      <div id="pfPreguntaMsg" class="form-text mt-1"></div>
    </div>
  <?php else: ?>
    <p class="text-muted small">Inicia sesión para participar.</p>
  <?php endif; ?>
</div>
