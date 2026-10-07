
<?php

require_once __DIR__ . '/../config/config.php';
require_staff();
csrf_verify_get();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid resident ID.");
}

$chk = $conn->prepare("SELECT barangay_id FROM residents WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$chk->bind_param("i", $id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$row) {
    die("Resident not found.");
}
require_barangay_access($row['barangay_id'], 'resident', $id);

// Soft delete: stamp deleted_at instead of removing the row. Because the
// resident record is kept (just hidden), related document_requests and
// blotter_records keep pointing at it and need no detaching.
$step = 'soft delete resident (UPDATE residents SET deleted_at = NOW() WHERE id = ?)';
$stmt = $conn->prepare("UPDATE residents SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
if (!$stmt) {
    http_response_code(500);
    die('Delete failed: could not prepare statement &mdash; ' . e($conn->error));
}
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$fail = $conn->error;
$stmt->close();

if (!$ok) {
    log_access('resident_delete_failed', 'resident', $id, 'denied');
    http_response_code(500);
    die(
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Resident delete failed</title></head>'
        . '<body style="font-family:Arial,Helvetica,sans-serif;margin:2rem;line-height:1.5">'
        . '<div style="border:2px solid #c0392b;background:#fdecea;color:#611a15;padding:1rem 1.25rem;border-radius:6px;max-width:760px">'
        . '<strong style="font-size:1.1rem">Delete failed &mdash; the resident was NOT removed.</strong>'
        . '<ul style="margin:.75rem 0 0">'
        . '<li><b>What failed:</b> ' . e($step) . ' &rarr; ' . e($fail) . '</li>'
        . '<li><b>Resident ID:</b> ' . (int)$id . '</li>'
        . '</ul>'
        . '<p style="margin:.9rem 0 0"><a href="' . e(url('pages/residents.php')) . '">Back to residents list</a></p>'
        . '</div></body></html>'
    );
}

log_access('resident_deleted', 'resident', $id);
header("Location: " . url('pages/residents.php?deleted=1'));
exit;
