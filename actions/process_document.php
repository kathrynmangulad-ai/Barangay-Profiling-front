<?php








require_once __DIR__ . '/../config/config.php';
require_staff();
csrf_verify_get();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid document request.');
}

$s = $conn->prepare("SELECT id, barangay_id, status FROM document_requests WHERE id = ?");
$s->bind_param('i', $id);
$s->execute();
$row = $s->get_result()->fetch_assoc();

if (!$row) {
    die('Document request not found.');
}
if ($_SESSION['role'] !== 'admin' && (int)$row['barangay_id'] !== (int)$_SESSION['barangay_id']) {
    die('Access denied.');
}

 
$u = $conn->prepare("UPDATE document_requests SET status='Processing' WHERE id=? AND status='Pending'");
$u->bind_param('i', $id);
$u->execute();

header('Location: ' . url('pages/documents.php'));
exit;
