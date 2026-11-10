<?php
/**
 * Certification of Good Moral Character (A4 portrait).
 *
 * Reached from pages/document_print.php once a request whose type mentions
 * "Good Moral" is Released. Layout, seals, access checks, issue date and the
 * control number (COGN-YYYY-NNN) come from includes/certificate.php.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/good\s*moral/i', 'Certification of Good Moral Character', 'COGN');

// "of legal age" unless the resident is known to be a minor on the issue date.
$ageClause = ($c['age'] !== null && $c['age'] < 18) ? $c['age'] . ' years of age' : 'of legal age';

$body = '
    <p class="salutation">To Whom It May Concern:</p>

    <p class="indent">
        This is to certify that ' . cert_fill($c['name_upper']) . '
        ' . e($ageClause) . ', Filipino, and a bonafide resident of ' . e($c['place_line']) . '.
    </p>

    <p class="indent">
        This is to certify further that the above-mentioned person is known to me to be a
        person of Good Moral Character in our community and has no derogatory record in this
        Barangay as of this date.
    </p>

    <p class="indent">
        This certification is being issued upon the request of the above-named person for
        whatever legal purpose it may serve.
    </p>

    <p class="indent issued">
        Issued this ' . cert_fill($c['day'], 'short') . '
        day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ' at ' . e($c['place_line']) . '.
    </p>';

cert_render($c, [
    'title_html'    => 'CERTIFICATION <span class="of">of</span> GOOD MORAL CHARACTER',
    'body_html'     => $body,
    'signatory'     => 'right',
    'validity_html' => 'Not valid without the official dry seal.',
    'pdf_prefix'    => 'Good-Moral',
]);
