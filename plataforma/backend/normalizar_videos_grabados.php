<?php
// Herramienta de un solo uso — vuelve a pasar cada eventos.video_grabado_url
// existente por normalizar_url_youtube() (auth.php), la misma función que
// ya usa contenido_form.php al guardar. Necesaria porque el fix de
// contenido_form.php solo normaliza HACIA ADELANTE (la próxima vez que se
// guarde ese evento) — no corrige lo que ya quedó guardado como
// youtube.com/live/... o /watch?v=... antes del fix, que el navegador
// bloquea al mostrarlo en un <iframe> (X-Frame-Options: sameorigin).
//
// Solo CLI a propósito, mismo criterio que reconstruir_membresias.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Esta herramienta solo corre por línea de comandos (SSH/Terminal de cPanel).\n");
}

require_once __DIR__ . '/auth.php';

$aplicar = in_array('--aplicar', $argv, true);

$filas = $conn->query("SELECT id, titulo, video_grabado_url FROM eventos WHERE video_grabado_url IS NOT NULL AND video_grabado_url <> ''")->fetch_all(MYSQLI_ASSOC);

$cambios = 0;
foreach ($filas as $fila) {
    $normalizada = normalizar_url_youtube($fila['video_grabado_url']);
    if ($normalizada === $fila['video_grabado_url']) {
        continue;
    }
    $cambios++;
    printf("[%s] evento id=%d \"%s\"\n  antes: %s\n  despues: %s\n", $aplicar ? 'aplicado' : 'dry-run', $fila['id'], $fila['titulo'], $fila['video_grabado_url'], $normalizada);
    if ($aplicar) {
        $stmt = $conn->prepare('UPDATE eventos SET video_grabado_url = ? WHERE id = ?');
        $stmt->bind_param('si', $normalizada, $fila['id']);
        $stmt->execute();
        $stmt->close();
    }
}

echo "\n--- Resumen ---\n";
printf("Eventos con video_grabado_url: %d\n", count($filas));
printf("%s: %d\n", $aplicar ? 'Corregidos' : 'Se corregirían (dry-run)', $cambios);
if (!$aplicar && $cambios > 0) {
    echo "\nEsto fue un dry-run — no se escribió nada. Repite el comando con --aplicar para guardar.\n";
}
