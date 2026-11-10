<?php
/**
 * Certificate of Residency (A4 portrait).
 *
 * Reached from pages/document_print.php once a request whose type mentions
 * "Residency" / "Residence" is Released. Layout, seals, access checks, issue
 * date and the control number (COR-YYYY-NNN) come from includes/certificate.php.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/\bresiden(cy|ce)\b/i', 'Certificate of Residency', 'COR');

$age = $c['age'] !== null ? (string)$c['age'] : '';

$body = '
    <p class="salutation">To Whom It May Concern:</p>

    <p class="indent">
        THIS IS TO CERTIFY that ' . cert_fill($c['name_upper']) . '
        ' . cert_fill($age, 'age') . ' years old, is a bonafide resident of ' . e($c['place_line']) . '
        and as a law-abiding citizen in the community.
    </p>

    <p class="indent">
        This CERTIFICATION is being issued upon the request of the above-named person for
        whatever legal purpose it may serve.
    </p>

    <p class="indent issued">
        Issued this ' . cert_fill($c['day'], 'short') . '
        day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ' at ' . e($c['place_line']) . '.
    </p>';

cert_render($c, [
    'title_html'    => 'CERTIFICATE <span class="of">of</span> RESIDENCY',
    'body_html'     => $body,
    'signatory'     => 'center',
    'body_left'     => 26,
    'body_right'    => 18,
    'control_rows'  => [
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued At', '<span class="at">' . e($c['issued_at']) . '</span>'],
        ['Issued On', '<span class="val">' . e($c['issued']->format('F j, Y')) . '</span>'],
    ],
    'validity_html' => 'Not valid without the<br>Barangay Seal',
    'pdf_prefix'    => 'Certificate-of-Residency',
]);
