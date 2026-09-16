<?php
// Prestaciones y regalos — quien ya tiene acceso a un curso/evento puede
// regalar ese mismo acceso o un descuento a alguien más, dentro de un
// permiso que el admin configura por curso/evento (regalo_configuracion,
// panel/admin/contenido_form.php) — nunca por default.
//
// Rediseño: "Regalar descuento" y "Regalar acceso" son dos prestaciones
// INDEPENDIENTES (cada una con su propio interruptor y límites), no un solo
// % donde 100 significa "acceso completo". "Regalar descuento" además admite
// VARIOS tipos de descuento configurables (tabla regalo_tipos_descuento) —
// el estudiante elige cuál usar al generar su enlace.
//
// `regalos` es la ÚNICA fuente de verdad de disponibilidad/reclamación/
// consumo/vigencia/estado del enlace: toda la UI (tarjeta "Regalar",
// bandeja de regalos, página de reclamo) lee `estado` de aquí, nunca
// reconstruye una copia local de esa lógica.
//
// Deliberadamente separado de cupones/promociones (backend/ofertas.php):
// un regalo nunca se presenta ni se procesa como cupón comercial, aunque
// ambos reduzcan el precio — el origen se conserva distinto en checkout,
// pagos y el panel admin.

/**
 * Configuración de regalo para un curso o evento (nunca ambos), con sus
 * tipos de descuento — null si nunca se guardó ninguna fila para este
 * curso/evento (nunca se crea implícita, solo desde el form de admin).
 */
