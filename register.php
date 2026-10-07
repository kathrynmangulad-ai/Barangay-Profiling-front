<?php
/* Backward-compatibility stub. Moved to /auth/register.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('auth/register.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
