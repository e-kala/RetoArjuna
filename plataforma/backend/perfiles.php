<?php
// Perfiles públicos opcionales — cada usuario decide desde "Mi perfil" si su
// nombre es clickeable a una página de perfil visible para cualquiera (ver
// content/perfil_publico.php). Sin este permiso, perfil_link() devuelve solo
// el texto, sin <a>.

function usuario_tiene_perfil_publico(int $usuarioId): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT perfil_publico FROM usuarios_perfil WHERE id = ?');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila && (int) $fila['perfil_publico'] === 1;
}

/**
 * Nombre de usuario como link a su perfil público si lo activó, o solo
 * texto si no. $usuarioId puede venir de cualquier tabla que ya tenga
 * username_cache a la mano (foro_temas, foro_respuestas, etc.) — evita una
 * consulta extra para el nombre, solo confirma el permiso.
 */
function perfil_link(int $usuarioId, string $nombre): string
{
    $nombreEscapado = htmlspecialchars($nombre);
    if (!usuario_tiene_perfil_publico($usuarioId)) {
        return $nombreEscapado;
    }
    $url = htmlspecialchars(BASE_URL . '/index.php?action=perfil_publico&usuario=' . $usuarioId);
    return '<a href="' . $url . '" class="text-decoration-none">' . $nombreEscapado . '</a>';
}
