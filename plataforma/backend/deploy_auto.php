<?php
// Despliegue automático "pull" — pensado para correr por cron (PHP CLI) en
// hosting compartido sin SSH/git (Neubox/cPanel). No necesita credenciales
// de FTP ni de la BD de producción aparte de las que ya vive en
// config.local.php de este mismo servidor: solo hace peticiones HTTPS
// salientes a la API pública de GitHub (repo público, sin token) y aplica
// los cambios localmente.
//
// SOLO SE APLICAN CAMBIOS DE ESQUEMA (ALTER TABLE ... ADD COLUMN IF NOT
// EXISTS) — nunca los INSERT de datos de exportar_produccion.sql. Ese
// archivo trae un dump completo de la BD local (usuarios_perfil con
// password_hash, pagos, membresia_suscripciones, foro_*), y local no
// siempre es un espejo exacto de producción — correr esos INSERT en
// automático podría crear cuentas/pagos/contenido de prueba reales en el
// sitio. Si algún día hace falta sincronizar datos de catálogo (navbar_links,
// membresias, etc.), se hace a mano, revisando el diff primero.
//
// Uso en cron de cPanel (comando, no URL):
//   /usr/bin/php /home/USUARIO/public_html/plataforma/backend/deploy_auto.php
//
// No es accesible por navegador (ver el guard de abajo) — solo corre por CLI.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo se ejecuta por línea de comandos (cron).');
}

const REPO = 'e-kala/RetoArjuna';
// 'master' en GitHub solo tiene el "Initial commit" (casi vacío) — todo el
// desarrollo real, y lo que hoy está en vivo en arjuna.mx, vive en
// 'desarrollo' (confirmado con el usuario). Si algún día se decide usar
// 'master' como rama real de producción, cambiar solo esta constante.
const RAMA = 'desarrollo';
$raiz = dirname(__DIR__, 2); // plataforma/backend/ -> plataforma/ -> raíz del sitio
$archivoEstado = $raiz . '/plataforma/backend/.deploy_estado.json';
$logDir = $raiz . '/logs';
$logFile = $logDir . '/deploy.log';

function deploy_log(string $msg): void
{
    global $logFile, $logDir;
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

function http_get(string $url): ?string
{
    $contexto = stream_context_create([
        'http' => ['header' => "User-Agent: RetoArjuna-Deploy\r\n", 'timeout' => 60, 'ignore_errors' => true],
    ]);
    $datos = @file_get_contents($url, false, $contexto);
    return $datos === false ? null : $datos;
}

// 1) ¿Hay un commit nuevo en la rama de producción?
$commitJson = http_get('https://api.github.com/repos/' . REPO . '/commits/' . RAMA);
if ($commitJson === null) {
    deploy_log('ERROR: no se pudo consultar la API de GitHub.');
    exit(1);
}
$commitData = json_decode($commitJson, true);
$shaRemoto = $commitData['sha'] ?? null;
if (!$shaRemoto) {
    deploy_log('ERROR: respuesta de GitHub sin sha — ¿rate limit? Respuesta: ' . substr($commitJson, 0, 300));
    exit(1);
}

$estado = file_exists($archivoEstado) ? json_decode((string) file_get_contents($archivoEstado), true) : [];
$shaLocal = $estado['sha'] ?? null;
if ($shaLocal === $shaRemoto) {
    exit(0); // nada nuevo — no ensucia el log en cada corrida del cron
}

deploy_log('Nuevo commit: ' . $shaRemoto . ' (anterior: ' . ($shaLocal ?: 'ninguno') . ')');

// 2) Descargar y extraer el .zip del commit (repo público, sin token)
$zipUrl = 'https://github.com/' . REPO . '/archive/' . $shaRemoto . '.zip';
$zipData = http_get($zipUrl);
if ($zipData === null) {
    deploy_log('ERROR: no se pudo descargar el zip del commit.');
    exit(1);
}
$tmpZip = sys_get_temp_dir() . '/retoarjuna_deploy_' . uniqid() . '.zip';
file_put_contents($tmpZip, $zipData);

$tmpDir = sys_get_temp_dir() . '/retoarjuna_extract_' . uniqid();
mkdir($tmpDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($tmpZip) !== true) {
    deploy_log('ERROR: el zip descargado no se pudo abrir.');
    @unlink($tmpZip);
    exit(1);
}
$zip->extractTo($tmpDir);
$zip->close();
@unlink($tmpZip);

// GitHub extrae dentro de una carpeta tipo RetoArjuna-<sha>/
$carpetas = glob($tmpDir . '/*', GLOB_ONLYDIR);
$origen = $carpetas[0] ?? null;
if (!$origen) {
    deploy_log('ERROR: no se encontró la carpeta extraída del zip.');
    exit(1);
}

// 3) Copiar archivos encima del sitio real. config.local.php, logs/ y
// plataforma/vendor/ están en .gitignore -> nunca vienen en el zip, así que
// quedan intactos automáticamente (esta función solo copia lo que SÍ existe
// en $origen, nunca borra nada del destino).
function copiar_recursivo(string $origen, string $destino): int
{
    $copiados = 0;
    foreach (scandir($origen) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $o = $origen . '/' . $item;
        $d = $destino . '/' . $item;
        if (is_dir($o)) {
            if (!is_dir($d)) {
                mkdir($d, 0755, true);
            }
            $copiados += copiar_recursivo($o, $d);
        } else {
            copy($o, $d);
            $copiados++;
        }
    }
    return $copiados;
}

$totalArchivos = copiar_recursivo($origen, $raiz);
deploy_log('Archivos actualizados: ' . $totalArchivos);

// Limpieza del directorio temporal
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmpDir);