function regalo_configuracion_obtener(?int $cursoId, ?int $eventoId): ?array
{
    global $conn;
    $columna = $cursoId !== null ? 'curso_id' : 'evento_id';
    $itemId = $cursoId !== null ? $cursoId : $eventoId;
    $stmt = $conn->prepare("SELECT * FROM regalo_configuracion WHERE $columna = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) {
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM regalo_tipos_descuento WHERE configuracion_id = ? ORDER BY orden, descuento_pct DESC');
    $stmt->bind_param('i', $fila['id']);
    $stmt->execute();
    $fila['tipos_descuento'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $fila;
}

/** Igual que regalo_configuracion_obtener() pero solo si hay algo realmente activo para mostrar al estudiante. */
function regalo_configuracion_publica(?int $cursoId, ?int $eventoId): ?array
{
    $config = regalo_configuracion_obtener($cursoId, $eventoId);
    if (!$config) {
        return null;
    }
    $hayDescuento = (int) $config['activo_descuento'] === 1 && count($config['tipos_descuento']) > 0;
    $hayAcceso = (int) $config['activo_acceso'] === 1;
    return ($hayDescuento || $hayAcceso) ? $config : null;
}

/**
 * Cuántos enlaces ya generó este usuario para un tipo de regalo específico
 * (un tipo de descuento, o null = "Regalar acceso") y si todavía puede
 * generar más — respeta enlaces_por_usuario (tope personal) y, solo la
 * primera vez, max_usuarios_habilitados (tope de cuentas distintas en total).
 */
function regalo_estado_usuario(array $config, ?array $tipo, int $usuarioId): array
{
    global $conn;
    $esAcceso = $tipo === null;
    $enlacesPorUsuario = $esAcceso ? (int) $config['acceso_enlaces_por_usuario'] : (int) $tipo['enlaces_por_usuario'];
    $maxUsuarios = $esAcceso ? $config['acceso_max_usuarios_habilitados'] : $tipo['max_usuarios_habilitados'];

    if ($esAcceso) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM regalos WHERE configuracion_id = ? AND tipo_descuento_id IS NULL AND usuario_da_id = ? AND estado <> 'revocado'");
        $stmt->bind_param('ii', $config['id'], $usuarioId);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM regalos WHERE tipo_descuento_id = ? AND usuario_da_id = ? AND estado <> 'revocado'");
        $stmt->bind_param('ii', $tipo['id'], $usuarioId);
    }
    $stmt->execute();
    $generados = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $disponibles = max(0, $enlacesPorUsuario - $generados);
    $puede = $disponibles > 0;
    $motivoBloqueo = null;

    if ($puede && $generados === 0 && $maxUsuarios !== null) {
        if ($esAcceso) {
            $stmt = $conn->prepare("SELECT COUNT(DISTINCT usuario_da_id) AS n FROM regalos WHERE configuracion_id = ? AND tipo_descuento_id IS NULL");
            $stmt->bind_param('i', $config['id']);
        } else {
            $stmt = $conn->prepare('SELECT COUNT(DISTINCT usuario_da_id) AS n FROM regalos WHERE tipo_descuento_id = ?');
            $stmt->bind_param('i', $tipo['id']);
        }
        $stmt->execute();
        $totalDadores = (int) $stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        if ($totalDadores >= (int) $maxUsuarios) {
            $puede = false;
            $disponibles = 0;
            $motivoBloqueo = 'Ya se alcanzó el número de personas que pueden regalar esto.';
        }
    }

    return ['generados' => $generados, 'disponibles' => $disponibles, 'puede_generar' => $puede, 'motivo_bloqueo' => $motivoBloqueo];
}

/**
 * Estado de CADA opción de regalo disponible para este usuario (un tipo de
 * descuento por fila, más "acceso" si aplica) — lo que curso_detalle.php/
 * evento_detalle.php necesitan para dejar elegir al estudiante.
 */
function regalo_opciones_usuario(array $config, int $usuarioId): array
{
    $opciones = [];
    if ((int) $config['activo_descuento'] === 1) {
        foreach ($config['tipos_descuento'] as $tipo) {
            $opciones[] = [
                'tipo' => 'descuento',
                'tipo_descuento_id' => (int) $tipo['id'],
                'descuento_pct' => (float) $tipo['descuento_pct'],
                'estado' => regalo_estado_usuario($config, $tipo, $usuarioId),
            ];
        }
    }
    if ((int) $config['activo_acceso'] === 1) {
        $opciones[] = [
            'tipo' => 'acceso',
            'tipo_descuento_id' => null,
            'descuento_pct' => 100.0,
            'estado' => regalo_estado_usuario($config, null, $usuarioId),
        ];
    }
    return $opciones;
}

/** Genera un nuevo enlace de regalo — revalida elegibilidad server-side, nunca confía en el cliente. */
function regalo_generar(array $config, ?int $tipoDescuentoId, int $usuarioId): array
{
    $tipo = null;
    if ($tipoDescuentoId !== null) {
        $tipo = null;
        foreach ($config['tipos_descuento'] as $t) {
            if ((int) $t['id'] === $tipoDescuentoId) {
                $tipo = $t;
                break;
            }
        }
        if (!$tipo || (int) $config['activo_descuento'] !== 1) {
            return ['success' => false, 'message' => 'Este tipo de descuento ya no está disponible.'];
        }
    } elseif ((int) $config['activo_acceso'] !== 1) {
        return ['success' => false, 'message' => 'Regalar acceso completo ya no está disponible.'];
    }

    $estado = regalo_estado_usuario($config, $tipo, $usuarioId);
    if (!$estado['puede_generar']) {
        return ['success' => false, 'message' => $estado['motivo_bloqueo'] ?? 'Ya generaste el máximo de enlaces que puedes regalar.'];
    }

    global $conn;
    $codigo = strtoupper(bin2hex(random_bytes(8)));
    $vigenciaDias = $tipo ? $tipo['vigencia_dias'] : $config['acceso_vigencia_dias'];
    $venceEn = $vigenciaDias !== null
        ? date('Y-m-d H:i:s', strtotime('+' . (int) $vigenciaDias . ' days'))
        : null;

    $stmt = $conn->prepare('INSERT INTO regalos (codigo, configuracion_id, tipo_descuento_id, usuario_da_id, vence_en) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('siiis', $codigo, $config['id'], $tipoDescuentoId, $usuarioId, $venceEn);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return ['success' => true, 'id' => $id, 'codigo' => $codigo];
}

/** Regalos que un usuario ha generado para una configuración, con su estado — para mostrarle su bandeja. */
function regalos_generados_por(int $usuarioId, int $configuracionId): array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT r.*, t.descuento_pct AS tipo_descuento_pct, u.username_cache AS recibe_username
         FROM regalos r
         LEFT JOIN regalo_tipos_descuento t ON t.id = r.tipo_descuento_id
         LEFT JOIN usuarios_perfil u ON u.id = r.usuario_recibe_id
         WHERE r.usuario_da_id = ? AND r.configuracion_id = ? ORDER BY r.created_at DESC'
    );
    $stmt->bind_param('ii', $usuarioId, $configuracionId);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/** Un regalo por su código, con los datos del curso/evento y de quien lo regaló — para la página de reclamo. */
function regalo_obtener_por_codigo(string $codigo): ?array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT r.*, t.descuento_pct AS tipo_descuento_pct, c.curso_id, c.evento_id,
                cu.titulo AS curso_titulo, cu.slug AS curso_slug,
                ev.titulo AS evento_titulo, ev.slug AS evento_slug,
                u.username_cache AS da_username
         FROM regalos r
         JOIN regalo_configuracion c ON c.id = r.configuracion_id
         LEFT JOIN regalo_tipos_descuento t ON t.id = r.tipo_descuento_id
         LEFT JOIN cursos cu ON cu.id = c.curso_id
         LEFT JOIN eventos ev ON ev.id = c.evento_id
         JOIN usuarios_perfil u ON u.id = r.usuario_da_id
         WHERE r.codigo = ?'
    );
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($fila) {
        // NULL en tipo_descuento_id (o el tipo ya borrado) = acceso completo.
        $fila['descuento_pct'] = $fila['tipo_descuento_id'] !== null && $fila['tipo_descuento_pct'] !== null
            ? (float) $fila['tipo_descuento_pct']
            : 100.0;
    }
    return $fila ?: null;
}

