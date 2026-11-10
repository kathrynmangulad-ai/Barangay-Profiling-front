<?php
/**
 * Certification (identification) - A4 portrait, plain letterhead.
 *
 * Reached from pages/document_print.php once a "Certification" request is
 * Released. Access checks, issue date and the control number (CRT-YYYY-NNN)
 * come from includes/certificate.php.
 *
 * Personal details come from the resident's profile (name, address, date of
 * birth, civil status, gender) and their profile photo fills the 2x2 box.
 * Place of birth and parents are not recorded, so those lines are blank for
 * staff to type in on screen (click the line) or to write by hand. The O.R.
 * and CTC lines are left blank.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/^\s*certification\s*$/i', 'Certification', 'CRT');

$addressParts = array_filter([$c['address'], $c['barangay'], CERT_MUNICIPALITY, CERT_PROVINCE], 'strlen');
$address = implode(', ', array_unique($addressParts));
$purpose = mb_strtoupper($c['purpose'] !== '' ? $c['purpose'] : 'identification', 'UTF-8');

$row = function (string $label, string $value) {
    return '<tr><td class="k">' . e($label) . '</td><td class="c">' . ($label !== '' ? ':' : '') . '</td>'
         . '<td class="v">' . cert_fill($value) . '</td></tr>';
};

$photo = $c['photo']
    ? '<img src="' . e($c['photo']) . '" alt="Photo of ' . e($c['full_name']) . '">'
    : '2 X 2';

$body = '
    <p class="caps salutation">To whom it may concern:</p>

    <p class="indent">
        This is to certify that the person whose name, picture and signature/thumb mark appears
        below is a resident of this barangay:
    </p>

    <div class="info">
        <table>
            ' . $row('Name', $c['full_name']) . '
            ' . $row('Address', $address) . '
            ' . $row('Date of Birth', $c['birth_date'] ? $c['birth_date']->format('F j, Y') : '') . '
            ' . $row('Place of Birth', '') . '
            ' . $row('Civil Status', mb_strtoupper($c['civil_status'], 'UTF-8')) . '
            ' . $row('Gender', mb_strtoupper($c['sex'], 'UTF-8')) . '
            ' . $row('Parents', '') . '
            ' . $row('', '') . '
        </table>
        <div class="photo-box">' . $photo . '</div>
    </div>

    <div class="sig-line">(Signature)</div>

    <p class="indent">
        This certification is issued upon the request of the above-mentioned person
        FOR ' . e($purpose) . ' purposes.
    </p>

    <p class="indent">
        Issued this ' . cert_fill($c['day'], 'short') . ' day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ' at ' . e($c['issued_at']) . '.
    </p>';

cert_render($c, [
    'plain'         => true,
    'title_html'    => 'CERTIFICATION',
    'body_html'     => $body,
    'signatory'     => 'right',
    'body_left'     => 12,
    'body_right'    => 14,
    'compact'       => true,
    'control_grid'  => true,
    'control_rows'  => [
        ['Paid Under O.R. No.', '<span class="blank" data-edit></span>'],
        ['Date', '<span class="blank" data-edit></span>'],
        ['CTC No.', '<span class="blank" data-edit></span>'],
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued on', '<span class="val">' . e($c['issued']->format('F j, Y')) . '</span>'],
        ['Issued at', '<span class="at">' . e($c['issued_at']) . '</span>'],
    ],
    'pdf_prefix'    => 'Certification',
]);
