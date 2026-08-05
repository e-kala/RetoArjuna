<?php
// Cupones de descuento. El incremento de uso ocurre al iniciar el pago (Stripe intent
// o registro de transferencia), no al confirmarse — igual que en nuywame; un checkout
// abandonado sigue consumiendo un uso del cupón, quirk aceptado, no un bug a corregir.

function validar_cupon(mysqli $conn, string $codigo, float $precio): ?array
{
    $stmt = $conn->prepare('SELECT * FROM cupones WHERE codigo = ? AND activo = 1 LIMIT 1');
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $cupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cupon) {
        return null;
    }

    $hoy = date('Y-m-d');
    if ($cupon['vigencia_desde'] && $hoy < $cupon['vigencia_desde']) {
        return null;
    }
    if ($cupon['vigencia_hasta'] && $hoy > $cupon['vigencia_hasta']) {
        return null;
    }
    if ($cupon['uso_maximo'] !== null && (int) $cupon['usos_actuales'] >= (int) $cupon['uso_maximo']) {
        return null;
    }

    if ($cupon['tipo'] === 'porcentaje') {
        $descuento = round($precio * ((float) $cupon['valor'] / 100), 2);
    } else {
        $descuento = min((float) $cupon['valor'], $precio);
    }

    return [
        'cupon_id' => (int) $cupon['id'],
        'descuento' => $descuento,
        'monto_final' => max(0, $precio - $descuento),
    ];
}

function incrementar_uso_cupon(mysqli $conn, int $cuponId): void
{
    $stmt = $conn->prepare('UPDATE cupones SET usos_actuales = usos_actuales + 1 WHERE id = ?');
    $stmt->bind_param('i', $cuponId);
    $stmt->execute();
    $stmt->close();
}
