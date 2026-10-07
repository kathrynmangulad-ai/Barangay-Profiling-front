<?php
/* Backward-compatibility stub. Moved to /resident/document_request.php. */
require_once __DIR__ . '/config/config.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('resident/document_request.php') . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
