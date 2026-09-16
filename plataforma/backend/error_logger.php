<?php
// Logger básico de errores fatales/no atrapados. Se incluye como PRIMERA línea de
// cada punto de entrada (antes de auth.php/conexion.php), para poder diagnosticar un
// 500 en un hosting donde no hay acceso cómodo a los logs del servidor: registra el
// error en logs/errores.log Y lo imprime como console.error() en el navegador (si la
// respuesta es HTML, nunca en endpoints que devuelven JSON). Escrito a propósito sin
// ninguna función de PHP 8+ (str_starts_with, fn(), etc.) para que funcione incluso
// si el problema real es justamente una versión de PHP más vieja de la esperada.

if (!defined('RA_ERROR_LOG_FILE')) {
    define('RA_ERROR_LOG_FILE', __DIR__ . '/../../logs/errores.log');
}

function ra_registrar_error($mensaje, $archivo = '', $linea = 0)
{
    $dir = dirname(RA_ERROR_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $detalle = $mensaje;
    if ($archivo !== '') {
        $detalle .= ' en ' . $archivo . ':' . $linea;
    }
    @file_put_contents(
        RA_ERROR_LOG_FILE,
        '[' . date('Y-m-d H:i:s') . '] ' . $detalle . "\n",
        FILE_APPEND | LOCK_EX
    );

    // Una petición AJAX (jQuery manda este header solo, siempre presente
    // desde que ENTRA la petición) espera un body limpio con
    // JSON.parse()/res.json() del otro lado — antes esto solo se detectaba
    // viendo si la respuesta YA traía Content-Type: application/json, pero
    // varios endpoints (ej. leccion_form.php) arman el JSON con
    // json_encode()/echo sin llamar nunca a header('Content-Type: ...'), así
    // que ese chequeo nunca detectaba nada y esta función igual forzaba 500
    // e inyectaba un <script> en medio del JSON — rompiendo el parseo del
    // lado del cliente por CUALQUIER aviso menor de PHP durante la petición
    // (ej. "Undefined array key"), aunque el guardado en sí sí hubiera
    // funcionado. Ahora también se detecta por el header de la petición.
    $esAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $headers = headers_sent() ? array() : headers_list();
    $esJson = $esAjax;
    foreach ($headers as $h) {
        if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'application/json') !== false) {
            $esJson = true;
            break;
        }
    }

    if ($esJson) {
        return; // No tocar el status code ni el cuerpo de una respuesta JSON/AJAX.
    }

    if (!headers_sent()) {
        http_response_code(500);
    }
    echo "<script>console.error(" . json_encode('[RetoArjuna] ' . $detalle) . ");</script>\n";
}

set_exception_handler(function ($e) {
    ra_registrar_error(get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
});

set_error_handler(function ($severidad, $mensaje, $archivo = '', $linea = 0) {
    if (!(error_reporting() & $severidad)) {
        return false; // Respeta @-silenciados y el nivel configurado.
    }
    ra_registrar_error($mensaje, $archivo, $linea);
    return true;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        ra_registrar_error($error['message'], $error['file'], $error['line']);
    }
});
