<?php
require_once __DIR__ . '/auth.php';
logout_user();
header('Location: ' . BASE_URL . '/index.php?action=inicio');
exit;
