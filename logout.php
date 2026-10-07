<?php
/* Backward-compatibility stub. Moved to /auth/logout.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('auth/logout.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
