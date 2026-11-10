<?php
/**
 * Barangay Clearance (A4 portrait).
 *
 * Reached from pages/document_print.php (and pages/clearance_history.php) once
 * a "Barangay Clearance" request is Released. Layout, seals, access checks,
 * issue date and the control number (CLR-YYYY-NNN) come from
 * includes/certificate.php.
 *
 * Filled from the resident's profile: name, age, civil status and he/she,
 * him/her (from sex). The request's purpose replaces "identification"; where a
 * value is unknown the template's own wording ("single/married", "he/she")
 * stays so it can be completed by hand. The OR No. line is left blank for the
 * official receipt number.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/^\s*barangay\s+clearance\s*$/i', 'Barangay Clearance', 'CLR');

$age     = $c['age'] !== null ? (string)$c['age'] : '';
$civil   = $c['civil_status'] !== '' ? $c['civil_status'] : 'single/married';
$purpose = $c['purpose'] !== '' ? $c['purpose'] : 'identification';

$body = '
    <p class="salutation">To whom it may concern:</p>

    <p>
        THIS IS TO CERTIFY that ' . cert_fill($c['name_upper']) . ',
        ' . cert_fill($age, 'age') . ' years old, ' . e($civil) . ', Filipino citizen is a bonafide
        resident of ' . e($c['place_line']) . '.
    </p>

    <p>
        Furthermore, ' . e($c['he_she']) . ' has no derogatory records filed in this office as to this date.
    </p>

    <p>
        This CERTIFICATION is issued upon the request of the above-named person for
        ' . e($purpose) . ' and for whatever legal purpose/s it may serve ' . e($c['him_her']) . ' best.
    </p>

    <p class="issued">
        Given this ' . cert_fill($c['day'], 'short') . '
        day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ' at ' . e($c['place_line']) . '.
    </p>';

cert_render($c, [
    'title_html'    => 'BARANGAY CLEARANCE',
    'body_html'     => $body,
    'signatory'     => 'center',
    'body_left'     => 26,
    'body_right'    => 26,
    'control_rows'  => [
        ['OR No.', '<span class="blank"></span>'],
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued At', '<span class="at">' . e($c['issued_at']) . '</span>'],
    ],
    'validity_html' => 'This clearance is void<br>Without Barangay Seal<br>Valid for Six (6) Months',
    'pdf_prefix'    => 'Barangay-Clearance',
]);
