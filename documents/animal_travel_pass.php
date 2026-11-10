<?php
/**
 * Animal Travel Pass (A4 portrait).
 *
 * Reached from pages/document_print.php once a request whose type mentions
 * "Animal" or "Travel Pass" is Released. Layout, seals, access checks, issue
 * date and the control number (ATP-YYYY-NNN) come from includes/certificate.php.
 *
 * Wording written for the system (no Word template). The owner comes from the
 * resident's profile; the request's purpose fills "for the purpose of". * notes the destination. Animal details are not recorded, so the table rows are
 * filled from the request form (Animal details); staff can still click any line to correct it.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/animal|travel\s*pass/i', 'Animal Travel Pass', 'ATP');

$age         = $c['age'] !== null ? (string)$c['age'] : '';
$purpose     = $c['purpose'];
// Animals and destination come from the request form (document_requests.details);
// requests made before those fields existed fall back to the notes / blank lines.
$details     = json_decode((string)($c['request']['details'] ?? ''), true) ?: [];
$animals     = array_values(array_filter((array)($details['animals'] ?? []), 'is_array'));
$destination = trim((string)($details['destination'] ?? ''));
if ($destination === '') {
    $destination = trim(preg_replace('/\s+/', ' ', (string)($c['request']['notes'] ?? '')));
}

$animalRows = '';
for ($i = 0; $i < max(2, count($animals)); $i++) {
    $a = $animals[$i] ?? [];
    $animalRows .= '<tr><td>' . cert_fill((string)($a['kind'] ?? '')) . '</td><td>' . cert_fill((string)($a['heads'] ?? ''))
                 . '</td><td>' . cert_fill((string)($a['sex'] ?? '')) . '</td><td>' . cert_fill((string)($a['color'] ?? '')) . '</td></tr>';
}

$body = '
    <style>.body--compact .salutation{margin-bottom:5mm}.body--compact .signatory{margin-top:11mm}.body--compact .control{margin-top:6mm}.animals{margin:0 0 3mm}</style>
    <p class="salutation">To whom it may concern:</p>

    <p class="indent left">
        THIS IS TO CERTIFY that ' . cert_fill($c['name_upper']) . ', ' . cert_fill($age, 'age') . ' years old,
        a bonafide resident of ' . e($c['place_line']) . ', is the lawful owner of the animal/s
        described below and is hereby allowed to transport the same.
    </p>

    <table class="animals">
        <thead><tr><th>Kind of Animal</th><th>No. of Heads</th><th>Sex</th><th>Color / Markings</th></tr></thead>
        <tbody>' . $animalRows . '</tbody>
    </table>

    <p class="left">
        From: ' . cert_fill($c['place_line'], 'route') . '<br>
        To: ' . cert_fill($destination, 'route') . '<br>
        For the purpose of: ' . cert_fill($purpose, 'route') . '
    </p>

    <p class="indent">
        This is to certify further that the above-mentioned animal/s were not stolen and, as far
        as the records of this office show, are free from any contagious or communicable disease.
        This pass is issued upon the request of the above-named person for whatever legal purpose
        it may serve ' . e($c['him_her']) . ' best.
    </p>

    <p class="indent issued">
        Issued this ' . cert_fill($c['day'], 'short') . '
        day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ' at ' . e($c['place_line']) . '.
    </p>';

cert_render($c, [
    'title_html'    => 'ANIMAL TRAVEL PASS',
    'body_html'     => $body,
    'signatory'     => 'center',
    'body_left'     => 25,
    'body_right'    => 25,
    'compact'       => true,
    'control_rows'  => [
        ['OR No.', '<span class="blank" data-edit></span>'],
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued At', '<span class="at">' . e($c['issued_at']) . '</span>'],
        ['Issued On', '<span class="val">' . e($c['issued']->format('F j, Y')) . '</span>'],
    ],
    'validity_html' => 'This pass is void<br>without the Barangay Seal<br>Valid for three (3) days<br>from date of issue',
    'pdf_prefix'    => 'Animal-Travel-Pass',
]);
