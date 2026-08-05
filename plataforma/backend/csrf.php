<?php
// CSRF propio de la plataforma de cursos.

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_valido(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function requerir_csrf_json(): void
{
    if (!csrf_valido($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sesión inválida, recarga la página e intenta de nuevo.']);
        exit;
    }
}

function requerir_csrf_form(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Sesión inválida, recarga la página e intenta de nuevo.';
        exit;
    }
}
