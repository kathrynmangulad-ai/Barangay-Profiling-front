<?php
/**
 * Extra, document-specific inputs on a request (stored as JSON in
 * document_requests.details) - e.g. the animals on an Animal Travel Pass.
 *
 * Used by both request forms (resident/document_request.php and
 * pages/documents.php) and read back by the certificate pages.
 *
 *   details_kind($type)                 'animal' or null
 *   details_fields_html($old)           the extra form fields (shown by JS only
 *                                       when the chosen type needs them)
 *   details_from_post($type, $error)    validated JSON string, or null
 *   details_decode($json)               array
 */

const DETAILS_MAX_ANIMALS = 3;

function details_column_exists(mysqli $conn): bool
{
    static $has = null;
    if ($has === null) {
        $has = false;
        if ($r = $conn->query("SHOW COLUMNS FROM document_requests LIKE 'details'")) {
            $has = $r->num_rows > 0;
            $r->free();
        }
    }
    return $has;
}

/** Which set of extra inputs a document type needs, if any. */
function details_kind(string $type): ?string
{
    return preg_match('/animal|travel\s*pass/i', $type) ? 'animal' : null;
}

function details_decode(?string $json): array
{
    $d = json_decode((string)$json, true);
    return is_array($d) ? $d : [];
}

/**
 * Validate the extra inputs for $type from $_POST. Returns the JSON to store
 * (null when the type has no extra inputs) and sets $error on bad input.
 */
function details_from_post(string $type, ?string &$error): ?string
{
    $error = null;
    if (details_kind($type) !== 'animal') {
        return null;
    }
    $clip = function ($v, int $max) { return mb_substr(trim(preg_replace('/\s+/', ' ', (string)$v)), 0, $max, 'UTF-8'); };
    $animals = [];
    foreach (array_slice((array)($_POST['animal'] ?? []), 0, DETAILS_MAX_ANIMALS) as $row) {
        $a = [
            'kind'  => $clip($row['kind'] ?? '', 60),
            'heads' => $clip($row['heads'] ?? '', 10),
            'sex'   => $clip($row['sex'] ?? '', 20),
            'color' => $clip($row['color'] ?? '', 80),
        ];
        if (implode('', $a) === '') { continue; }
        if ($a['kind'] === '' || $a['heads'] === '') {
            $error = 'For each animal, please give at least the kind of animal and the number of heads.';
            return null;
        }
        if (!preg_match('/^\d{1,4}$/', $a['heads']) || (int)$a['heads'] < 1) {
            $error = 'Number of heads must be a whole number (1 or more).';
            return null;
        }
        $animals[] = $a;
    }
    $destination = $clip($_POST['destination'] ?? '', 150);
    if (!$animals) {
        $error = 'Please list the animal/s to be transported (kind and number of heads).';
        return null;
    }
    if ($destination === '') {
        $error = 'Please enter where the animal/s will be transported to.';
        return null;
    }
    return json_encode(['animals' => $animals, 'destination' => $destination], JSON_UNESCAPED_UNICODE);
}

/** The extra form fields, plus the script that shows them for matching types. */
function details_fields_html(array $old = []): string
{
    $rows = (array)($old['animal'] ?? []);
    $h = '<fieldset class="details-fields full" data-details="animal" hidden'
       . ' style="border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin:0">'
       . '<legend style="padding:0 6px;font-weight:700">Animal details</legend>'
       . '<p class="field-hint" style="margin:0 0 10px">List each kind of animal to be transported. Kind and number of heads are required.</p>'
       . '<div style="display:grid;grid-template-columns:2fr 1fr 1fr 2fr;gap:8px 10px;align-items:end">'
       . '<label style="margin:0">Kind of animal</label><label style="margin:0">No. of heads</label>'
       . '<label style="margin:0">Sex</label><label style="margin:0">Color / markings</label>';
    for ($i = 0; $i < DETAILS_MAX_ANIMALS; $i++) {
        $r = (array)($rows[$i] ?? []);
        $sex = (string)($r['sex'] ?? '');
        $h .= '<input name="animal[' . $i . '][kind]" maxlength="60" placeholder="' . ($i === 0 ? 'e.g. Carabao' : '') . '" value="' . e($r['kind'] ?? '') . '">'
            . '<input name="animal[' . $i . '][heads]" inputmode="numeric" maxlength="4" placeholder="' . ($i === 0 ? 'e.g. 2' : '') . '" value="' . e($r['heads'] ?? '') . '">'
            . '<select name="animal[' . $i . '][sex]">';
        foreach (['' => '—', 'Male' => 'Male', 'Female' => 'Female', 'Mixed' => 'Mixed'] as $v => $label) {
            $h .= '<option value="' . e($v) . '"' . ($sex === $v ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        $h .= '</select>'
            . '<input name="animal[' . $i . '][color]" maxlength="80" placeholder="' . ($i === 0 ? 'e.g. Black, white patch on head' : '') . '" value="' . e($r['color'] ?? '') . '">';
    }
    $h .= '</div>'
        . '<div style="margin-top:12px"><label for="details-destination">Destination (to)</label>'
        . '<input id="details-destination" name="destination" maxlength="150" placeholder="e.g. Tuguegarao City, Cagayan" value="' . e($old['destination'] ?? '') . '"></div>'
        . '</fieldset>'
        . '<script>(function(){var f=document.currentScript.previousElementSibling,form=f.closest("form");'
        . 'var t=form&&form.querySelector("[name=document_type]");if(!t)return;'
        . 'function sync(){var on=/animal|travel\\s*pass/i.test(t.value);f.hidden=!on;'
        . 'f.querySelectorAll("input,select").forEach(function(el){el.disabled=!on;});'
        . 'var first=f.querySelector("[name=\'animal[0][kind]\']"),dest=f.querySelector("[name=destination]");'
        . 'if(first){first.required=on;}if(dest){dest.required=on;}}'
        . 't.addEventListener("input",sync);t.addEventListener("change",sync);sync();})();</script>';
    return $h;
}
