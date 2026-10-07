<?php
/* Backward-compatibility stub. Moved to /pages/incident_report.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('pages/incident_report.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
