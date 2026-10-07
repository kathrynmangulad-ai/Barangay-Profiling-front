<?php
/* Backward-compatibility stub. This page moved to /auth/login.php.
   Kept so old bookmarks and links keep working. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('auth/login.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
