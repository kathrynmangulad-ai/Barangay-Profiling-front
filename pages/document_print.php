<?php

require_once __DIR__ . '/../config/config.php';
require_login();

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










require_record_access(
    $request['barangay_id'],
    isset($request['resident_id']) ? (int)$request['resident_id'] : null,
    'document_request',
    $id
);






if (is_resident() && (string)($request['status'] ?? '') !== 'Released') {
    log_access('print_before_release', 'document_request', $id, 'denied');
    deny_access('This document has not been released yet. You can print it once your barangay releases it.');
}

if (is_resident()) {
    log_access('document_printed', 'document_request', $id);
}



 

$requestor = htmlspecialchars($request['requestor_name'] ?? '');
$document_type = trim($request['document_type'] ?? '');
$barangay = htmlspecialchars($request['barangay_name'] ?? '');
$purpose = htmlspecialchars($request['purpose'] ?? '');
$notes = htmlspecialchars($request['notes'] ?? '');

if (!empty($request['created_at'])) {
    $date = date("F d, Y", strtotime($request['requested_at']));
} else {
    $date = date("F d, Y");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>
<?= htmlspecialchars($document_type) ?> - <?= $requestor ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    background: #eeeeee;
    font-family: "Times New Roman", Times, serif;
}

.print-controls {
    text-align: center;
    margin-bottom: 20px;
}

.print-controls button {
    border: none;
    padding: 12px 22px;
    margin: 5px;
    border-radius: 5px;
    font-size: 15px;
    cursor: pointer;
}

.print-btn {
    background: #198754;
    color: white;
}

.close-btn {
    background: #555;
    color: white;
}

.document {
    width: 8.5in;
    min-height: 11in;
    margin: auto;
    background: white;
    padding: 55px 70px;
    box-shadow: 0 0 10px rgba(0,0,0,.2);
}

.header {
    text-align: center;
    line-height: 1.4;
}

.header p {
    margin: 2px;
}

.header h3 {
    margin: 5px;
}

.title {
    text-align: center;
    margin-top: 45px;
    margin-bottom: 35px;
}

.title h1 {
    font-size: 25px;
    text-decoration: underline;
}

.content {
    font-size: 17px;
    line-height: 2;
    text-align: justify;
}

.content p {
    margin-bottom: 20px;
}

.center {
    text-align: center;
}

.bold {
    font-weight: bold;
}

.signature {
    margin-top: 80px;
    text-align: center;
}

.signature-name {
    font-weight: bold;
    text-decoration: underline;
}

.table-info {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    font-size: 16px;
}

.table-info td {
    border: 1px solid #000;
    padding: 10px;
}

.label {
    width: 35%;
    font-weight: bold;
}

.note-box {
    border: 1px solid #000;
    padding: 15px;
    min-height: 100px;
    margin-top: 15px;
}

.footer {
    margin-top: 60px;
    text-align: center;
    font-size: 13px;
}

@media print {

    body {
        background: white;
        padding: 0;
    }

    .print-controls {
        display: none;
    }

    .document {
        width: 100%;
        min-height: 100vh;
        box-shadow: none;
        padding: 55px 70px;
    }

}

</style>

</head>

<body>


 

<div class="print-controls">

    <button class="print-btn" onclick="window.print()">
        🖨 Print Document
    </button>

    <button class="close-btn" onclick="window.close()">
        Close
    </button>

</div>


<div class="document">


 
 
 

<div class="header">

    <p>REPUBLIC OF THE PHILIPPINES</p>

    <p>PROVINCE OF CAGAYAN</p>

    <p>MUNICIPALITY OF STO. NIÑO</p>


</div>


<?php





if ($document_type == "Barangay Clearance"):

?>

<div class="title">

    <h1>BARANGAY CLEARANCE</h1>

</div>

<div class="content">

    <p>
        <strong>TO WHOM IT MAY CONCERN:</strong>
    </p>

    <p>
        This is to certify that
        <strong><?= $requestor ?></strong>
        is a resident of
        <strong>Barangay <?= $barangay ?></strong>,
        Municipality of Sto. Niño, Province of Cagayan.
    </p>

    <p>
        Based on the records available in this office,
        the above-named person is known to this barangay
        and is requesting this clearance for:
    </p>

    <p class="center bold">
        <?= $purpose ?>
    </p>

    <p>
        This certification is issued upon the request of
        the interested party for whatever legal purpose
        it may serve.
    </p>

    <p>
        Issued this <strong><?= $date ?></strong>
        at Barangay <?= $barangay ?>,
        Sto. Niño, Cagayan.
    </p>

</div>

<div class="signature">

    <p>Certified by:</p>

    <br>

    <p class="signature-name">
        PUNONG BARANGAY
    </p>

    <p>Punong Barangay</p>

</div>


<?php





elseif ($document_type == "Certificate of Residency"):

?>

<div class="title">

    <h1>CERTIFICATE OF RESIDENCY</h1>

</div>

<div class="content">

    <p>
        <strong>TO WHOM IT MAY CONCERN:</strong>
    </p>

    <p>
        This is to certify that
        <strong><?= $requestor ?></strong>
        is a bona fide resident of
        <strong>Barangay <?= $barangay ?></strong>,
        Municipality of Sto. Niño, Province of Cagayan.
    </p>

    <p>
        This certification is issued upon the request of
        the above-named person for the following purpose:
    </p>

    <p class="center bold">
        <?= $purpose ?>
    </p>

    <p>
        Issued this <strong><?= $date ?></strong>
        at Barangay <?= $barangay ?>,
        Sto. Niño, Cagayan.
    </p>

