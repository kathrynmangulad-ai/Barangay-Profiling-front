<?php
/* Backward-compatibility stub. Moved to /pages/resident_edit.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('pages/resident_edit.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
