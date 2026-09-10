<?php
// Motor de ofertas — única fuente de verdad de precio/badge/CTA para curso,
// evento, producto y membresía (checklist.txt OF01-OF10, ADM05: "una sola
// configuración debe alimentar frontend y checkout"). Catálogos, detalles y
// checkout llaman resolver_oferta() en vez de reimplementar la cascada cada
// uno por su lado.
require_once __DIR__ . '/auth.php';

/**
 * Traduce el tipo de ítem a su columna FK en promociones/cupon_alcance/pagos.
 */
function oferta_columna_tipo(string $tipo): string
{
    return match ($tipo) {
        'curso' => 'curso_id',
        'evento' => 'evento_id',
        'producto' => 'producto_id',
        'membresia' => 'membresia_id',
        default => throw new InvalidArgumentException("Tipo de ítem desconocido: {$tipo}"),
    };
}

/**
 * Promoción pública vigente para un ítem — activación/vencimiento son solo
 * por fecha en el WHERE, sin cron (mismo criterio que ya usa
 * eventos_catalogo.php para separar próximos/pasados).
 */
function obtener_promocion_activa(mysqli $conn, string $tipo, int $itemId): ?array
{
    $columna = oferta_columna_tipo($tipo);
    $stmt = $conn->prepare(
        "SELECT * FROM promociones WHERE {$columna} = ? AND activo = 1
         AND NOW() BETWEEN fecha_inicio AND fecha_fin
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/**
 * Validación de solo lectura de un cupón contra la tabla propia (no Stripe)
 * — no escribe nada, el llamador decide cuándo persistir cupon_id en
 * pagos/membresia_suscripciones. $usuarioId null = Visitante (válido para
 * vigencia/alcance/límite total, nunca para "un uso por cuenta").
 */
function validar_cupon(mysqli $conn, string $codigo, string $tipo, int $itemId, float $precioBase, ?int $usuarioId): array
{
    $vacio = ['ok' => false, 'cupon' => null, 'descuento_monto' => 0.0, 'precio_final' => $precioBase, 'message' => ''];

    $codigo = strtoupper(trim($codigo));
    if ($codigo === '') {
        return array_merge($vacio, ['message' => 'Escribe un código de cupón.']);
    }

    $stmt = $conn->prepare('SELECT * FROM cupones WHERE codigo = ? AND activo = 1 LIMIT 1');
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $cupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cupon) {
        return array_merge($vacio, ['message' => 'Ese cupón no existe o ya no está activo.']);
    }

    $cuponId = (int) $cupon['id'];
    $ahora = new DateTime();
    if ($cupon['fecha_inicio'] && $ahora < new DateTime($cupon['fecha_inicio'])) {
        return array_merge($vacio, ['message' => 'Ese cupón todavía no está vigente.']);
    }
    if ($cupon['fecha_fin'] && $ahora > new DateTime($cupon['fecha_fin'])) {
        return array_merge($vacio, ['message' => 'Ese cupón ya venció.']);
    }

    // Alcance: sin filas en cupon_alcance = cupón global.
    $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM cupon_alcance WHERE cupon_id = ?');
    $stmt->bind_param('i', $cuponId);
    $stmt->execute();
    $totalAlcance = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    if ($totalAlcance > 0) {
        $columna = oferta_columna_tipo($tipo);
        $stmt = $conn->prepare("SELECT 1 FROM cupon_alcance WHERE cupon_id = ? AND {$columna} = ? LIMIT 1");
        $stmt->bind_param('ii', $cuponId, $itemId);
        $stmt->execute();
        $aplica = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$aplica) {
            return array_merge($vacio, ['message' => 'Ese cupón no aplica para este ítem.']);
        }
    }

    // Límite total de usos — cuenta redenciones confirmadas en ambas tablas.
    if ($cupon['usos_totales'] !== null) {
        $stmt = $conn->prepare(
            "SELECT
               (SELECT COUNT(*) FROM pagos WHERE cupon_id = ? AND estado = 'confirmado') +
               (SELECT COUNT(*) FROM membresia_suscripciones WHERE cupon_id = ? AND estado = 'activa') AS n"
        );
        $stmt->bind_param('ii', $cuponId, $cuponId);
        $stmt->execute();
        $usados = (int) $stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        if ($usados >= (int) $cupon['usos_totales']) {
            return array_merge($vacio, ['message' => 'Ese cupón ya alcanzó su límite de usos.']);
        }
    }

    // Un solo uso por Cuenta Arjuna (P02) — un Visitante nunca puede pasar
    // este chequeo con estado "ya usado" (usuarioId null lo salta), pero
    // tampoco puede canjearlo hasta tener cuenta (eso lo hace P03/checkout,
    // no esta función).
    if ($usuarioId) {
        $stmt = $conn->prepare(
            "SELECT 1 FROM pagos WHERE usuario_id = ? AND cupon_id = ? AND estado = 'confirmado'
             UNION SELECT 1 FROM membresia_suscripciones WHERE usuario_id = ? AND cupon_id = ? AND estado = 'activa'
             LIMIT 1"
        );
        $stmt->bind_param('iiii', $usuarioId, $cuponId, $usuarioId, $cuponId);
        $stmt->execute();
        $yaUsado = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($yaUsado) {
            return array_merge($vacio, ['message' => 'Ya usaste este cupón antes.']);
        }
    }

    $descuento = $cupon['tipo_descuento'] === 'monto'
        ? (float) $cupon['valor']
        : $precioBase * ((float) $cupon['valor'] / 100);
    $precioFinal = max(0.0, round($precioBase - $descuento, 2));

    return [
        'ok' => true,
        'cupon' => $cupon,
        'descuento_monto' => round($precioBase - $precioFinal, 2),
        'precio_final' => $precioFinal,
        'message' => '',
    ];
}

