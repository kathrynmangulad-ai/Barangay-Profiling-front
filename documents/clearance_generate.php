<?php
/**
 * Barangay Clearance Generator
 *
 * Visual reference: Official Barangay Clearance issued by the Office of the
 * Punong Barangay (A4 portrait).
 *
 * Workflow:
 *   1. Resident/Staff requests a document -> document_requests.status = 'Pending'
 *   2. Staff processes -> status = 'Processing'
 *   3. Staff releases -> status = 'Released' (via actions/release_document.php)
 *   4. Only 'Released' requests can generate a clearance.
 *   5. This page is reachable from document_print.php after release.
 *
 * Integration points (reuse existing system):
 *   - config/config.php      (database connection, helpers, BASE_URL)
 *   - includes/auth.php      (require_login, require_staff, require_record_access,
 *                             scope_barangay, can_access_record, current_user)
 *   - actions/release_document.php (sets status to 'Released')
 *   - document_requests table:
 *       id, barangay_id, resident_id, requestor_name, document_type,
 *       purpose, notes, status, requested_at, released_at, control_no
 *   - residents table: last_name, first_name, middle_name, suffix, age,
 *       civil_status, barangay_id
 *   - barangays table: barangay_name
 */

require_once __DIR__ . '/../config/config.php';
require_login();

$rid = (int)($_GET['request_id'] ?? 0);
if ($rid <= 0) {
    die('Invalid request identifier.');
}

// Fetch the document request with barangay name. Only Barangay Clearance
// requests are served by this template.
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
        dr.control_no,
        dr.requested_at,
        dr.released_at,
        b.barangay_name
    FROM document_requests dr
    LEFT JOIN barangays b ON b.id = dr.barangay_id
    WHERE dr.id = ? AND dr.document_type = 'Barangay Clearance'"
);
if (!$stmt) {
    die('Prepare Error: ' . $conn->error);
}
$stmt->bind_param('i', $rid);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false || ($request = $result->fetch_assoc()) === false) {
    $stmt->close();
    die('Barangay Clearance request not found.');
}
$stmt->close();

// Only released clearances can be generated/printed.
if ((string)$request['status'] !== 'Released') {
    log_access('clearance_not_released', 'document_request', $rid, 'denied');
    deny_access('This Barangay Clearance has not been released yet. You can generate it once the barangay releases it.');
}

// Record-level access control.
require_record_access(
    $request['barangay_id'],
    isset($request['resident_id']) ? (int)$request['resident_id'] : null,
    'document_request',
    $rid
);

// Build the resident's full name and details from the residents table.
$resident = null;
if (!empty($request['resident_id'])) {
    $pr = $conn->prepare(
        "SELECT last_name, first_name, middle_name, suffix, age, civil_status,
                barangay_id, contact_no, address
         FROM residents
         WHERE id = ? AND deleted_at IS NULL LIMIT 1"
    );
    if ($pr) {
        $residentId = (int)$request['resident_id'];
        $pr->bind_param('i', $residentId);
        $pr->execute();
        $resident = $pr->get_result()->fetch_assoc();
        $pr->close();
    }
}

/**
 * Build a clean full name: "First Middle Last Suffix"
 */
function render_clearance_name(?array $row): string
{
    if (!$row) {
        return '';
    }
    $parts = array_filter(
        [
            trim((string)($row['first_name'] ?? '')),
            trim((string)($row['middle_name'] ?? '')),
            trim((string)($row['last_name'] ?? '')),
            trim((string)($row['suffix'] ?? '')),
        ],
        'strlen'
    );
    return implode(' ', $parts);
}

/**
 * Build "Last, First Middle Suffix" for formal documents.
 */
function render_clearance_name_formal(?array $row): string
{
    if (!$row) {
        return '';
    }
    $last   = trim((string)($row['last_name'] ?? ''));
    $first  = trim((string)($row['first_name'] ?? ''));
    $middle = trim((string)($row['middle_name'] ?? ''));
    $suffix = trim((string)($row['suffix'] ?? ''));
    $tail   = trim(implode(' ', array_filter([$first, $middle, $suffix], 'strlen')));
    if ($tail === '') { return $last; }
    if ($last === '') { return $tail; }
    return $last . ', ' . $tail;
}

$residentName  = render_clearance_name($resident);
$residentNameFormal = render_clearance_name_formal($resident);
$requestName   = trim((string)($request['requestor_name'] ?? ''));
$purpose       = trim((string)($request['purpose'] ?? ''));
$barangayName  = trim((string)($request['barangay_name'] ?? 'Barangay Centro Sur'));
$residentAge   = !empty($resident['age']) ? (int)$resident['age'] : null;
$residentCivil = !empty($resident['civil_status']) ? trim((string)$resident['civil_status']) : null;

