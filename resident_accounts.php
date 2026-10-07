<?php
/* Backward-compatibility stub. Moved to /secretary/resident_accounts.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('secretary/resident_accounts.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