/**
 * Guarda un código de cupón en sesión para que sobreviva cualquier cantidad
 * de navegación entre el enlace inicial y el pago final (P03) — a
 * diferencia de `volver` (auth.php), que es un salto único y sí viaja bien
 * como query param puro.
 */
function guardar_cupon_sesion(string $codigo): void
{
    $codigo = strtoupper(trim($codigo));
    if ($codigo !== '') {
        $_SESSION['cupon_pendiente'] = $codigo;
    }
}

/**
 * Única fuente de verdad de precio/badge/CTA/acceso-gratis (OF01-OF10).
 *
 * $item espera: precio (float), gratuito (bool), incluido_membresia (bool),
 * solo_miembros (bool), descuento_miembro_pct (?float), ya_tiene_acceso
 * (bool), id (int) — cada llamador pasa false/null en los campos que no le
 * apliquen (ej. producto: incluido_membresia=false, solo_miembros=false,
 * descuento_miembro_pct=null; siempre existen porque la membresía nunca ha
 * tenido beneficios de tienda).
 *
 * Retorna: estado ('acceso'|'incluido_membresia'|'exclusivo_bloqueado'|
 * 'gratuito'|'oferta'|'publico'), precio_regular, precio_final,
 * descuento_monto, oferta_tipo (null|'promocion'|'cupon'|
 * 'descuento_miembro'|'combinado'), oferta_id, oferta_nombre, cupon_error,
 * acceso_gratis_automatico (bool), badge, cta.
 */
