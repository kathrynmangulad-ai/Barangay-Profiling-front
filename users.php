<?php
/* Backward-compatibility stub. Moved to /admin/users.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('admin/users.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