// Use the request's barangay name (assigned barangay) for display.
$displayBarangay = $barangayName;

// Date of issuance: dynamically generated from the release date.
$issuedAt = $request['released_at']
    ? new DateTime('@' . strtotime($request['released_at']))
    : new DateTime();

$dayNum  = (int)$issuedAt->format('j');
$month   = $issuedAt->format('F');
$year    = $issuedAt->format('Y');

// Control number: reuse existing or generate a new one.
$controlNo = trim((string)($request['control_no'] ?? ''));
if ($controlNo === '') {
    $seq = 1;
    $max = $conn->query(
        "SELECT COALESCE(MAX(SUBSTRING(control_no, 10)), 0) + 1 AS n
         FROM document_requests
         WHERE control_no LIKE 'CLR-$year-%'"
    );
    if ($max && ($row = $max->fetch_assoc())) {
        $seq = (int)$row['n'];
    }
    $controlNo = sprintf('CLR-%d-%03d', $year, $seq);

    // Persist the generated control number back to the request.
    $upd = $conn->prepare(
        'UPDATE document_requests SET control_no = ? WHERE id = ?'
    );
    if ($upd) {
        $upd->bind_param('si', $controlNo, $rid);
        $upd->execute();
        $upd->close();
    }
}

// Fixed official name per the reference image.
define('CLR_HONORIFIC', 'HON. BENJIE VINCENT R. PAGURAYAN');