function resolver_oferta(mysqli $conn, string $tipo, array $item, ?array $usuario, ?string $codigoCupon = null): array
{
    $precioRegular = (float) ($item['precio'] ?? 0);
    $esMiembro = $usuario ? usuario_tiene_membresia_activa((int) $usuario['id']) : false;
    $usuarioId = $usuario['id'] ?? null;

    $base = [
        'estado' => 'publico',
        'precio_regular' => $precioRegular,
        'precio_final' => $precioRegular,
        'descuento_monto' => 0.0,
        'oferta_tipo' => null,
        'oferta_id' => null,
        'oferta_nombre' => null,
        'cupon_error' => null,
        'acceso_gratis_automatico' => false,
        'badge' => '',
        'cta' => 'comprar',
    ];

    // 1) Acceso ya adquirido (OF06) — prevalece sobre cualquier oferta.
    if (!empty($item['ya_tiene_acceso'])) {
        return array_merge($base, ['estado' => 'acceso', 'precio_final' => 0.0, 'cta' => 'ver_acceso']);
    }

    $soloMiembros = !empty($item['solo_miembros']);
    $incluidoMembresia = !empty($item['incluido_membresia']);

    // 2) Exclusivo para miembros (OF05), sin membresía activa.
    if ($soloMiembros && !$esMiembro) {
        return array_merge($base, [
            'estado' => 'exclusivo_bloqueado',
            'cta' => $usuario ? 'accesar_membresia' : 'requiere_cuenta',
        ]);
    }

    // 3) Incluido con membresía (OF04) — solo_miembros ya implica incluido
    // para quien sí es miembro.
    if ($esMiembro && ($incluidoMembresia || $soloMiembros)) {
        return array_merge($base, [
            'estado' => 'incluido_membresia',
            'precio_final' => 0.0,
            'badge' => 'Incluido con tu membresía',
            'cta' => 'accesar_membresia',
        ]);
    }

    // 4) Gratuito — por el checkbox, o porque el precio quedó en $0 sin
    // marcarlo (no tiene sentido cobrar/mostrar "$0.00 MXN" en ese caso).
    // acceso_gratis_automatico=true aquí también: varios puntos del flujo de
    // pago (producto_detalle.php, stripe_create_intent.php, obtener_gratis.php)
    // solo miran esa bandera, no el estado, para decidir si saltarse Stripe.
    if (!empty($item['gratuito']) || $precioRegular <= 0.0) {
        return array_merge($base, [
            'estado' => 'gratuito', 'precio_final' => 0.0, 'badge' => 'Gratuito',
            'cta' => 'inscribir_gratis', 'acceso_gratis_automatico' => true,
        ]);
    }

    // 5) Mejor precio entre promoción pública / cupón / descuento de
    // miembro (OF10) — incluido_membresia/solo_miembros ya se resolvieron
    // arriba, así que una promoción pública nunca compite con membresía
    // (P01 "independiente de membresía" sale gratis, sin condicional extra).
    $itemId = (int) ($item['id'] ?? 0);
    $candidatos = [];

    $promo = obtener_promocion_activa($conn, $tipo, $itemId);
    if ($promo) {
        $precioPromo = $promo['tipo_descuento'] === 'precio_fijo'
            ? (float) $promo['valor']
            : max(0.0, $precioRegular * (1 - ((float) $promo['valor'] / 100)));
        $candidatos[] = [
            'tipo' => 'promocion',
            'id' => (int) $promo['id'],
            'nombre' => $promo['nombre'],
            'precio_final' => round($precioPromo, 2),
            'combinable' => (bool) $promo['combinable'],
        ];
    }

    $cuponError = null;
    if ($codigoCupon !== null && $codigoCupon !== '') {
        $v = validar_cupon($conn, $codigoCupon, $tipo, $itemId, $precioRegular, $usuarioId !== null ? (int) $usuarioId : null);
        if ($v['ok']) {
            $candidatos[] = [
                'tipo' => 'cupon',
                'id' => (int) $v['cupon']['id'],
                'nombre' => 'Cupón ' . $v['cupon']['codigo'],
                'precio_final' => round($v['precio_final'], 2),
                'combinable' => (bool) $v['cupon']['combinable'],
            ];
        } else {
            $cuponError = $v['message'];
        }
    }

    if ($esMiembro && !$soloMiembros && !$incluidoMembresia && !empty($item['descuento_miembro_pct'])) {
        $pct = (float) $item['descuento_miembro_pct'];
        $candidatos[] = [
            'tipo' => 'descuento_miembro',
            'id' => null,
            'nombre' => 'Descuento de miembro',
            'precio_final' => round(max(0.0, $precioRegular * (1 - $pct / 100)), 2),
            'combinable' => true, // beneficio de cuenta, no una oferta puntual — siempre se apila
        ];
    }

    if (!$candidatos) {
        $base['cupon_error'] = $cuponError;
        return $base;
    }

    $todosCombinables = true;
    foreach ($candidatos as $c) {
        if (!$c['combinable']) {
            $todosCombinables = false;
            break;
        }
    }

    if ($todosCombinables && count($candidatos) > 1) {
        // Cascada: cada descuento combinable se suma por su propio monto
        // (calculado contra el precio regular), con piso en $0 — no se
        // encadenan porcentajes entre sí para evitar ambigüedad al mezclar
        // un precio_fijo con un porcentaje.
        $descuentoTotal = 0.0;
        $nombres = [];
        foreach ($candidatos as $c) {
            $descuentoTotal += max(0.0, $precioRegular - $c['precio_final']);
            $nombres[] = $c['nombre'];
        }
        $ganador = [
            'tipo' => 'combinado', 'id' => null, 'nombre' => implode(' + ', $nombres),
            'precio_final' => max(0.0, round($precioRegular - $descuentoTotal, 2)),
        ];
    } else {
        usort($candidatos, fn (array $a, array $b) => $a['precio_final'] <=> $b['precio_final']);
        $ganador = $candidatos[0];
    }

    $precioFinal = $ganador['precio_final'];
    return array_merge($base, [
        'estado' => 'oferta',
        'precio_final' => $precioFinal,
        'descuento_monto' => round($precioRegular - $precioFinal, 2),
        'oferta_tipo' => $ganador['tipo'],
        'oferta_id' => $ganador['id'],
        'oferta_nombre' => $ganador['nombre'],
        'cupon_error' => $cuponError,
        'acceso_gratis_automatico' => $precioFinal <= 0.0,
        'badge' => $ganador['nombre'],
        'cta' => $precioFinal <= 0.0 ? 'inscribir_gratis' : 'comprar',
    ]);
}
