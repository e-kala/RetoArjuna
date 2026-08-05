<?php
// Migración única de datos reales de Flarum (retoarju_foro) hacia el esquema propio
// (retoarju_cursos). Se corre UNA VEZ por CLI, antes de borrar retoarju_foro.
// php plataforma/db/migrar_datos_flarum.php

$origen = new mysqli('localhost', 'retoarju_foro', ')7}S0Kap*odxwI=A', 'retoarju_foro');
if ($origen->connect_error) {
    die("No se pudo conectar a retoarju_foro: {$origen->connect_error}\n");
}
$origen->set_charset('utf8mb4');

$destino = new mysqli('localhost', 'retoarju_cursos', 'Cursos_RA_2026!loc', 'retoarju_cursos');
if ($destino->connect_error) {
    die("No se pudo conectar a retoarju_cursos: {$destino->connect_error}\n");
}
$destino->set_charset('utf8mb4');

$resumen = ['usuarios' => 0, 'categorias' => 0, 'temas' => 0, 'respuestas' => 0, 'likes' => 0];

// -----------------------------------------------------------------
// 1) Usuarios: upsert por username_cache (la fila de EKA ya existe)
// -----------------------------------------------------------------
$usuarioMap = []; // flarum_user_id => usuarios_perfil.id

$res = $origen->query('SELECT id, username, email, password, avatar_url, joined_at FROM users');
while ($u = $res->fetch_assoc()) {
    $stmt = $destino->prepare('SELECT id FROM usuarios_perfil WHERE username_cache = ? LIMIT 1');
    $stmt->bind_param('s', $u['username']);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // No se migra avatar_url: apuntaba a los assets de Flarum, que ya no existirán.
    $avatar = null;
    $joinedAt = $u['joined_at'] ?? date('Y-m-d H:i:s');

    if ($existente) {
        $usuarioMap[(int) $u['id']] = (int) $existente['id'];
        $stmt = $destino->prepare('UPDATE usuarios_perfil SET password_hash = ?, email_cache = ? WHERE id = ?');
        $stmt->bind_param('ssi', $u['password'], $u['email'], $existente['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $destino->prepare(
            "INSERT INTO usuarios_perfil (username_cache, email_cache, password_hash, avatar_cache, rol, activo, created_at)
             VALUES (?, ?, ?, ?, 'estudiante', 1, ?)"
        );
        $stmt->bind_param('sssss', $u['username'], $u['email'], $u['password'], $avatar, $joinedAt);
        $stmt->execute();
        $usuarioMap[(int) $u['id']] = $stmt->insert_id;
        $stmt->close();
    }
    $resumen['usuarios']++;
}

// -----------------------------------------------------------------
// 2) Tags -> categorías del foro (+ una categoría "General" de respaldo)
// -----------------------------------------------------------------
$categoriaMap = []; // flarum_tag_id => foro_categorias.id

$destino->query("INSERT INTO foro_categorias (nombre, slug, descripcion, orden) VALUES ('General', 'general', 'Temas generales.', -1) ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");
$stmt = $destino->prepare("SELECT id FROM foro_categorias WHERE slug = 'general'");
$stmt->execute();
$categoriaGeneralId = (int) $stmt->get_result()->fetch_assoc()['id'];
$stmt->close();

$res = $origen->query('SELECT id, name, slug, description, position FROM tags');
while ($t = $res->fetch_assoc()) {
    $slug = $t['slug'] !== '' ? $t['slug'] : ('categoria-' . $t['id']);
    $stmt = $destino->prepare(
        'INSERT INTO foro_categorias (nombre, slug, descripcion, orden) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)'
    );
    $orden = (int) ($t['position'] ?? 0);
    $stmt->bind_param('sssi', $t['name'], $slug, $t['description'], $orden);
    $stmt->execute();

    $catId = $stmt->insert_id;
    if ($catId === 0) {
        $lookup = $destino->prepare('SELECT id FROM foro_categorias WHERE slug = ?');
        $lookup->bind_param('s', $slug);
        $lookup->execute();
        $catId = (int) $lookup->get_result()->fetch_assoc()['id'];
        $lookup->close();
    }
    $stmt->close();
    $categoriaMap[(int) $t['id']] = $catId;
    $resumen['categorias']++;
}

// -----------------------------------------------------------------
// 3) Discusiones (+ su post inicial) -> temas
// -----------------------------------------------------------------
$temaMap = [];       // flarum_discussion_id => foro_temas.id
$primerPostMap = []; // flarum_first_post_id => foro_temas.id (para resolver likes)

$res = $origen->query(
    "SELECT d.id, d.title, d.slug, d.is_sticky, d.is_locked, d.comment_count,
            d.last_posted_at, d.created_at, d.user_id, d.first_post_id,
            p.content AS post_content
     FROM discussions d
     JOIN posts p ON p.id = d.first_post_id"
);
while ($d = $res->fetch_assoc()) {
    $usuarioId = $usuarioMap[(int) $d['user_id']] ?? null;
    if (!$usuarioId) {
        continue; // autor no resuelto, se salta (no debería pasar con datos reales)
    }

    $tagRes = $origen->query('SELECT tag_id FROM discussion_tag WHERE discussion_id = ' . (int) $d['id'] . ' ORDER BY tag_id LIMIT 1');
    $tagRow = $tagRes->fetch_assoc();
    $categoriaId = $tagRow ? ($categoriaMap[(int) $tagRow['tag_id']] ?? $categoriaGeneralId) : $categoriaGeneralId;

    // Se sufija con el id de Flarum siempre: algunos temas reales comparten slug
    // (p.ej. dos hilos casi con el mismo título) y un slug duplicado pisaría el
    // primero vía ON DUPLICATE KEY UPDATE, perdiendo ese tema silenciosamente.
    $slugBase = $d['slug'] !== '' ? $d['slug'] : 'tema';
    $slug = $slugBase . '-' . $d['id'];
    $fijado = (int) $d['is_sticky'];
    $cerrado = (int) $d['is_locked'];
    $respuestasCount = max(0, (int) $d['comment_count'] - 1);
    $contenido = (string) $d['post_content'];

    $stmt = $destino->prepare(
        'INSERT INTO foro_temas (categoria_id, usuario_id, titulo, slug, contenido, fijado, cerrado, respuestas_count, ultima_respuesta_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)'
    );
    $stmt->bind_param(
        'iisssiiiss',
        $categoriaId, $usuarioId, $d['title'], $slug, $contenido,
        $fijado, $cerrado, $respuestasCount, $d['last_posted_at'], $d['created_at']
    );
    $stmt->execute();
    $temaId = $stmt->insert_id;
    if ($temaId === 0) {
        $lookup = $destino->prepare('SELECT id FROM foro_temas WHERE slug = ?');
        $lookup->bind_param('s', $slug);
        $lookup->execute();
        $temaId = (int) $lookup->get_result()->fetch_assoc()['id'];
        $lookup->close();
    }
    $stmt->close();

    $temaMap[(int) $d['id']] = $temaId;
    $primerPostMap[(int) $d['first_post_id']] = $temaId;
    $resumen['temas']++;
}

// -----------------------------------------------------------------
// 4) Posts tipo 'comment' (excluyendo el post inicial ya usado arriba) -> respuestas
// -----------------------------------------------------------------
$respuestaMap = []; // flarum_post_id => foro_respuestas.id

$res = $origen->query(
    "SELECT id, discussion_id, user_id, content, created_at
     FROM posts WHERE type = 'comment' AND number > 1 ORDER BY discussion_id, number"
);
while ($p = $res->fetch_assoc()) {
    $temaId = $temaMap[(int) $p['discussion_id']] ?? null;
    $usuarioId = $usuarioMap[(int) $p['user_id']] ?? null;
    if (!$temaId || !$usuarioId) {
        continue;
    }

    $stmt = $destino->prepare(
        'INSERT INTO foro_respuestas (tema_id, usuario_id, contenido, created_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('iiss', $temaId, $usuarioId, $p['content'], $p['created_at']);
    $stmt->execute();
    $respuestaMap[(int) $p['id']] = $stmt->insert_id;
    $stmt->close();
    $resumen['respuestas']++;
}

// -----------------------------------------------------------------
// 5) post_likes -> foro_likes
// -----------------------------------------------------------------
$res = $origen->query('SELECT post_id, user_id FROM post_likes');
while ($l = $res->fetch_assoc()) {
    $usuarioId = $usuarioMap[(int) $l['user_id']] ?? null;
    if (!$usuarioId) {
        continue;
    }
    $postId = (int) $l['post_id'];
    $temaId = $primerPostMap[$postId] ?? null;
    $respuestaId = $temaId ? null : ($respuestaMap[$postId] ?? null);
    if (!$temaId && !$respuestaId) {
        continue;
    }

    $stmt = $destino->prepare(
        'INSERT IGNORE INTO foro_likes (usuario_id, tema_id, respuesta_id) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('iii', $usuarioId, $temaId, $respuestaId);
    $stmt->execute();
    $stmt->close();
    $resumen['likes']++;
}

echo "Migración completa:\n";
foreach ($resumen as $k => $v) {
    echo "  {$k}: {$v}\n";
}
