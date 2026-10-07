<?php
/* Backward-compatibility stub. Moved to /pages/blotter_add.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('pages/blotter_add.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
