<?php
/**
 * Barangay Permit (A4 portrait).
 *
 * Reached from pages/document_print.php once a request whose type mentions
 * "Permit" is Released. Layout, seals, access checks, issue date and the
 * control number (BP-YYYY-NNN) come from includes/certificate.php.
 *
 * "To wit": the system does not store which kind of permit was asked for, so
 * the option is ticked from the request's purpose (business / transport-travel
 * keywords, otherwise "others"). The purpose goes on that option's first
 * specify line and the request notes on its second; the other options stay
 * blank to fill in by hand if needed. The OR No. line is left blank.
 */

require_once __DIR__ . '/../config/config.php';
require_login();
require_once BASE_PATH . '/includes/certificate.php';

$c = cert_load($conn, (int)($_GET['request_id'] ?? 0), '/\bpermit\b/i', 'Barangay Permit', 'BP');

$age     = $c['age'] !== null ? (string)$c['age'] : '';
$purpose = $c['purpose'];
$notes   = trim(preg_replace('/\s+/', ' ', (string)($c['request']['notes'] ?? '')));

$haystack = $purpose . ' ' . $notes;
if (preg_match('/business|negosyo|store|shop|stall|sari|vend|eatery|carinderia|establish|canteen|restaurant/i', $haystack)) {
    $kind = 'business';
} elseif (preg_match('/transport|travel|byahe|biyahe|vehicle|truck|deliver|haul|livestock|animal|cargo/i', $haystack)) {
    $kind = 'transport';
} else {
    $kind = 'others';
}

$option = function (string $key, string $label, int $lines) use ($kind, $purpose, $notes) {
    $on = ($kind === $key);
    $values = $on ? [$purpose, $notes] : ['', ''];
    $html = '<div class="towit__item"><span class="towit__box">' . ($on ? '(&#10003;)' : '(&nbsp;&nbsp;&nbsp;)') . '</span>'
          . '<span>' . e($label) . '</span></div><div class="towit__lines">';
    for ($i = 0; $i < $lines; $i++) {
        $html .= '<span class="line" data-edit>' . e($values[$i] ?? '') . '</span>';
    }
    return $html . '</div>';
};

$body = '
    <p class="salutation">To whom it may concern:</p>

    <p class="left">
        PERMIT is hereby granted to ' . cert_fill($c['name_upper']) . ',
        ' . cert_fill($age, 'age') . ' years old a resident of
        Barangay ' . cert_fill($c['barangay'], 'place') . ', Municipality of ' . cert_fill(CERT_MUNICIPALITY, 'place') . ',
        Province of ' . cert_fill(CERT_PROVINCE, 'place') . '.
    </p>

    <div class="towit">
        <p class="towit__label">To wit:</p>
        ' . $option('business', 'to establish business, specify:', 2) . '
        ' . $option('transport', 'to transport/travel, specify:', 2) . '
        ' . $option('others', 'others, specify:', 1) . '
    </div>

    <p>
        This PERMIT is issued upon the request of the above-named person for whatever legal
        purpose it may serve ' . e($c['him_her']) . ' best.
    </p>

    <p class="issued">
        Issued this ' . cert_fill($c['day'], 'short') . '
        day of ' . cert_fill($c['month'], 'month') . ',
        ' . e($c['year']) . ', at ' . e($c['place_line']) . '.
    </p>';

cert_render($c, [
    'title_html'    => 'BARANGAY PERMIT',
    'body_html'     => $body,
    'signatory'     => 'center',
    'body_left'     => 25,
    'body_right'    => 25,
    'compact'       => true,
    'control_rows'  => [
        ['OR No.', '<span class="blank"></span>'],
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued At', '<span class="at">' . e($c['issued_at']) . '</span>'],
    ],
    'validity_html' => 'This permit is void<br>Without Barangay Seal<br>Valid for Six (6) Months',
    'pdf_prefix'    => 'Barangay-Permit',
]);
