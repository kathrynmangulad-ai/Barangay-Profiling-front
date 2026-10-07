<?php
/* Backward-compatibility stub. Moved to /pages/document_print.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('pages/document_print.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
