<?php
// Resuelve qué se está comprando (curso, evento o producto) a partir de los
// parámetros de la petición, normalizando lo que checkout/stripe/transferencia
// necesitan, para no repetir el branching en cada archivo de pagos.
// Devuelve null si no viene ningún id o el item no existe.
function resolver_item_pago(mysqli $conn, array $params, int $usuarioPerfilId): ?array
{
    $cursoId = (int) ($params['curso_id'] ?? 0);
    $eventoId = (int) ($params['evento_id'] ?? 0);
    $productoId = (int) ($params['producto_id'] ?? 0);

    if ($cursoId) {
        $stmt = $conn->prepare('SELECT titulo, slug, precio, activo, gratuito FROM cursos WHERE id = ?');
        $stmt->bind_param('i', $cursoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        return [
            'tipo' => 'curso',
            'id' => $cursoId,
            'titulo' => $row['titulo'],
            'slug' => $row['slug'],
            'precio' => (float) $row['precio'],
            'activo' => (bool) $row['activo'],
            'gratuito' => (bool) $row['gratuito'],
            'ya_tiene_acceso' => usuario_tiene_acceso_curso($usuarioPerfilId, $cursoId),
            'es_fisico' => false,
        ];
    }

    if ($eventoId) {
        $stmt = $conn->prepare('SELECT titulo, slug, precio, activo, gratuito FROM eventos WHERE id = ?');
        $stmt->bind_param('i', $eventoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        return [
            'tipo' => 'evento',
            'id' => $eventoId,
            'titulo' => $row['titulo'],
            'slug' => $row['slug'],
            'precio' => (float) $row['precio'],
            'activo' => (bool) $row['activo'],
            'gratuito' => (bool) $row['gratuito'],
            'ya_tiene_acceso' => usuario_esta_inscrito_evento($usuarioPerfilId, $eventoId),
            'es_fisico' => false,
        ];
    }

    if ($productoId) {
        $stmt = $conn->prepare('SELECT nombre, slug, precio, activo, tipo, stock FROM productos WHERE id = ?');
        $stmt->bind_param('i', $productoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        return [
            'tipo' => 'producto',
            'id' => $productoId,
            'titulo' => $row['nombre'],
            'slug' => $row['slug'],
            'precio' => (float) $row['precio'],
            'activo' => (bool) $row['activo'],
            'gratuito' => false,
            'ya_tiene_acceso' => false,
            'es_fisico' => $row['tipo'] === 'fisico',
            'stock' => $row['stock'] !== null ? (int) $row['stock'] : null,
        ];
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
