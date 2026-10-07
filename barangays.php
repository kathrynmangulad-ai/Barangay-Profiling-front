<?php
/* Backward-compatibility stub. Moved to /admin/barangays.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('admin/barangays.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
