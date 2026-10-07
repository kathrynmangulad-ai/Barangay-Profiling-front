<?php
/* Backward-compatibility stub. Moved to /pages/residents.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('pages/residents.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
