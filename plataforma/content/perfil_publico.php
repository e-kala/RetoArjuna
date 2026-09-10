<?php
// Perfil público de otro usuario — solo visible si esa persona activó
// "Hacer mi perfil público" en su propio perfil (ver panel/content/perfil.php
// y backend/perfiles.php). Nunca expone correo/teléfono, solo lo que ya es
// visible en el foro (usuario, fecha de registro, actividad).
$usuarioId = (int) ($_GET['usuario'] ?? 0);

$stmt = $conn->prepare('SELECT id, username_cache, avatar_cache, created_at, perfil_publico FROM usuarios_perfil WHERE id = ?');
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Un admin sí puede ver un perfil aunque su dueño lo haya puesto privado —
// necesita poder revisarlo desde el panel (usuarios.php, pagos.php, etc.,
// que ahora enlazan el nombre de usuario aquí mismo) sin depender de que la
// persona haya activado "Hacer mi perfil público".
$esAdminViendoPerfil = (current_user()['rol'] ?? null) === 'admin';
if (!$perfil || ((int) $perfil['perfil_publico'] !== 1 && !$esAdminViendoPerfil)) {
    echo '<div class="container" style="margin-top:143px;margin-bottom:60px;"><p>Este perfil no está disponible.</p></div>';
    return;
}

$stmt = $conn->prepare('SELECT COUNT(*) AS n FROM foro_temas WHERE usuario_id = ? AND visibilidad = "publico"');
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$totalTemas = (int) $stmt->get_result()->fetch_assoc()['n'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS n FROM foro_respuestas WHERE usuario_id = ? AND eliminado_en IS NULL');
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$totalRespuestas = (int) $stmt->get_result()->fetch_assoc()['n'];
$stmt->close();

$stmt = $conn->prepare(
    'SELECT id, titulo, slug, created_at FROM foro_temas
     WHERE usuario_id = ? AND visibilidad = "publico" AND oculto = 0
     ORDER BY created_at DESC LIMIT 10'
);
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$temasRecientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// El badge de membresía no se muestra a Visitante (mismo criterio que el
// resto del sitio, ver G03 en checklist.txt) — aquí no es un "ancla" de
// venta, pero se mantiene consistente: solo lo ve alguien ya logueado.
$esMiembro = current_user() && usuario_tiene_membresia_activa($usuarioId);
?>
<div class="container" style="margin-top: 143px; margin-bottom: 60px; max-width: 700px;">
  <div class="d-flex align-items-center gap-3 mb-4">
    <span class="pf-user-avatar" style="width:64px;height:64px;font-size:1.5rem;"><?= htmlspecialchars(strtoupper(substr((string) $perfil['username_cache'], 0, 1))) ?></span>
    <div>
      <h1 class="h3 mb-1"><?= htmlspecialchars($perfil['username_cache']) ?></h1>
      <p class="text-muted mb-0">
        En la comunidad desde <?= htmlspecialchars(date('F Y', strtotime($perfil['created_at']))) ?>
        <?php if ($esMiembro): ?> · <span class="badge rounded-pill" style="background:#fff3e0;color:#8a6d00;">Miembro Camino Arjuna</span><?php endif; ?>
      </p>
    </div>
  </div>

  <div class="row row-cols-2 g-3 mb-4">
    <div class="col">
      <div class="ra-stat-card text-center">
        <div class="ra-stat-value"><?= $totalTemas ?></div>
        <div class="ra-stat-label">Temas creados en el foro</div>
      </div>
    </div>
    <div class="col">
      <div class="ra-stat-card text-center">
        <div class="ra-stat-value"><?= $totalRespuestas ?></div>
        <div class="ra-stat-label">Respuestas en el foro</div>
      </div>
    </div>
  </div>

  <?php if ($temasRecientes): ?>
    <h2 class="h5 mb-3">Temas recientes</h2>
    <ul class="list-group">
      <?php foreach ($temasRecientes as $t): ?>
        <li class="list-group-item">
          <a href="<?= htmlspecialchars(BASE_URL . '/foro/tema.php?id=' . $t['id']) ?>" class="text-decoration-none"><?= htmlspecialchars($t['titulo']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
