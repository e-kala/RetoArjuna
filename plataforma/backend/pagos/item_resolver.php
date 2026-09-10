<?php
// Resuelve qué se está comprando (curso, evento o producto) a partir de los
// parámetros de la petición, normalizando lo que checkout/stripe/transferencia
// necesitan, para no repetir el branching en cada archivo de pagos.
// Devuelve null si no viene ningún id o el item no existe.
require_once __DIR__ . '/../ofertas.php';

/**
 * Un regalo aceptado (backend/regalos.php: regalo_aceptar_descuento()) SIEMPRE
 * gana sobre promoción/cupón/descuento de miembro — es personal y de un solo
 * uso, no compite por "mejor precio" contra ofertas públicas. Se aplica
 * DESPUÉS de resolver_oferta(), pisando su resultado — nunca se mete al
 * candidato de cupón (backend/ofertas.php) a propósito: un regalo nunca debe
 * verse ni tratarse como cupón comercial, aunque ambos bajen el precio.
 * Devuelve el mismo $item con 'regalo' agregado (null si no aplica), para
 * que checkout.php sepa que no debe ofrecer el campo de código de cupón.
 */
function aplicar_regalo_a_item(array $item, ?array $regalo, int $usuarioPerfilId): array
{
    $item['regalo'] = null;
    if (!$regalo || $item['ya_tiene_acceso']) {
        return $item;
    }
    $esCursoRegalo = $regalo['curso_id'] !== null;
    $tipoCoincide = ($item['tipo'] === 'curso' && $esCursoRegalo && (int) $regalo['curso_id'] === $item['id'])
        || ($item['tipo'] === 'evento' && !$esCursoRegalo && (int) $regalo['evento_id'] === $item['id']);
    if (!$tipoCoincide || $regalo['estado'] !== 'aceptado' || (int) ($regalo['usuario_recibe_id'] ?? 0) !== $usuarioPerfilId) {
        return $item;
    }

    $precioFinal = round($item['precio_regular'] * (1 - ((float) $regalo['descuento_pct'] / 100)), 2);
    $item['estado'] = $precioFinal <= 0 ? 'gratuito' : 'oferta';
    $item['precio_final'] = max(0.0, $precioFinal);
    $item['descuento_monto'] = round($item['precio_regular'] - $item['precio_final'], 2);
    $item['oferta_tipo'] = 'regalo';
    $item['oferta_id'] = (int) $regalo['id'];
    $item['oferta_nombre'] = 'Regalo de ' . $regalo['da_username'];
    $item['cupon_error'] = null;
    $item['acceso_gratis_automatico'] = $item['precio_final'] <= 0.0;
    $item['mostrar_codigo_promocion'] = false;
    $item['regalo'] = $regalo;
    return $item;
}

