<?php
// Corrige retroactivamente el contenido migrado de Flarum: estaba guardado como
// el XML interno crudo de s9e/TextFormatter (<r>...<H3><s>### </s>...) en vez de
// HTML limpio. Convierte con convertir_contenido_foro() SOLO las filas que todavía
// tienen ese formato crudo (columna que empieza con "<r>" o "<t>") — seguro de
// correr más de una vez, no vuelve a tocar filas ya convertidas.
// php plataforma/db/backfill_contenido_flarum.php

require_once __DIR__ . '/../backend/conexion.php';
require_once __DIR__ . '/../../foro/backend/foro_helpers.php';

foreach (['foro_temas', 'foro_respuestas'] as $tabla) {
    $res = $conn->query("SELECT id, contenido FROM {$tabla} WHERE contenido REGEXP '^<[rt]>'");
    $actualizados = 0;
    while ($fila = $res->fetch_assoc()) {
        $html = convertir_contenido_foro($fila['contenido']);
        $stmt = $conn->prepare("UPDATE {$tabla} SET contenido = ? WHERE id = ?");
        $stmt->bind_param('si', $html, $fila['id']);
        $stmt->execute();
        $stmt->close();
        $actualizados++;
    }
    echo "{$tabla}: {$actualizados} filas convertidas\n";
}
