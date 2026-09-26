<?php
// Sincronización de inscripción automática a "eventos regulares" — un
// evento con eventos.es_regular=1 inscribe automáticamente a todo Miembro
// de Camino Arjuna con membresía activa, sin pasar por Stripe ni mostrar
// sello promocional (para él ya es un beneficio activo).
//
// Único punto de escritura del valor metodo='membresia_regular' en
// evento_inscripciones — nunca se escribe desde otro archivo, para que la
// regla "nunca pisar una fila con otro origen" (compra propia, otorgado
// manual por admin, regalo canjeado) viva en un solo lugar.
//
// Nota de diseño: un usuario cuyo acceso vive SOLO en `pagos` (sin fila en
// evento_inscripciones — patrón real de una compra suelta) y que además es
// miembro de un evento regular: al cancelar su membresía, la función de
// abajo no tiene ninguna fila 'membresia_regular' que cancelar para él (no
// existía), así que no hace nada — y su acceso real sigue intacto porque
// usuario_esta_inscrito_evento() sigue viendo su fila en `pagos`. El
// criterio de "nunca perder una compra independiente" queda cubierto por
// la propia definición de acceso del sistema, sin lógica adicional aquí.

/**
 * Sincroniza las inscripciones automáticas de UN usuario a todos los
 * eventos regulares próximos (activo=1 AND fecha_inicio >= NOW(), mismo
 * criterio que content/eventos_catalogo.php), según si su membresía está
 * activa ahora mismo.
 */
function sincronizar_inscripciones_regulares(int $usuarioId, bool $tieneMembresiaActiva): void
{
    global $conn;

    if ($tieneMembresiaActiva) {
        // INSERT...SELECT de todos los eventos regulares próximos. El
        // ON DUPLICATE KEY solo "reclama" la fila si sigue siendo
        // membresia_regular (o ya cancelada bajo ese mismo origen) — si la
        // fila existente tiene cualquier otro metodo, este UPDATE es un
        // no-op (metodo=metodo no cambia nada), nunca la pisa.
        $stmt = $conn->prepare(
            "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado, metodo)
             SELECT ?, e.id, 'inscrito', 'membresia_regular'
             FROM eventos e
             WHERE e.es_regular = 1 AND e.activo = 1 AND e.fecha_inicio >= NOW()
             ON DUPLICATE KEY UPDATE
               estado = IF(evento_inscripciones.metodo = 'membresia_regular', 'inscrito', evento_inscripciones.estado),
               metodo = evento_inscripciones.metodo"
        );
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $stmt->close();
        return;
    }

    // Solo cancela (nunca DELETE, preserva historial) las filas
    // metodo='membresia_regular' de eventos regulares próximos —
    // cualquier otro metodo, o eventos ya pasados, quedan intactos.
    $stmt = $conn->prepare(
        "UPDATE evento_inscripciones ei
         JOIN eventos e ON e.id = ei.evento_id
         SET ei.estado = 'cancelado'
         WHERE ei.usuario_id = ? AND ei.metodo = 'membresia_regular'
           AND e.es_regular = 1 AND e.activo = 1 AND e.fecha_inicio >= NOW()
           AND ei.estado <> 'cancelado'"
    );
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Sincroniza TODOS los miembros con membresía activa/gracia hacia UN
 * evento regular específico — usado cuando un evento pasa a es_regular=1
 * (o se guarda de nuevo ya siéndolo), para no dejar fuera a quien ya era
 * miembro antes de que existiera el flag. Reusa el mismo criterio de
 * acceso de usuario_tiene_membresia_activa() vía JOIN directo, en vez de
 * iterar usuario por usuario.
 */
function sincronizar_evento_regular_para_todos_los_miembros(int $eventoId): void
{
    global $conn;

    // "metodo" sin calificar es ambiguo aquí — el SELECT interno consulta
    // membresia_suscripciones, que TAMBIÉN tiene su propia columna metodo
    // (enum distinto). Hay que calificar con evento_inscripciones.metodo
    // explícitamente en el ON DUPLICATE KEY UPDATE (confirmado con
    // mysqli_sql_exception real en pruebas: "Column 'metodo' in field list
    // is ambiguous").
    $stmt = $conn->prepare(
        "INSERT INTO evento_inscripciones (usuario_id, evento_id, estado, metodo)
         SELECT s.usuario_id, ?, 'inscrito', 'membresia_regular'
         FROM membresia_suscripciones s
         WHERE (s.estado = 'activa' AND (s.periodo_actual_fin IS NULL OR s.periodo_actual_fin >= NOW()))
            OR (s.estado = 'gracia' AND s.periodo_actual_fin >= DATE_SUB(NOW(), INTERVAL 2 DAY))
         ON DUPLICATE KEY UPDATE
           estado = IF(evento_inscripciones.metodo = 'membresia_regular', 'inscrito', evento_inscripciones.estado),
           metodo = evento_inscripciones.metodo"
    );
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $stmt->close();
}