/**
 * "Reclamar" = un usuario logueado abre el enlace por primera vez, queda
 * ligado a su cuenta (usuario_recibe_id) sin aplicar todavía el beneficio
 * — eso pasa hasta "Aceptar" (regalo_aceptar_gratis()/regalo_aceptar_descuento()).
 * Revisitar el mismo enlace ya reclamado por la MISMA cuenta es idempotente;
 * otra cuenta lo encuentra ya tomado.
 */
function regalo_reclamar(array $regalo, int $usuarioId): array
{
    global $conn;

    if ($regalo['estado'] === 'revocado') {
        return ['success' => false, 'message' => 'Este regalo fue cancelado.'];
    }
    if ((int) $regalo['usuario_da_id'] === $usuarioId) {
        return ['success' => false, 'message' => 'No puedes reclamar un regalo que tú mismo generaste.'];
    }
    if ($regalo['vence_en'] !== null && strtotime($regalo['vence_en']) < time() && $regalo['estado'] === 'disponible') {
        return ['success' => false, 'message' => 'Este enlace de regalo ya venció.'];
    }

    if ($regalo['estado'] === 'disponible') {
        $stmt = $conn->prepare("UPDATE regalos SET estado = 'reclamado', usuario_recibe_id = ?, reclamado_en = NOW() WHERE id = ? AND estado = 'disponible'");
        $stmt->bind_param('ii', $usuarioId, $regalo['id']);
        $stmt->execute();
        $afectadas = $stmt->affected_rows;
        $stmt->close();
        if ($afectadas === 0) {
            // Alguien más lo reclamó justo en este instante — se revalida de nuevo.
            $fresco = regalo_obtener_por_codigo($regalo['codigo']);
            return $fresco ? regalo_reclamar($fresco, $usuarioId) : ['success' => false, 'message' => 'Este regalo ya no existe.'];
        }
        return ['success' => true];
    }

    // reclamado o aceptado: solo sigue siendo válido para el mismo destinatario.
    if ((int) ($regalo['usuario_recibe_id'] ?? 0) !== $usuarioId) {
        return ['success' => false, 'message' => 'Este regalo ya fue reclamado por otra persona.'];
    }
    return ['success' => true];
}

/**
 * Acepta un regalo de acceso completo (tipo_descuento_id = NULL): otorga el
 * acceso directo (mismo criterio que curso_inscribir.php/evento_inscribir.php
 * para lo gratuito) y marca el regalo consumido. Para descuento parcial no
 * se usa esta función — el llamador manda al usuario a checkout.php con
 * ?regalo=CODIGO, que ya sabe leer un regalo en estado "aceptado".
 */
function regalo_aceptar_gratis(array $regalo, int $usuarioId): array
{
    global $conn;

    if ((int) ($regalo['usuario_recibe_id'] ?? 0) !== $usuarioId || $regalo['estado'] !== 'reclamado') {
        return ['success' => false, 'message' => 'Este regalo no está disponible para aceptar.'];
    }

    if ($regalo['curso_id'] !== null) {
        $stmt = $conn->prepare('INSERT IGNORE INTO curso_inscripciones (usuario_id, curso_id, regalo_id) VALUES (?, ?, ?)');
        $stmt->bind_param('iii', $usuarioId, $regalo['curso_id'], $regalo['id']);
    } else {
        $stmt = $conn->prepare('INSERT IGNORE INTO evento_inscripciones (usuario_id, evento_id, regalo_id) VALUES (?, ?, ?)');
        $stmt->bind_param('iii', $usuarioId, $regalo['evento_id'], $regalo['id']);
    }
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE regalos SET estado = 'aceptado', aceptado_en = NOW() WHERE id = ? AND estado = 'reclamado'");
    $stmt->bind_param('i', $regalo['id']);
    $stmt->execute();
    $stmt->close();

    return ['success' => true];
}

/**
 * Marca un regalo de descuento parcial como aceptado (el usuario decidió
 * usarlo y va a pagar el monto con descuento) — checkout.php ya sabe leer
 * esto para calcular el precio y, al confirmarse el pago, guardar
 * pagos.regalo_id. Si el pago se abandona, el regalo queda "aceptado" sin
 * pago confirmado — visible y revocable por el admin (panel/admin/regalos.php),
 * mismo criterio manual que ya se usa para otros pagos abandonados.
 */
function regalo_aceptar_descuento(array $regalo, int $usuarioId): array
{
    global $conn;

    if ((int) ($regalo['usuario_recibe_id'] ?? 0) !== $usuarioId || $regalo['estado'] !== 'reclamado') {
        return ['success' => false, 'message' => 'Este regalo no está disponible para aceptar.'];
    }

    $stmt = $conn->prepare("UPDATE regalos SET estado = 'aceptado', aceptado_en = NOW() WHERE id = ? AND estado = 'reclamado'");
    $stmt->bind_param('i', $regalo['id']);
    $stmt->execute();
    $stmt->close();

    return ['success' => true];
}