// Asset paths (relative to this file at documents/clearance_generate.php).
define('CLR_SEAL_PATH', asset('img/barangay_seal.png'));
define('CLR_LOGO_PATH', asset('img/logo.png'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay Clearance - <?= e($residentName ?: $requestName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Source+Serif+4:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 0; }
        .clearance-page { min-height: 100vh; display: flex; flex-direction: column; background: #ffffff; font-family: "Times New Roman", Times, serif; }
        .clearance-page__content { flex: 1; display: flex; flex-direction: column; padding: 24px 16px 40px; gap: 24px; }
        .clearance-toolbar { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
        .clearance-toolbar .btn { font-size: 14px; padding: 12px 26px; }
        .clearance-document { position: relative; flex: 1; display: flex; flex-direction: column; width: 210mm; min-height: 297mm; margin: 0 auto; background: #ffffff; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18); padding: 0; overflow: hidden; }
        .clearance-top-banner { position: relative; width: 100%; height: 42mm; background: #7B1F2B; overflow: hidden; }
        .clearance-top-banner::before { content: ''; position: absolute; bottom: -20px; left: 0; width: 100%; height: 40px; background: #ffffff; border-radius: 0 0 50% 50% / 0 0 100% 100%; transform: translateY(-50%); }
        .clearance-top-banner::after { content: ''; position: absolute; bottom: -8px; left: 0; width: 100%; height: 12px; background: #D96C1E; }
        .clearance-banner-content { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #ffffff; text-align: center; padding: 0 6mm; z-index: 2; }
        .clearance-gov-line { display: block; font-size: 12pt; font-weight: 600; letter-spacing: 1.5px; margin: 0; line-height: 1.35; }
        .clearance-province { display: block; font-size: 10pt; font-weight: 500; letter-spacing: 1px; margin: 0; line-height: 1.3; }
        .clearance-municipality { display: block; font-size: 10pt; font-weight: 500; letter-spacing: 1px; margin: 0; line-height: 1.3; }
        .clearance-barangay-name { display: block; font-size: 13pt; font-weight: 800; letter-spacing: 2.5px; margin: 1mm 0 0 0; line-height: 1.2; }
        .clearance-header { position: relative; display: flex; justify-content: space-between; align-items: flex-start; padding: 4mm 16mm 2mm 16mm; height: 30mm; z-index: 3; }
        .clearance-logo-left { height: 26mm; width: auto; display: block; }
        .clearance-logo-right { height: 26mm; width: auto; display: block; }
        .clearance-bagong-pilipinas { height: 22mm; width: auto; display: block; }
        .clearance-office-banner { display: flex; align-items: center; justify-content: center; text-align: center; margin: 2mm auto 4mm auto; padding: 2.5mm 8mm; background: #D96C1E; color: #ffffff; border-radius: 16px; box-shadow: 0 2px 8px rgba(217, 108, 30, 0.35); max-width: fit-content; }
        .clearance-office-banner span { font-size: 13pt; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; line-height: 1.3; }
        .clearance-title { text-align: center; margin: 2mm auto 6mm auto; }
        .clearance-title h1 { font-size: 24pt; font-weight: 800; letter-spacing: 4px; text-transform: uppercase; color: #1a1a1a; margin: 0; line-height: 1.2; }
        .clearance-watermark { position: absolute; inset: 0; display: block; pointer-events: none; z-index: 0; background-image: url("<?= e(CLR_SEAL_PATH) ?>"); background-repeat: no-repeat; background-position: center; background-size: 85%; opacity: 0.12; filter: grayscale(1); }
        .clearance-content { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; padding: 10mm 16mm 12mm 16mm; text-align: justify; }
        .clearance-meta { display: flex; justify-content: space-between; margin-bottom: 5mm; padding-bottom: 3mm; border-bottom: 1px solid #cccccc; }
        .clearance-meta-item { flex: 1; min-width: 150px; }
        .clearance-meta-label { font-size: 9.5pt; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: #444444; margin-bottom: 1.5mm; }
        .clearance-meta-value { font-size: 11pt; color: #1a1a1a; }
        .clearance-body p { font-size: 11.5pt; line-height: 1.85; margin: 0 0 4mm 0; text-align: justify; }
        .clearance-blank-line { display: inline-block; border-bottom: 1px solid #1a1a1a; width: 35mm; height: 0; }
        .clearance-date-line { display: inline-block; border-bottom: 1px solid #1a1a1a; width: 25mm; height: 0; }
        .clearance-month-line { display: inline-block; border-bottom: 1px solid #1a1a1a; width: 38mm; height: 0; }
        .clearance-signatures { display: flex; justify-content: center; align-items: flex-end; gap: 30mm; margin-top: 8mm; padding-top: 3mm; border-top: 1px solid #cccccc; }
        .clearance-signature { flex: 1; min-width: 0; text-align: center; }
        .clearance-signature-line { margin-top: 8mm; border-bottom: 1px solid #1a1a1a; width: 70mm; height: 0; margin-left: auto; margin-right: auto; }
        .clearance-signature-name { font-size: 11pt; font-weight: 800; text-decoration: underline; text-transform: uppercase; color: #1a1a1a; margin-top: 4mm; }
        .clearance-signature-designation { font-size: 10pt; font-weight: 600; color: #1a1a1a; margin-top: 1mm; }
        .clearance-footer { position: relative; z-index: 1; margin-top: auto; padding-top: 4mm; border-top: 1px solid #cccccc; }
        .clearance-footer-row { display: flex; justify-content: space-between; font-size: 9pt; color: #1a1a1a; }
        .clearance-footer-left { flex: 1; }
        .clearance-footer-right { flex: 1; text-align: right; }
        .clearance-footer-label { font-weight: 700; display: block; margin-bottom: 1mm; }
        .clearance-footer-blank { display: inline-block; border-bottom: 1px solid #1a1a1a; width: 30mm; height: 0; margin-left: 4px; }
        .clearance-footer-void { font-size: 8pt; line-height: 1.4; color: #444444; }
        @media print {
            body { background: #ffffff !important; margin: 0 !important; padding: 0 !important; }
            .clearance-page { min-height: 0 !important; height: auto !important; overflow: visible !important; box-shadow: none !important; }
            .clearance-page__content { padding: 0 !important; gap: 0 !important; }
            .clearance-toolbar { display: none !important; }
            .clearance-document { box-shadow: none !important; width: 210mm !important; min-height: 297mm !important; margin: 0 auto !important; background: #ffffff !important; }
            .clearance-document, .clearance-content, .clearance-signatures, .clearance-footer, .clearance-title, .clearance-body { break-inside: avoid !important; page-break-inside: avoid !important; }
        }
        .no-print { display: none; }
        .btn { display: inline-block; padding: 10px 24px; margin: 4px; border: none; border-radius: 4px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .btn-success { background: #198754; color: #ffffff; }
        .btn-secondary { background: #6c757d; color: #ffffff; }
        .btn--ghost { background: transparent; color: #444444; border: 1px solid #cccccc; }
        .btn--ghost:hover { background: #f5f5f5; }
    </style>
</head>
<body>

<div class="clearance-page">
    <div class="clearance-page__content">

        <!-- Screen-only toolbar -->
        <div class="clearance-toolbar">
            <a
                href="<?= e(url('pages/document_print.php?id=' . $rid)) ?>"
                class="btn btn--ghost"
                target="_blank"
                rel="noopener"
            >
                ← Back to Request Details
            </a>
            <button class="btn btn-success" type="button" onclick="window.print()">
                🖨 Print Clearance
            </button>
            <a href="#no-print" class="btn btn-secondary" id="download-pdf">
                📄 Download PDF
            </button>
        </div>

        <!-- The printable clearance document -->
        <div class="clearance-document no-print">

            <!-- Transparent seal watermark behind content -->
            <div class="clearance-watermark" aria-hidden="true"></div>

            <!-- Top maroon banner with curved bottom and gold accent -->
            <div class="clearance-top-banner">
                <div class="clearance-banner-content">
                    <span class="clearance-gov-line">Republic of the Philippines</span>
                    <span class="clearance-province">Province of Cagayan</span>
                    <span class="clearance-municipality">Municipality of Sto. Niño</span>
                    <span class="clearance-barangay-name"><?= e(strtoupper($displayBarangay)) ?></span>
                </div>
            </div>

            <!-- Header logos -->
            <div class="clearance-header">
                <img class="clearance-logo-left" src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Seal_of_the_Philippines.svg/1200px-Seal_of_the_Philippines.svg.png" alt="Republic of the Philippines Seal">
                <img class="clearance-bagong-pilipinas" src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Bagong_Pilipinas_logo.svg/1200px-Bagong_Pilipinas_logo.svg.png" alt="Bagong Pilipinas">
                <img class="clearance-logo-right" src="<?= e(CLR_LOGO_PATH) ?>" alt="Barangay Logo">
            </div>

            <!-- Office of the Punong Barangay banner -->
            <div class="clearance-office-banner"><span>Office of the Punong Barangay</span></div>

            <!-- Document title -->
            <div class="clearance-title"><h1>Barangay Clearance</h1></div>

            <!-- Content -->
            <div class="clearance-content">
                <div class="clearance-meta">
                    <div class="clearance-meta-item">
                        <div class="clearance-meta-label">Control No.</div>
                        <div class="clearance-meta-value"><?= e($controlNo) ?></div>
                    </div>
                    <div class="clearance-meta-item">
                        <div class="clearance-meta-label">Date of Issuance</div>
                        <div class="clearance-meta-value"><?= e($month . ' ' . $dayNum . ', ' . $year) ?></div>
                    </div>
                </div>

                <p><strong>To whom it may concern:</strong></p>

                <p>
                    THIS IS TO CERTIFY that
                    <span class="clearance-blank-line"></span>
                    <strong><?= e($residentNameFormal ?: $residentName ?: $requestName) ?></strong>,
                    <?= $residentAge ? (int)$residentAge : '_____' ?> years old,
                    <?= $residentCivil ? e($residentCivil) : 'single/married' ?>,
                    Filipino citizen is a bonafide resident of
                    <strong><?= e(strtoupper($displayBarangay)) ?></strong>,
                    Sto. Niño, Cagayan.
                </p>

                <p>Furthermore, he/she has no derogatory records filed in this office as to this date.</p>

                <p>This CERTIFICATION is issued upon the request of the above-named person for identification and for whatever legal purpose/s it may serve him/her best.</p>

                <p>
                    Given this
                    <span class="clearance-date-line"></span>
                    <strong><?= (int)$dayNum ?></strong> day of
                    <span class="clearance-month-line"></span>
                    <strong><?= e($month) ?></strong>,
                    <strong><?= (int)$year ?></strong>
                    at Barangay Centro Sur, Sto. Niño, Cagayan.
                </p>
            </div>

            <!-- Signature section -->
            <div class="clearance-signatures">
                <div class="clearance-signature">
                    <div class="clearance-signature-line"></div>
                    <div class="clearance-signature-name"><?= e(CLR_HONORIFIC) ?></div>
                    <div class="clearance-signature-designation">Punong Barangay</div>
                </div>
            </div>

            <!-- Footer -->
            <div class="clearance-footer">
                <div class="clearance-footer-row">
                    <div class="clearance-footer-left">
                        <div class="clearance-footer-label">OR No.:</div>
                        <div class="clearance-footer-label">Control No.:</div>
                        <div class="clearance-footer-label">Issued At:</div>
                    </div>
                    <div class="clearance-footer-right">
                        <div><span class="clearance-footer-blank"></span></div>
                        <div><?= e($controlNo) ?></div>
                        <div>Centro Sur, Sto. Niño, Cagayan</div>
                    </div>
                </div>
                <div class="clearance-footer-void" style="margin-top: 3mm;">
                    This clearance is void<br>Without Barangay Seal<br>Valid for Six (6) Months
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