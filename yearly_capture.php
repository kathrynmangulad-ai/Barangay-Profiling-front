<?php
/* Backward-compatibility stub. Moved to /admin/yearly_capture.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('admin/yearly_capture.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