function resolver_item_pago(mysqli $conn, array $params, int $usuarioPerfilId, ?string $codigoCupon = null): ?array
{
    $cursoId = (int) ($params['curso_id'] ?? 0);
    $eventoId = (int) ($params['evento_id'] ?? 0);
    $productoId = (int) ($params['producto_id'] ?? 0);
    $usuario = $usuarioPerfilId > 0 ? ['id' => $usuarioPerfilId] : null;
    $codigoRegalo = strtoupper(trim((string) ($params['regalo'] ?? '')));
    $regalo = $codigoRegalo !== '' ? regalo_obtener_por_codigo($codigoRegalo) : null;

    if ($cursoId) {
        $stmt = $conn->prepare('SELECT titulo, slug, descripcion, imagen_portada, precio, activo, gratuito, incluido_membresia, descuento_miembro_pct, mostrar_codigo_promocion FROM cursos WHERE id = ?');
        $stmt->bind_param('i', $cursoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $item = [
            'tipo' => 'curso',
            'id' => $cursoId,
            'titulo' => $row['titulo'],
            'slug' => $row['slug'],
            'descripcion' => (string) $row['descripcion'],
            'imagen' => $row['imagen_portada'],
            'precio' => (float) $row['precio'],
            'activo' => (bool) $row['activo'],
            'gratuito' => (bool) $row['gratuito'],
            'incluido_membresia' => (bool) $row['incluido_membresia'],
            'solo_miembros' => false,
            'descuento_miembro_pct' => $row['descuento_miembro_pct'] !== null ? (float) $row['descuento_miembro_pct'] : null,
            'ya_tiene_acceso' => usuario_tiene_acceso_curso($usuarioPerfilId, $cursoId),
            'es_fisico' => false,
            'mostrar_codigo_promocion' => (bool) $row['mostrar_codigo_promocion'],
        ];
        $item = array_merge($item, resolver_oferta($conn, 'curso', $item, $usuario, $codigoCupon));
        return aplicar_regalo_a_item($item, $regalo, $usuarioPerfilId);
    }

    if ($eventoId) {
        $stmt = $conn->prepare('SELECT titulo, slug, descripcion, imagen_portada, precio, activo, gratuito, incluido_membresia, solo_miembros, descuento_miembro_pct, mostrar_codigo_promocion FROM eventos WHERE id = ?');
        $stmt->bind_param('i', $eventoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $item = [
            'tipo' => 'evento',
            'id' => $eventoId,
            'titulo' => $row['titulo'],
            'slug' => $row['slug'],
            'descripcion' => (string) $row['descripcion'],
            'imagen' => $row['imagen_portada'],
            'precio' => (float) $row['precio'],
            'activo' => (bool) $row['activo'],
            'gratuito' => (bool) $row['gratuito'],
            'incluido_membresia' => (bool) $row['incluido_membresia'],
            'solo_miembros' => (bool) $row['solo_miembros'],
            'descuento_miembro_pct' => $row['descuento_miembro_pct'] !== null ? (float) $row['descuento_miembro_pct'] : null,
            'ya_tiene_acceso' => usuario_esta_inscrito_evento($usuarioPerfilId, $eventoId),
            'es_fisico' => false,
            'mostrar_codigo_promocion' => (bool) $row['mostrar_codigo_promocion'],
        ];
        $item = array_merge($item, resolver_oferta($conn, 'evento', $item, $usuario, $codigoCupon));
        return aplicar_regalo_a_item($item, $regalo, $usuarioPerfilId);
    }

    if ($productoId) {
        $stmt = $conn->prepare('SELECT nombre, slug, descripcion, imagen, precio, activo, tipo, stock, mostrar_codigo_promocion FROM productos WHERE id = ?');
        $stmt->bind_param('i', $productoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $item = [
            'tipo' => 'producto',
            'id' => $productoId,
            'titulo' => $row['nombre'],
            'slug' => $row['slug'],
            'descripcion' => (string) $row['descripcion'],
            'imagen' => $row['imagen'],
            'precio' => (float) $row['precio'],
            'activo' => (bool) $row['activo'],
            'gratuito' => false,
            'incluido_membresia' => false,
            'solo_miembros' => false,
            'descuento_miembro_pct' => null,
            'ya_tiene_acceso' => false,
            'es_fisico' => $row['tipo'] === 'fisico',
            'stock' => $row['stock'] !== null ? (int) $row['stock'] : null,
            'mostrar_codigo_promocion' => (bool) $row['mostrar_codigo_promocion'],
        ];
        return array_merge($item, resolver_oferta($conn, 'producto', $item, $usuario, $codigoCupon));
    }

    return null;
}

/**
 * Arma el fragmento de columnas/valores de `pagos` según el tipo de item, para
 * usarlo en los INSERTs de stripe_create_intent.php/transferencia.php sin repetir
 * el branching. Devuelve ['col' => 'curso_id'|'evento_id'|'producto_id', 'id' => int].
 */
function columna_pago_para_item(array $item): array
{
    $col = $item['tipo'] === 'curso' ? 'curso_id' : ($item['tipo'] === 'evento' ? 'evento_id' : 'producto_id');
    return ['col' => $col, 'id' => $item['id']];
}
