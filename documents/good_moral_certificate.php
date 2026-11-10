<?php

/**
 * Certificate of Good Moral Character Generator
 *
 * Visual reference: Barangay Centro Sur certificate template (A4 portrait).
 *
 * Workflow:
 *   1. Resident requests a document -> status = 'Pending' -> 'Processing'
 *      -> 'Released' (by the secretary using release_document.php).
 *   2. Only 'Released' requests can generate a certificate.
 *   3. This page is only reachable from document_print.php after release.
 *
 * Integration points (reuse existing system):
 *   - config/config.php      (database connection, helpers, BASE_URL)
 *   - includes/auth.php      (require_login, require_staff, require_secretary)
 *   - actions/release_document.php (sets status to 'Released')
 *   - document_requests table:
 *       id, barangay_id, resident_id, requestor_name, document_type,
 *       purpose, status, requested_at, released_at, processed_by, processed_at
 */

require_once __DIR__ . '/../config/config.php';
require_login();

// This route is intended to be opened BY the system right after a request
// transitions to "Released" (see actions/release_document.php).
// Outright direct GET access without a valid released request is denied.
$rid = (int)($_GET['request_id'] ?? 0);
if ($rid <= 0) {
    die('Invalid request identifier.');
}

// Fetches a Released certificate so the secretary can print/download it.
$stmt = $conn->prepare(
    "SELECT
        dr.id,
        dr.barangay_id,
        dr.resident_id,
        dr.requestor_name,
        dr.document_type,
        dr.purpose,
        dr.notes,
        dr.status,
        dr.requested_at,
        dr.released_at,
        b.barangay_name
    FROM document_requests dr
    LEFT JOIN barangays b ON b.id = dr.barangay_id
    WHERE dr.id = ? AND dr.status = 'Released'"
);
if (!$stmt) {
    die('Prepare Error: ' . $conn->error);
}
$stmt->bind_param('i', $rid);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false || ($request = $result->fetch_assoc()) === false) {
    $stmt->close();
    die('Certificate request not found or not yet released.');
}
$stmt->close();

// Build the resident's full name from the residents table. The request row
// already stores the requestor name, but the certificate must show the
// CURRENT resident profile name from the DB.
$resident = null;
if ($request['resident_id'] > 0) {
    $pr = $conn->prepare(
        "SELECT last_name, first_name, middle_name, suffix
         FROM residents
         WHERE id = ? AND deleted_at IS NULL LIMIT 1"
    );
    if ($pr) {
        $residentId = (int)$request['resident_id'];
        $pr->bind_param('i', $residentId);
        $pr->execute();
        $prResult = $pr->get_result();
        $resident = $prResult->fetch_assoc();
        $pr->close();
    }
}

/**
 * Build a clean full name.
 * Returns "First Middle Last Suffix" (spaces trimmed, empty parts dropped)
 * so the blank line in the wording reads naturally.
 */
function render_name(?array $row): string
{
    if (!$row || !isset($row['first_name'])) {
        return '';
    }
    $parts = array_filter(
        [
            trim((string)($row['first_name']   ?? '')),
            trim((string)($row['middle_name']  ?? '')),
            trim((string)($row['last_name']    ?? '')),
            trim((string)($row['suffix']       ?? '')),
        ],
        'strlen'
    );
    return implode(' ', $parts);
}

$residentName  = render_name($resident);
$requestName   = trim((string)($request['requestor_name'] ?? ''));
$documentType  = trim((string)($request['document_type']  ?? ''));
$purpose       = trim((string)($request['purpose']        ?? ''));
$barangay      = trim((string)($request['barangay_name']  ?? ''));
$notes         = trim((string)($request['notes']          ?? ''));

// Date of issuance: dynamically generated from the actual issuance date.
// released_at is set by release_document.php (NOW()).
$issuedAt = $request['released_at']
    ? new DateTime('@' . strtotime($request['released_at']))
    : new DateTime();

$dayNum  = (int)$issuedAt->format('j');
$month   = $issuedAt->format('F');
$year    = $issuedAt->format('Y');

// -------------------------------------------------------------------------
// Generate a unique control number per approved document request.
// Format: COGN-YYYY-XXX  (must be unique across the database).
// -------------------------------------------------------------------------
$seq = 1;
$max = $conn->query(
    "SELECT COALESCE(MAX(SUBSTRING(control_no, 8)), 0) + 1 AS n
     FROM document_requests
     WHERE control_no LIKE 'COGN-2026-%'"
);
if ($max && ($row = $max->fetch_assoc())) {
    $seq = (int)$row['n'];
}
$controlNo = sprintf('COGN-%d-%03d', $year, $seq);

// Defend against a duplicate (extremely unlikely). Retry with the next seq.
$chk = $conn->prepare("SELECT id FROM document_requests WHERE control_no = ? LIMIT 1");
$chk->bind_param('s', $controlNo);
$chk->execute();
$chkResult = $chk->get_result();
$duplicate = $chkResult->num_rows > 0;
$chk->close();

if ($duplicate) {
    $controlNo = sprintf('COGN-%d-%03d', $year, $seq + 1);
}

