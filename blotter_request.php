<?php
/* Backward-compatibility stub. Moved to /resident/blotter_request.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('resident/blotter_request.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
