<?php
// Bandeja de notificaciones unificada — respuestas/menciones del foro y
// avisos de contenido nuevo (curso/evento/producto/noticia), todo en un
// mismo lugar (ver backend/notificaciones.php). Compartido por
// panel/dashboard.php y panel/index.php (mismo patrón que mis_compras.php).
$usuarioPerfilId = (int) $_SESSION['usuario_perfil_id'];
$notificaciones = notificaciones_recientes($usuarioPerfilId, 50);
$hayNoLeidas = (bool) array_filter($notificaciones, fn ($n) => (int) $n['leida'] === 0);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <h1 class="h3 mb-0">Notificaciones</h1>
    <?php if ($notificaciones): ?>
        <button class="btn btn-outline-secondary btn-sm" id="btnMarcarTodasLeidas" <?= $hayNoLeidas ? '' : 'hidden' ?>><i class="bi bi-check2-all"></i> Marcar todas como leídas</button>
    <?php endif; ?>
</div>

<div class="list-group shadow-sm" id="listaNotificaciones">
    <?php foreach ($notificaciones as $n): ?>
        <div class="list-group-item d-flex align-items-center gap-2" data-notif-id="<?= (int) $n['id'] ?>" style="<?= (int) $n['leida'] === 0 ? 'border-left:3px solid var(--pf-accent);background:#fffaf2;' : '' ?>">
            <a class="text-decoration-none flex-grow-1 text-dark" href="<?= htmlspecialchars(BASE_URL . '/' . $n['enlace']) ?>">
                <i class="bi <?= notificacion_icono($n['tipo']) ?>"></i>
                <?= htmlspecialchars($n['titulo']) ?>
                <?php if ($n['mensaje']): ?><span class="text-muted">— <?= htmlspecialchars($n['mensaje']) ?></span><?php endif; ?>
                · <span class="text-muted"><?= notificacion_tiempo_relativo($n['created_at']) ?></span>
            </a>
            <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0 btn-marcar-notif" title="Marcar como leída" <?= (int) $n['leida'] === 0 ? '' : 'hidden' ?>>
                <i class="bi bi-check2"></i>
            </button>
        </div>
    <?php endforeach; ?>
    <?php if (!$notificaciones): ?>
        <div class="list-group-item text-center text-muted py-5"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i> No tienes notificaciones todavía.</div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var csrfToken = <?= json_encode(csrf_token()) ?>;
        var urlMarcarLeida = <?= json_encode(BASE_URL . '/backend/marcar_notificacion_leida.php') ?>;

        function marcar(datos, callbackExito) {
            fetch(urlMarcarLeida, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams(Object.assign({ csrf_token: csrfToken }, datos)),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        callbackExito();
                    } else {
                        alert(data.message || 'No se pudo marcar como leída.');
                    }
                })
                .catch(function () { alert('Error de conexión. Intenta de nuevo.'); });
        }

        var btnTodas = document.getElementById('btnMarcarTodasLeidas');
        if (btnTodas) {
            btnTodas.addEventListener('click', function () {
                marcar({ todas: '1' }, function () {
                    document.querySelectorAll('#listaNotificaciones [data-notif-id]').forEach(function (fila) {
                        fila.style.borderLeft = '';
                        fila.style.background = '';
                    });
                    document.querySelectorAll('.btn-marcar-notif').forEach(function (btn) { btn.hidden = true; });
                    btnTodas.hidden = true;
                });
            });
        }

        document.getElementById('listaNotificaciones').addEventListener('click', function (event) {
            var btn = event.target.closest('.btn-marcar-notif');
            if (!btn) return;
            event.preventDefault();
            var fila = btn.closest('[data-notif-id]');
            marcar({ notificacion_id: fila.dataset.notifId }, function () {
                fila.style.borderLeft = '';
                fila.style.background = '';
                btn.hidden = true;
                var quedanNoLeidas = document.querySelectorAll('.btn-marcar-notif:not([hidden])').length > 0;
                if (!quedanNoLeidas && btnTodas) btnTodas.hidden = true;
            });
        });
    });
</script>