// 4) Solo el esquema — se extraen ÚNICAMENTE los bloques "ALTER TABLE ...;"
// de exportar_produccion.sql (ya son idempotentes: ADD COLUMN IF NOT
// EXISTS). Todo lo demás del archivo (CREATE TABLE, INSERT, UPDATE de
// corrección) se ignora a propósito — ver el comentario del encabezado.
require_once $raiz . '/plataforma/backend/conexion.php';
$sqlPath = $raiz . '/plataforma/db/exportar_produccion.sql';
if (!file_exists($sqlPath)) {
    deploy_log('AVISO: no se encontró exportar_produccion.sql, se omite la migración de esquema.');
} else {
    $sqlCompleto = (string) file_get_contents($sqlPath);
    preg_match_all('/ALTER TABLE\b.*?;/is', $sqlCompleto, $coincidencias);
    // mysqldump envuelve DISABLE/ENABLE KEYS en comentarios /*!40000 ... */ —
    // la regex de arriba corta ese comentario a la mitad y deja SQL inválido
    // ("ALTER TABLE `x` DISABLE KEYS */;", con un */ suelto). No son
    // estatutos que necesitemos de todos modos, se descartan explícitamente.
    $alters = array_values(array_filter($coincidencias[0] ?? [], function (string $s): bool {
        return stripos($s, 'DISABLE KEYS') === false && stripos($s, 'ENABLE KEYS') === false;
    }));
    $aplicados = 0;
    $errores = 0;
    foreach ($alters as $alter) {
        if ($conn->query($alter)) {
            $aplicados++;
        } else {
            $errores++;
            deploy_log('ERROR SQL en: ' . substr($alter, 0, 120) . ' — ' . $conn->error);
        }
    }
    deploy_log("Esquema: {$aplicados} ALTER aplicados, {$errores} con error, de " . count($alters) . ' encontrados.');
}

// 5) Registrar el nuevo estado
file_put_contents($archivoEstado, json_encode(['sha' => $shaRemoto, 'fecha' => date('c')], JSON_PRETTY_PRINT));
deploy_log('Deploy completo: ' . $shaRemoto);