</div>

<div class="signature">

    <p>Certified by:</p>

    <br>

    <p class="signature-name">
        PUNONG BARANGAY
    </p>

    <p>Punong Barangay</p>

</div>


<?php





elseif ($document_type == "Certificate of Indigency"):

?>

<div class="title">

    <h1>CERTIFICATE OF INDIGENCY</h1>

</div>

<div class="content">

    <p>
        <strong>TO WHOM IT MAY CONCERN:</strong>
    </p>

    <p>
        This is to certify that
        <strong><?= $requestor ?></strong>
        is a resident of
        <strong>Barangay <?= $barangay ?></strong>,
        Municipality of Sto. Niño, Province of Cagayan.
    </p>

    <p>
        Based on the information and records available
        in this office, the above-named person belongs to
        a financially indigent household in this barangay.
    </p>

    <p>
        This certification is issued upon the request of
        the above-named person for:
    </p>

    <p class="center bold">
        <?= $purpose ?>
    </p>

    <p>
        Issued this <strong><?= $date ?></strong>
        at Barangay <?= $barangay ?>,
        Sto. Niño, Cagayan.
    </p>

</div>

<div class="signature">

    <p>Certified by:</p>

    <br>

    <p class="signature-name">
        PUNONG BARANGAY
    </p>

    <p>Punong Barangay</p>

</div>


<?php





elseif ($document_type == "Business Clearance"):

?>

<div class="title">

    <h1>BARANGAY BUSINESS CLEARANCE</h1>

</div>

<div class="content">

    <p>
        <strong>TO WHOM IT MAY CONCERN:</strong>
    </p>

    <p>
        This is to certify that the business/activity
        represented by the applicant
        <strong><?= $requestor ?></strong>
        is being processed/requested for clearance
        within the jurisdiction of
        <strong>Barangay <?= $barangay ?></strong>,
        Municipality of Sto. Niño, Province of Cagayan.
    </p>

    <table class="table-info">

        <tr>
            <td class="label">Applicant</td>
            <td><?= $requestor ?></td>
        </tr>

        <tr>
            <td class="label">Barangay</td>
            <td><?= $barangay ?></td>
        </tr>

        <tr>
            <td class="label">Purpose</td>
            <td><?= $purpose ?></td>
        </tr>

        <tr>
            <td class="label">Date Issued</td>
            <td><?= $date ?></td>
        </tr>

    </table>

    <p>
        This clearance is issued upon the request of the
        applicant for whatever lawful purpose it may serve.
    </p>

</div>

<div class="signature">

    <p>Approved by:</p>

    <br>

    <p class="signature-name">
        PUNONG BARANGAY
    </p>

    <p>Punong Barangay</p>

</div>


<?php





elseif ($document_type == "Animal Travel Pass"):

?>

<div class="title">

    <h1>ANIMAL TRAVEL PASS</h1>

</div>

<div class="content">

    <p>
        <strong>TO WHOM IT MAY CONCERN:</strong>
    </p>

    <p>
        This is to certify that
        <strong><?= $requestor ?></strong>,
        a resident of Barangay
        <strong><?= $barangay ?></strong>,
        has requested an Animal Travel Pass.
    </p>

    <table class="table-info">

        <tr>
            <td class="label">Owner / Requestor</td>
            <td><?= $requestor ?></td>
        </tr>

        <tr>
            <td class="label">Barangay</td>
            <td><?= $barangay ?></td>
        </tr>

        <tr>
            <td class="label">Purpose / Destination</td>
            <td><?= $purpose ?></td>
        </tr>

        <tr>
            <td class="label">Date Issued</td>
            <td><?= $date ?></td>
        </tr>

    </table>

    <p>
        <strong>Animal Information / Notes:</strong>
    </p>

    <div class="note-box">

        <?= nl2br($notes) ?>

    </div>

    <p>
        This travel pass is issued upon the request of
        the owner and is subject to applicable veterinary
        and animal transport requirements.
    </p>

</div>

<div class="signature">

    <p>Certified by:</p>

    <br>

    <p class="signature-name">
        PUNONG BARANGAY
    </p>

    <p>Punong Barangay</p>

</div>


<?php





else:

?>

<div class="title">

    <h1><?= strtoupper(htmlspecialchars($document_type)) ?></h1>

</div>

<div class="content">

    <p>
        <strong>TO WHOM IT MAY CONCERN:</strong>
    </p>

    <p>
        This document was requested by
        <strong><?= $requestor ?></strong>
        from Barangay
        <strong><?= $barangay ?></strong>.
    </p>

    <p>
        Purpose:
        <strong><?= $purpose ?></strong>
    </p>

    <?php if (!empty($notes)): ?>

        <p>
            Notes:
        </p>

        <div class="note-box">
            <?= nl2br($notes) ?>
        </div>

    <?php endif; ?>

</div>

<div class="signature">

    <p>Certified by:</p>

    <br>

    <p class="signature-name">
        PUNONG BARANGAY
    </p>

    <p>Punong Barangay</p>

</div>

<?php endif; ?>


<div class="footer">

    Document generated by Barangay Document Request System

</div>

</div>


<script>

window.onload = function() {

    setTimeout(function() {

        window.print();

    }, 500);

};

</script>

</body>

</html>