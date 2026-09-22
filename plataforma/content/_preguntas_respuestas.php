<?php
// Fragmento compartido por curso_detalle.php / evento_detalle.php / leccion.php
// para la pestaña "Preguntas y respuestas" — muestra las RESPUESTAS del tema
// "ancla" propio de este curso/evento/lección (ver foro_crear_tema_ancla()/
// foro_tema_ancla_de() en foro_helpers.php: un solo tema aislado por
// contexto, nunca una mezcla de temas históricos del foro), con botón "Ver
// todo en el foro" y un formulario para publicar sin salir de la página —
// siempre vía foro/backend/crear_tema_leccion.php (ver el JS más abajo:
// nunca llama a responder.php directo, porque el ancla nace oculta y
// responder.php no acepta respuestas a un tema oculto para un no-admin).
// Espera ya definidas por quien lo incluye:
//   $respuestasPregyresp      array de foro_respuestas_de() (o [] si aún no hay ninguna)
//   $temaExistenteIdPregyresp int|null — id del tema ancla de este contexto exacto
//   $usuario                  current_user() ya resuelto
//   $verForoUrlPregyresp      string — a dónde lleva "Ver todo en el foro"
if (!isset($respuestasPregyresp)) {
    $respuestasPregyresp = [];
}
?>
<div class="pf-preguntas-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted small mb-0">Preguntas de la comunidad — respondidas por otros estudiantes o por el equipo de Arjuna.</p>
    <a href="<?= htmlspecialchars($verForoUrlPregyresp) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Ver todo en el foro</a>
  </div>

  <div id="pfListaRespuestas">
    <?php if ($respuestasPregyresp): ?>
      <div class="d-flex flex-column gap-3 mb-3">
        <?php foreach ($respuestasPregyresp as $r): ?>
          <div class="pf-respuesta-item">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
              <strong class="small"><?= htmlspecialchars($r['username_cache']) ?></strong>
              <span class="text-muted small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at']))) ?></span>
            </div>
            <div class="pf-contenido-html pf-contenido-html-compacto"><?= (string) $r['contenido'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted small">Todavía no hay preguntas aquí — sé el primero en preguntar.</p>
    <?php endif; ?>
  </div>

  <?php if ($usuario): ?>
    <div class="card p-3" style="max-width:560px;">
      <label for="pfNuevaPregunta" class="form-label small fw-bold mb-1">
        <?= $respuestasPregyresp ? 'Responder' : 'Hacer una pregunta' ?>
      </label>
      <textarea id="pfNuevaPregunta" class="form-control" rows="3" placeholder="Escribe tu pregunta o comentario…"></textarea>
      <button type="button" id="pfBtnPublicarPregunta" class="btn btn-sm mt-2 align-self-start" style="background:#f7931e;color:#fff;">Publicar</button>
      <div id="pfPreguntaMsg" class="form-text mt-1"></div>
    </div>
  <?php else: ?>
    <p class="text-muted small">Inicia sesión para participar.</p>
  <?php endif; ?>
</div>
<style>
  .pf-respuesta-item { border-bottom: 1px solid var(--pf-line, #e5e0d8); padding-bottom: 12px; }
  .pf-respuesta-item:last-child { border-bottom: none; padding-bottom: 0; }
  .pf-contenido-html-compacto { font-size: 0.92rem; }
</style>
