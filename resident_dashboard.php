<?php
/* Backward-compatibility stub. Moved to /resident/resident_dashboard.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('resident/resident_dashboard.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
