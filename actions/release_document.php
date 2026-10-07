<?php

require_once __DIR__ . '/../config/config.php';
require_staff();
csrf_verify_get();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid document request.");
}







$stmt = $conn->prepare("
    SELECT 
        dr.*,
        b.barangay_name
    FROM document_requests dr
    LEFT JOIN barangays b 
        ON dr.barangay_id = b.id
    WHERE dr.id = ?
");

if ($stmt === false) {
    die("Prepare Error: " . $conn->error);
}

$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    die("Execute Error: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result === false) {
    die("Get Result Error: " . $stmt->error);
}

$request = $result->fetch_assoc();

if (!$request) {
    die("Document request not found.");
}





require_barangay_access($request['barangay_id'], 'document_request', $id);







$update = $conn->prepare("
    UPDATE document_requests
    SET status = 'Released'
    WHERE id = ?
");

$update->bind_param("i", $id);
$update->execute();







header("Location: " . url('pages/document_print.php?id=' . $id));
exit;

?>