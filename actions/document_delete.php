<?php

require_once __DIR__ . '/../config/config.php';
require_staff();
csrf_verify_get();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid document request ID.");
}





$chk = $conn->prepare("SELECT barangay_id FROM document_requests WHERE id = ? LIMIT 1");
$chk->bind_param("i", $id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$row) {
    die("Document request not found.");
}
require_barangay_access($row['barangay_id'], 'document_request', $id);

$stmt = $conn->prepare("DELETE FROM document_requests WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    log_access('document_deleted', 'document_request', $id);
    header("Location:" . url('pages/documents.php?deleted=1'));
    exit;
} else {
    die("Error deleting document request.");
}
?>