<?php
/* Backward-compatibility stub. Moved to /auth/reset_password.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('auth/reset_password.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
