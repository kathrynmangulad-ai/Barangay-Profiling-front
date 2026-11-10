<?php
/**
 * Affidavit of Solo Parent (A4 portrait).
 *
 * Reached from pages/document_print.php once a request whose type mentions
 * "Solo Parent" is Released. Layout, seals, access checks, issue date and the
 * control number (SP-YYYY-NNN) come from includes/certificate.php.
 *
 * The Punong Barangay attests; the parent's name, age and he/she, his/her come
 * from the resident's profile. Children are not recorded as such, so the line
 * is pre-filled with the parent's household members under 18 - staff can
 * click it (like every underlined value) to correct it before printing.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/solo\s*parent/i', 'Affidavit of Solo Parent', 'SP');

/* ---- Children: the parent's household members under 18 ------------------ */
$children = [];
$resId = (int)($c['request']['resident_id'] ?? 0);
if ($resId > 0) {
    $q = $conn->prepare(
        'SELECT k.first_name, k.middle_name, k.last_name, k.suffix, k.age, k.birth_date
           FROM residents p
           JOIN residents k ON k.household_id = p.household_id AND k.id <> p.id AND k.deleted_at IS NULL
          WHERE p.id = ? AND p.household_id IS NOT NULL
          ORDER BY k.birth_date DESC, k.age ASC'
    );
    $q->bind_param('i', $resId);
    $q->execute();
    $rs = $q->get_result();
    while ($k = $rs->fetch_assoc()) {
        $kAge = !empty($k['birth_date']) ? (new DateTime($k['birth_date']))->diff($c['issued'])->y : (int)$k['age'];
        if ($kAge >= 18) { continue; }
        $name = implode(' ', array_filter([trim((string)$k['first_name']), trim((string)($k['middle_name'] ?? '')),
                                           trim((string)$k['last_name']), trim((string)($k['suffix'] ?? ''))], 'strlen'));
        $children[] = $name . ', ' . $kAge . ' ' . ($kAge === 1 ? 'year' : 'years') . ' old';
    }
    $q->close();
}
$childWord = count($children) === 1 ? 'a child named' : 'children named';
if (count($children) > 1) {
    $last = array_pop($children);
    $childList = implode('; ', $children) . ' and ' . $last;
} else {
    $childList = $children[0] ?? '';
}

$captainName = $c['captain_line'] !== '' ? preg_replace('/^HON\.\s+/', '', $c['captain_line']) : '';
$age = $c['age'] !== null ? (string)$c['age'] : '';

$body = '
    <p class="salutation">To Whom It May Concern:</p>

    <p class="indent">
        I, ' . cert_fill($captainName) . ' of legal age and the Barangay Chief Executive of
        ' . e($c['place_line']) . ' hereby attest that:
    </p>

    <ol class="attest">
        <li>That, ' . cert_fill($c['full_name']) . ', ' . cert_fill($age, 'age') . ' years old and resident of
            ' . e($c['place_line']) . '.</li>
        <li>That ' . e($c['he_she']) . ' has ' . $childWord . ' ' . cert_fill($childList, 'wide') . '.</li>
        <li>That ' . e($c['he_she']) . ' is not co-habiting with a co-parent or any other person who could help
            ' . e($c['him_her']) . ' in the daily sustenance and support of ' . e($c['his_her']) . ' children.</li>
        <li>That ' . e($c['he_she']) . ' is a Solo Parent.</li>
    </ol>

    <p class="issued center">
        Issued this ' . cert_fill($c['day'], 'short') . ' day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ' at ' . e($c['place_line']) . '.
    </p>';

cert_render($c, [
    'title_html'    => 'AFFIDAVIT <span class="of">of</span> SOLO PARENT',
    'body_html'     => $body,
    'signatory'     => 'right',
    'body_left'     => 25,
    'body_right'    => 25,
    'control_rows'  => [
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued At', '<span class="at">' . e($c['issued_at']) . '</span>'],
        ['Issued On', '<span class="val">' . e($c['issued']->format('m/d/Y')) . '</span>'],
    ],
    'validity_html' => 'Not Valid without the<br>Barangay Seal',
    'pdf_prefix'    => 'Affidavit-of-Solo-Parent',
]);