// Fixed official name per the reference image.
// These asset paths are relative to the certificate view.
// asset() already returns a BASE_URL path, and this file lives at
// documents/good_moral_certificate.php, so we prefix two levels.
define('CERT_SEAL_PATH', asset('img/barangay_seal.png'));
define('CERT_LOGO_PATH', asset('img/logo.png'));
define('CERT_BAGONG_PATH', asset('img/bagong_pilipinas.png'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= e($documentType . ' - ' . ($residentName ?: $requestName)) ?>
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Source+Serif+4:ital,wght@0,400;0,600;0,700;1,400&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="<?= e(asset('css/certificate.css')) ?>">
    <style>
        .certificate-heading .gov-line { font-family: "Source Serif 4", Georgia, "Times New Roman", serif; }
        .certificate-heading .municipal { font-family: "Source Serif 4", Georgia, "Times New Roman", serif; }
        .certificate-heading .barangay { font-family: "Playfair Display", Georgia, "Times New Roman", serif; letter-spacing: 2.6px; }

                <div class="certificate-signatures">
                    <div class="certificate-signature">
                        <div class="signature-line"></div>
                        <div class="certificate-signature-name">
                            <?= e($HONORIFIC) ?>
                        </div>
                        <div class="designation">Punong Barangay</div>
                    </div>

                    <div class="certificate-footer">
                        <div class="row">
                            <span class="label">Control No.</span>
                            <span class="value"><?= e($controlNo) ?></span>
                        </div>
                        <div class="row">
                            <span class="label">Issued At</span>
                            <span class="value">Centro Sur, Sto. Niño, Cagayan</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var downloadBtn = document.getElementById('download-pdf');
        if (!downloadBtn) return;
        downloadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });
    })();
</script>
</body>
</html>

        .certificate-banner span { font-family: "Playfair Display", Georgia, "Times New Roman", serif; }
        .certificate-title h1 { font-family: "Playfair Display", Georgia, "Times New Roman", serif; }
        .certificate-body p { font-family: "Source Serif 4", Georgia, "Times New Roman", serif; }
        .certificate-meta .meta-label,
        .certificate-meta .meta-value,
        .certificate-signature-name,
        .certificate-signature .designation,
        .certificate-footer { font-family: "Source Serif 4", Georgia, "Times New Roman", serif; }
    </style>
</head>
<body>

<div class="certificate-page">
    <div class="certificate-page__content">

        <div class="certificate-toolbar">
            <a
                href="<?= e(url('pages/document_print.php?id=' . $rid)) ?>"
                class="btn btn--ghost"
                target="_blank"
                rel="noopener"
            >
                ← Back to Request Details
            </a>
            <button class="btn btn-success" type="button" onclick="window.print()">
                🖨 Print Certificate
            </button>
            <a href="#no-print" class="btn btn-secondary" id="download-pdf">
                📄 Download PDF
            </a>
        </div>

        <div class="certificate-document no-print">
            <div class="certificate-watermark" aria-hidden="true"></div>

            <header class="certificate-header">
                <img class="cert-logo" src="<?= e(CERT_LOGO_PATH) ?>" alt="Barangay Logo">
                <img class="cert-bagongpilipinas" src="<?= e(CERT_BAGONG_PATH) ?>" alt="Bagong Pilipinas Logo">
                <img class="cert-seal" src="<?= e(CERT_SEAL_PATH) ?>" alt="Barangay Official Seal">
            </header>

            <div class="certificate-heading">
                <span class="gov-line">Republic of the Philippines</span>
                <span class="municipal">Province of Cagayan</span>
                <span class="municipal">Municipality of Sto. Niño</span>
                <span class="barangay">Barangay Centro Sur</span>
            </div>

            <div class="certificate-banner">
                <span>OFFICE OF THE PUNONG BARANGAY</span>
            </div>

            <div class="certificate-title">
                <h1>CERTIFICATION OF GOOD MORAL CHARACTER</h1>
            </div>

            <div class="certificate-body">

                <div class="certificate-meta">
                    <div class="meta-item">
                        <div class="meta-label">Control No.</div>
                        <div class="meta-value"><?= e($controlNo) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Date of Issuance</div>
                        <div class="meta-value"><?= e($month . ' ' . $dayNum . ', ' . $year) ?></div>
                    </div>
                </div>

                <p>To Whom It May Concern:</p>
                <p>
                    This is to certify that
                    <?= $residentName
                        ? e(htmlspecialchars($residentName, ENT_QUOTES, 'UTF-8'))
                        : e(htmlspecialchars($requestName, ENT_QUOTES, 'UTF-8'))
                    ?>
                    of legal age, Filipino, and a bona fide resident of Barangay Centro Sur, Sto. Niño, Cagayan.
                </p>
                <p>
                    This is to certify further that the above-mentioned person is known to me to be a person of Good Moral Character in our community and has no derogatory record in this Barangay as of this date.
                </p>
                <p>
                    This certification is being issued upon the request of the above-named person for whatever legal purpose it may serve.
                </p>

                <?php if ($notes !== ''): ?>
                    <p style="font-size:10.5pt; line-height:1.7;">
                        <strong>Additional Notes:</strong>
                        <?= nl2br(e($notes)) ?>
                    </p>
                <?php endif; ?>

$HONORIFIC = 'HON. BENJIE VINCENT R. PAGURAYAN';
