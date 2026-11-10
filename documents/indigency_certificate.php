<?php
/**
 * Certificate of Indigency (A4 portrait).
 *
 * Reached from pages/document_print.php once a request whose type mentions
 * "Indigency" / "Indigent" is Released. Layout, seals, access checks, issue
 * date and the control number (COI-YYYY-NNN) come from includes/certificate.php.
 *
 * Filled from the resident's profile: name, age, civil status, he/she (from
 * sex). The request's purpose replaces "identification"; unknown values keep
 * the template's wording ("single/married", "he/she") to complete by hand.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/\bindigen(cy|t)\b/i', 'Certificate of Indigency', 'COI');

$age     = $c['age'] !== null ? (string)$c['age'] : '';
$civil   = $c['civil_status'] !== '' ? $c['civil_status'] : 'single/married';
$purpose = $c['purpose'] !== '' ? $c['purpose'] : 'identification';

$body = '
    <p class="salutation">To whom it may concern:</p>

    <p class="indent">
        THIS IS TO CERTIFY that ' . cert_fill($c['name_upper']) . '
        ' . cert_fill($age, 'age') . ' years old, ' . e($civil) . ', Filipino citizen is a bonafide
        resident of ' . e($c['place_line']) . '.
    </p>

    <p class="indent">
        This is to certify that ' . e($c['he_she']) . ' belongs to the indigent group based on the
        records filed in this office.
    </p>

    <p class="indent">
        This certification is issued upon the request of the above-named person for
        ' . e($purpose) . ' and for whatever legal purpose it may serve.
    </p>

    <p class="indent issued">
        Issued this ' . cert_fill($c['day'], 'short') . '
        day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ' at ' . e($c['place_line']) . '.
    </p>';

cert_render($c, [
    'title_html'    => 'CERTIFICATE <span class="of">of</span> INDIGENCY',
    'body_html'     => $body,
    'signatory'     => 'center',
    'body_left'     => 25,
    'body_right'    => 25,
    'control_rows'  => [
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued At', '<span class="at">' . e($c['issued_at']) . '</span>'],
        ['Issued On', '<span class="val">' . e($c['issued']->format('F j, Y')) . '</span>'],
    ],
    'validity_html' => 'This Certificate is not<br>Valid without the<br>Barangay Seal',
    'pdf_prefix'    => 'Certificate-of-Indigency',
]);
