
<?php

require_once __DIR__ . '/../config/config.php';
require_staff();
csrf_verify_get();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid resident ID.");
}

// Look the row up among the soft-deleted residents so we can check barangay
// access before restoring it.
$chk = $conn->prepare("SELECT barangay_id FROM residents WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1");
$chk->bind_param("i", $id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$row) {
    die("Deleted resident not found.");
}
require_barangay_access($row['barangay_id'], 'resident', $id);

// Restore: clear the deletion stamp so the resident shows up in the live list.
$stmt = $conn->prepare("UPDATE residents SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
if (!$stmt) {
    http_response_code(500);
    die('Restore failed: could not prepare statement &mdash; ' . e($conn->error));
}
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$err = $conn->error;
$stmt->close();

if (!$ok) {
    log_access('resident_restore_failed', 'resident', $id, 'denied');
    http_response_code(500);
    die('Restore failed &mdash; ' . e($err));
}

log_access('resident_restored', 'resident', $id);
header("Location: " . url('pages/residents.php?view=deleted&restored=1'));
exit;
