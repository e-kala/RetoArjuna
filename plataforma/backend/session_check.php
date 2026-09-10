<?php
// Sondeo ligero de sesión — usado por content/session_watch.js en todo el
// sitio para avisar cuando la sesión ya expiró mientras el usuario seguía en
// la página (antes solo se notaba hasta el siguiente clic/recarga, cuando
// require_login()/require_role() ya redirigían a medio camino de algo).
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['logged_in' => is_logged_in()]);
