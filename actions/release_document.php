<?php
/* Release a document request (staff only), then return to the Documents list.
 *
 * Only Pending / Processing requests can be released. The release goes through
 * transition_document() so it is recorded in document_status_history and the
 * access log, and released_at is stamped once - certificates print it as the
 * date of issuance. Re-opening an already released request just shows it again.
 */

require_once __DIR__ . '/../config/config.php';
require_staff();
csrf_verify_get();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid document request.");
}

$stmt = $conn->prepare("SELECT id, barangay_id, status FROM document_requests WHERE id = ?");
if ($stmt === false) {
    die("Prepare Error: " . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    die("Document request not found.");
}

require_barangay_access($request['barangay_id'], 'document_request', $id);

$status = (string)$request['status'];

if ($status === 'Pending' || $status === 'Processing') {
    if (!transition_document($id, 'Released', ['Pending', 'Processing'])) {
        die("This request could not be released. It may have been changed by someone else - please reload the Documents page.");
    }
    $stamp = $conn->prepare("UPDATE document_requests SET released_at = NOW() WHERE id = ? AND released_at IS NULL");
    $stamp->bind_param("i", $id);
    $stamp->execute();
    $stamp->close();
} elseif ($status !== 'Released') {
    die("Only pending or processing requests can be released (this one is " . e($status) . ").");
}

// Stay on the Documents list (same tab and filters the user came from); the
// list shows a "released" notice with a View / Print link for this request.
$qs = [];
$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
$refParts = parse_url($ref);
if ($ref !== '' && isset($refParts['path']) && preg_match('~/pages/documents\.php$~', $refParts['path'])) {
    parse_str((string)($refParts['query'] ?? ''), $qs);
    unset($qs['released'], $qs['saved']);
}
$qs['released'] = $id;
header("Location: " . url('pages/documents.php?' . http_build_query($qs)));
exit;
