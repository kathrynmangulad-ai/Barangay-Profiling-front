<?php
/**
 * Shared certificate template (Certification of Good Moral Character,
 * Barangay Clearance, ...): one A4 portrait design matching the barangay's
 * Word templates - maroon header band, municipal seal + Bagong Pilipinas logo,
 * barangay seal, orange "Office of the Punong Barangay" bar, watermark seal,
 * Punong Barangay signatory, control number, Print and Download PDF.
 *
 * A certificate page does three things:
 *   $c = cert_load($conn, $requestId, '/good\s*moral/i', 'Certification of Good Moral Character', 'COGN');
 *   $body = '...paragraphs built with e() and cert_fill()...';
 *   cert_render($c, ['title_html' => ..., 'body_html' => $body, ...]);
 *
 * cert_load() enforces access (residents: their own request; secretaries:
 * their barangay), the document type and the Released status, fixes the
 * issue date (released_at) and assigns the control number <CODE>-YYYY-NNN once.
 *
 * Images (optional - the page works without them). Each barangay uploads its
 * own under Secretary > Document Logos (see includes/logos.php); until then the
 * built-in files are used:
 *   assets/img/logo.png                         municipal seal (left)
 *   assets/img/bagong_pilipinas.png             Bagong Pilipinas logo (left)
 *   assets/img/seals/<barangay-slug>.png        barangay seal with ribbons (right)
 *   assets/img/seals/<barangay-slug>-watermark.png
 *                                               plain round seal: watermark, and
 *                                               the right seal when there is no
 *                                               ribbon version
 * e.g. seals/centro-sur.png. A ribbon seal's medallion position is measured
 * once and cached beside it as <name>.json.
 */

require_once __DIR__ . '/logos.php';

if (!defined('CERT_PROVINCE')) {
    define('CERT_PROVINCE', 'Cagayan');
    define('CERT_MUNICIPALITY', 'Sto. Niño');
}

/** 1 -> "1st", 22 -> "22nd", 11 -> "11th". */
function cert_ordinal(int $n): string
{
    $suffix = 'th';
    if (!in_array($n % 100, [11, 12, 13], true)) {
        $suffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th';
    }
    return $n . $suffix;
}

/** An underlined fill-in value, e.g. the name on "This is to certify that ____". */
function cert_fill(string $text, string $modifier = ''): string
{
    // data-edit: staff can click it on screen to correct or complete the value
    // before printing (not saved).
    return '<span class="fill' . ($modifier !== '' ? ' fill--' . e($modifier) : '') . '" data-edit>' . e($text) . '</span>';
}

/** Assign <CODE>-YYYY-NNN to a request once (serialised with a DB lock). */
function cert_control_no(mysqli $conn, int $id, string $code, int $year): string
{
    $prefix = $code . '-' . $year . '-';
    $conn->query("SELECT GET_LOCK('bms_control_no', 5)");
    try {
        // Another tab may have assigned it while we waited for the lock.
        $s = $conn->prepare('SELECT control_no FROM document_requests WHERE id = ?');
        $s->bind_param('i', $id);
        $s->execute();
        $existing = trim((string)($s->get_result()->fetch_assoc()['control_no'] ?? ''));
        $s->close();
        if ($existing !== '') {
            return $existing;
        }

        $pos  = strlen($prefix) + 1;
        $like = $prefix . '%';
        $m = $conn->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(control_no, ?) AS UNSIGNED)), 0) AS n
                               FROM document_requests WHERE control_no LIKE ?');
        $m->bind_param('is', $pos, $like);
        $m->execute();
        $next = (int)($m->get_result()->fetch_assoc()['n'] ?? 0) + 1;
        $m->close();

        $no = sprintf('%s%03d', $prefix, $next);
        $u = $conn->prepare("UPDATE document_requests SET control_no = ? WHERE id = ? AND (control_no IS NULL OR control_no = '')");
        $u->bind_param('si', $no, $id);
        $u->execute();
        $u->close();
        return $no;
    } finally {
        $conn->query("SELECT RELEASE_LOCK('bms_control_no')");
    }
}

/**
 * Where the medallion of a ribbon seal sits inside its image, as fractions of
 * the image height: ['center' => medallion centre y, 'ring' => ring height].
 * Measured on the seal's green ring (smooth, unlike the sunburst edge, and
 * works for round or oval medallions): its top and bottom along the centre
 * column, scanning up from the bottom past the ribbons. Cached as <name>.json.
 */
function cert_seal_geometry(string $file): array
{
    $default = ['v' => 2, 'center' => 0.4045, 'ring' => 0.7117];   // Centro Sur's seal
    $cache = preg_replace('/\.png$/i', '.json', $file);
    if (is_file($cache) && filemtime($cache) >= filemtime($file)) {
        $g = json_decode((string)file_get_contents($cache), true);
        if (($g['v'] ?? 0) === 2 && isset($g['center'], $g['ring'])) { return $g; }
    }
    if (!function_exists('imagecreatefrompng') || !($im = @imagecreatefrompng($file))) { return $default; }
    $w = imagesx($im); $h = imagesy($im); $cx = intdiv($w, 2);
    $green = function ($y) use ($im, $cx) {
        $c = imagecolorat($im, $cx, $y);
        if ((($c >> 24) & 127) > 60) { return false; }
        $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
        return $g > 45 && $g > $r + 25 && $g > $b + 25;
    };
    // First run of 3 green pixels scanning down from the top / up from the bottom.
    $edge = function (int $from, int $to, int $dir) use ($green) {
        for ($y = $from, $run = 0; $y !== $to; $y += $dir) {
            $run = $green($y) ? $run + 1 : 0;
            if ($run >= 3) { return $y - 2 * $dir; }
        }
        return null;
    };
    $top = $edge(0, $h, 1);
    $bottom = $edge($h - 1, -1, -1);
    imagedestroy($im);
    if ($top === null || $bottom === null || ($bottom - $top) < $h * 0.3) { return $default; }
    $g = ['v' => 2, 'center' => round(($top + $bottom) / 2 / $h, 4), 'ring' => round(($bottom - $top) / $h, 4)];
    @file_put_contents($cache, json_encode($g));
    return $g;
}

/**
 * Load everything a certificate needs for request $rid, or stop with an
 * access-denied page. $typePattern is a regex the request's document_type
 * must match; $code prefixes the control number (e.g. COGN, CLR).
 */
function cert_load(mysqli $conn, int $rid, string $typePattern, string $docLabel, string $code): array
{
    if ($rid <= 0) {
        die('Invalid request identifier.');
    }

    /* ---- Request + barangay + its Punong Barangay --------------------- */
    $has_captain_col = false;
    if ($cc = $conn->query("SHOW COLUMNS FROM barangays LIKE 'punong_barangay'")) {
        $has_captain_col = $cc->num_rows > 0;
        $cc->free();
    }
    $captainSel = $has_captain_col ? 'b.punong_barangay' : 'NULL AS punong_barangay';
    $detailsSel = 'NULL AS details';
    if ($dc = $conn->query("SHOW COLUMNS FROM document_requests LIKE 'details'")) {
        if ($dc->num_rows > 0) { $detailsSel = 'dr.details'; }
        $dc->free();
    }
    $stmt = $conn->prepare(
        "SELECT dr.id, dr.barangay_id, dr.resident_id, dr.requestor_name, dr.document_type,
                dr.purpose, dr.notes, dr.status, dr.control_no, dr.requested_at, dr.processed_at, dr.released_at,
                b.barangay_name, $captainSel, $detailsSel
           FROM document_requests dr
           LEFT JOIN barangays b ON b.id = dr.barangay_id
          WHERE dr.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$request) {
        die('Document request not found.');
    }

    // Residents only ever see their own requests; secretaries only their barangay.
    require_record_access(
        $request['barangay_id'],
        $request['resident_id'] !== null ? (int)$request['resident_id'] : null,
        'document_request',
        $rid
    );
    if (!preg_match($typePattern, (string)$request['document_type'])) {
        deny_access('This request is not a ' . $docLabel . '.');
    }
    if ((string)$request['status'] !== 'Released') {
        log_access('certificate_not_released', 'document_request', $rid, 'denied');
        deny_access('This ' . $docLabel . ' has not been released yet. It can be printed once the barangay releases the request.');
    }

    /* ---- Date of issuance: the release date, fixed once --------------- */
    // Requests released before released_at was recorded get it stamped now
    // (from processed_at when available) so the printed date never changes.
    if (empty($request['released_at'])) {
        $fix = $conn->prepare('UPDATE document_requests SET released_at = COALESCE(processed_at, NOW()) WHERE id = ? AND released_at IS NULL');
        $fix->bind_param('i', $rid);
        $fix->execute();
        $fix->close();
        $re = $conn->prepare('SELECT released_at FROM document_requests WHERE id = ?');
        $re->bind_param('i', $rid);
        $re->execute();
        $request['released_at'] = $re->get_result()->fetch_assoc()['released_at'] ?? null;
        $re->close();
    }
    // Stored in Manila time (config.php sets the session time zone), so parse
    // it in the app time zone - not as a UTC timestamp.
    $issued = new DateTime($request['released_at'] ?: 'now');

    $controlNo = trim((string)($request['control_no'] ?? ''));
    if ($controlNo === '') {
        $controlNo = cert_control_no($conn, $rid, $code, (int)$issued->format('Y'));
    }

    /* ---- Resident ------------------------------------------------------- */
    $resident = null;
    if (!empty($request['resident_id'])) {
        $pr = $conn->prepare(
            'SELECT first_name, middle_name, last_name, suffix, age, birth_date, sex, civil_status, address, photo
               FROM residents WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $resId = (int)$request['resident_id'];
        $pr->bind_param('i', $resId);
        $pr->execute();
        $resident = $pr->get_result()->fetch_assoc() ?: null;
        $pr->close();
    }
    $fullName = '';
    if ($resident) {
        $fullName = implode(' ', array_filter([
            trim((string)$resident['first_name']),
            trim((string)($resident['middle_name'] ?? '')),
            trim((string)$resident['last_name']),
            trim((string)($resident['suffix'] ?? '')),
        ], 'strlen'));
    }
    if ($fullName === '') {
        $fullName = trim((string)$request['requestor_name']);
    }
    $age = null;
    if ($resident && !empty($resident['birth_date'])) {
        $age = (new DateTime($resident['birth_date']))->diff($issued)->y;
    } elseif ($resident && $resident['age'] !== null && (int)$resident['age'] > 0) {
        $age = (int)$resident['age'];
    }
    $sex = strtolower(trim((string)($resident['sex'] ?? '')));
    $sex = in_array($sex, ['male', 'female'], true) ? $sex : '';

    /* ---- Barangay, signatory, images ----------------------------------- */
    $brgyRaw  = trim((string)($request['barangay_name'] ?? ''));
    $barangay = ucwords(mb_strtolower($brgyRaw, 'UTF-8'), " -.");
    $captain  = trim(preg_replace('/^hon\.?\s+/i', '', trim((string)($request['punong_barangay'] ?? ''))));

    // The barangay's own uploads win; the built-in files are the fallback.
    $slug   = logo_slug($brgyRaw);
    $brgyId = (int)$request['barangay_id'];
    $ribbon = brgy_logo($brgyId, $slug, 'seal');
    $municipalSeal = brgy_logo($brgyId, $slug, 'municipal')['url'] ?? null;
    $bagongLogo    = brgy_logo($brgyId, $slug, 'bagong')['url'] ?? null;
    $barangaySeal  = $ribbon['url'] ?? null;
    $watermarkSeal = brgy_logo($brgyId, $slug, 'watermark')['url'] ?? null;
    $headerSeal      = $barangaySeal ?: $watermarkSeal;
    $headerSealRound = !$barangaySeal && $watermarkSeal;

    // Every ribbon seal is scaled so its green ring is 23mm tall, then nudged so
    // the medallion's centre (not the image's - the ribbons hang below) sits on
    // the header's centre line with the left logos.
    $sealStyle = '';
    if ($headerSeal && !$headerSealRound) {
        $geo = cert_seal_geometry($ribbon['path']);
        $sealH = 23 / $geo['ring'];
        $sealStyle = sprintf('height:%.2fmm;transform:translateY(%.2fmm)', $sealH, (0.5 - $geo['center']) * $sealH);
    }

    // Setup hints for administrators (screen only, never printed).
    $hints = [];
    if (is_admin()) {
        if ($captain === '') {
            $hints[] = 'No Punong Barangay is set for Barangay ' . e($barangay) . '. <a href="' . e(url('admin/barangays.php')) . '">Set it under Admin &rsaquo; Barangays</a>.';
        }
        $logosLink = '<a href="' . e(url('secretary/document_logos.php?barangay_id=' . $brgyId)) . '">Document Logos</a>';
        if (!$barangaySeal && !$watermarkSeal) {
            $hints[] = 'Barangay ' . e($barangay) . ' has no seal yet, so the top right is empty. Its secretary can upload one under ' . $logosLink . '.';
        }
        if (!$watermarkSeal) {
            $hints[] = 'Barangay ' . e($barangay) . ' has no plain round seal for the watermark. Its secretary can upload one under ' . $logosLink . '.';
        }
        if (!$bagongLogo) {
            $hints[] = 'The Bagong Pilipinas logo is missing. It can be uploaded under ' . $logosLink . '.';
        }
    }

    return [
        'rid'          => $rid,
        'request'      => $request,
        'doc_label'    => $docLabel,
        'full_name'    => $fullName,
        'name_upper'   => mb_strtoupper($fullName, 'UTF-8'),
        'age'          => $age,
        'sex'          => $sex,
        'he_she'       => $sex === 'male' ? 'he' : ($sex === 'female' ? 'she' : 'he/she'),
        'him_her'      => $sex === 'male' ? 'him' : ($sex === 'female' ? 'her' : 'him/her'),
        'his_her'      => $sex === 'male' ? 'his' : ($sex === 'female' ? 'her' : 'his/her'),
        'civil_status' => mb_strtolower(trim((string)($resident['civil_status'] ?? '')), 'UTF-8'),
        'purpose'      => trim((string)($request['purpose'] ?? '')),
        'issued'       => $issued,
        'day'          => cert_ordinal((int)$issued->format('j')),
        'month'        => $issued->format('F'),
        'year'         => $issued->format('Y'),
        'control_no'   => $controlNo,
        'barangay'     => $barangay,
        'place_line'   => 'Barangay ' . $barangay . ', ' . CERT_MUNICIPALITY . ' ' . CERT_PROVINCE,
        'issued_at'    => $barangay . ', ' . CERT_MUNICIPALITY . ' ' . CERT_PROVINCE,
        'captain_line' => $captain !== '' ? 'HON. ' . mb_strtoupper($captain, 'UTF-8') : '',
        'municipal_seal' => $municipalSeal,
        'bagong_logo'    => $bagongLogo,
        'round_seal'     => $watermarkSeal,
        'photo'          => ($resident && trim((string)$resident['photo']) !== '' && is_file(BASE_PATH . '/' . ltrim($resident['photo'], '/'))) ? url(ltrim($resident['photo'], '/')) : null,
        'address'        => trim((string)($resident['address'] ?? '')),
        'birth_date'     => ($resident && !empty($resident['birth_date'])) ? new DateTime($resident['birth_date']) : null,
        'header_seal'    => $headerSeal,
        'header_round'   => $headerSealRound,
        'seal_style'     => $sealStyle,
        'watermark'      => $watermarkSeal ?: ($barangaySeal ?: $municipalSeal),
        'hints'          => $hints,
        'back_url'       => is_resident() ? url('resident/document_request.php') : url('pages/documents.php?tab=released'),
        'back_label'     => is_resident() ? 'My Requests' : 'Document Requests',
    ];
}

/**
 * Output the certificate page.
 * $o: title_html   trusted HTML for the title (escape any data inside)
 *     body_html    trusted HTML paragraphs (built with e() / cert_fill())
 *     signatory    'right' (default) or 'center'
 *     body_left / body_right   text margins in mm (default 22 / 11)
 *     control_rows [[label, trusted value HTML], ...] (default Control No., Issued At)
 *     validity_html trusted HTML for the bottom-right note
 *     pdf_prefix   PDF file name prefix, e.g. "Good-Moral"
 *     compact      true = tighter spacing for longer certificates
 */
function cert_render(array $c, array $o): void
{
    $plain     = !empty($o['plain']);   // simpler letterhead (Certification): no band, watermark or Bagong Pilipinas logo
    $signatory = ($o['signatory'] ?? 'right') === 'center' ? ' signatory--center' : '';
    $bodyLeft  = (float)($o['body_left'] ?? 22);
    $bodyRight = (float)($o['body_right'] ?? 11);
    $rows = $o['control_rows'] ?? [
        ['Control No.', '<span class="val">' . e($c['control_no']) . '</span>'],
        ['Issued At', '<span class="at">' . e($c['issued_at']) . '</span>'],
    ];
    $pdfName = ($o['pdf_prefix'] ?? 'Certificate') . '_' . $c['control_no'] . '_'
             . preg_replace('/[^A-Za-z0-9]+/', '-', $c['full_name']) . '.pdf';
    $brgyLen = mb_strlen('Barangay ' . $c['barangay'], 'UTF-8');
    $brgyClass = $brgyLen > 26 ? ' barangay--xlong' : ($brgyLen > 21 ? ' barangay--long' : '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($c['doc_label']) ?> - <?= e($c['full_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Uncial+Antiqua&family=Fondamento:ital@0;1&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 0; }

    :root {
        --maroon-1: #4E0A0E;
        --maroon-2: #8C1C22;
        --tan: #C9A27C;
        --orange-1: #F08A3E;
        --orange-2: #E2701F;
        --ink: #1B1B1B;
        --uncial: "Uncial Antiqua", "Lucida Calligraphy", Georgia, serif;
        --script: "Fondamento", "Lucida Calligraphy", "Apple Chancery", Georgia, serif;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; }
    body {
        background: #E9EAEE;
        color: var(--ink);
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ---- Screen toolbar ---------------------------------------------- */
    .toolbar {
        position: sticky; top: 0; z-index: 10;
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center;
        padding: 10px 12px; background: #fff; border-bottom: 1px solid #D9DCE3;
    }
    .toolbar .btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 14px; border-radius: 9px; border: 1px solid #CBD2DC;
        background: #fff; color: #1F2937; text-decoration: none; cursor: pointer;
        font-family: inherit; font-size: 13.5px; font-weight: 600; line-height: 1;
    }
    .toolbar .btn:hover { background: #F3F4F6; }
    .toolbar .btn--primary { background: #8C1C22; border-color: #8C1C22; color: #fff; }
    .toolbar .btn--primary:hover { background: #6E141A; }
    .toolbar .btn[disabled] { opacity: .6; cursor: progress; }
    .toolbar .meta { font-size: 12.5px; color: #4B5563; margin-right: auto; }
    .hints {
        max-width: 794px; margin: 12px auto 0; padding: 10px 14px; border-radius: 10px;
        background: #FFF7E6; border: 1px solid #F3D8A6; color: #6B4A12; font-size: 13px; line-height: 1.5;
    }
    .hints p { margin: 2px 0; }
    .hints code { background: #fff; padding: 1px 4px; border-radius: 4px; }

    /* ---- The A4 sheet ------------------------------------------------- */
    .sheet-wrap { padding: 16px 0 32px; }
    .sheet-scale { width: 210mm; margin: 0 auto; transform-origin: top left; }
    .sheet {
        position: relative; overflow: hidden;
        width: 210mm; height: 297mm;
        background: #fff;
        box-shadow: 0 6px 28px rgba(0, 0, 0, .18);
    }

    /* Maroon header band with a slanted tan edge (drops ~4.5mm left to right).
       Built from a slightly rotated block (not an SVG or angled gradient) so
       the PDF renderer draws it exactly like the browser does. */
    .band { position: absolute; top: 0; left: 0; width: 210mm; height: 17mm; overflow: hidden; }
    .band__fill {
        position: absolute; left: -3mm; top: -12mm; width: 220mm; height: 21.7mm;
        background: linear-gradient(90deg, var(--maroon-1), var(--maroon-2) 55%, #5E0E13);
        border-bottom: 1.7mm solid var(--tan);
        transform: rotate(1.23deg); transform-origin: 3mm 100%;
    }
    .band__fill::after {
        content: ""; position: absolute; left: 0; right: 0; bottom: -2.3mm; height: .6mm;
        background: rgba(122, 22, 27, .55);
    }

    /* Header row: left logos | heading | barangay seal, on one centre line.
       Same 9mm side margins as the orange bar; the heading is centred on the
       page. Ribbon seals get their measured size and nudge inline. */
    .header-row {
        position: absolute; left: 9mm; right: 9mm; top: 17.5mm;
        display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center; column-gap: 3mm;
    }
    .left-logos { justify-self: start; display: flex; align-items: center; gap: 3.5mm; }
    .left-logos img { display: block; height: 25mm; width: auto; object-fit: contain; }
    .left-logos .bp { height: 24mm; }
    .seal-barangay { justify-self: end; display: block; height: 32.3mm; width: auto; object-fit: contain; }
    /* A plain round seal (no ribbons) is centred as-is, at the municipal seal's height. */
    .seal-barangay--round { height: 25mm; transform: none; }

    .heading { max-width: 84mm; text-align: center; font-family: var(--script); color: #222; }
    .heading .line { display: block; font-size: 11.5pt; line-height: 1.38; letter-spacing: .02em; }
    .heading .barangay { display: block; margin-top: 1.6mm; font-size: 17pt; line-height: 1.2; letter-spacing: .06em; color: #111; }
    .heading .barangay--long { font-size: 15pt; letter-spacing: .04em; }
    .heading .barangay--xlong { font-size: 13.5pt; letter-spacing: .02em; }

    .banner {
        position: absolute; left: 9mm; right: 9mm; top: 55mm; height: 8.5mm;
        border-radius: 1.5mm;
        background: linear-gradient(180deg, var(--orange-1), var(--orange-2));
        box-shadow: 0 1.2mm 1.6mm rgba(0, 0, 0, .28);
        display: flex; align-items: center; justify-content: center;
        font-family: var(--uncial); font-size: 12pt; letter-spacing: .05em; color: #2A1A10;
    }

    .watermark {
        position: absolute; left: 50%; top: 100mm; width: 142mm; height: 142mm;
        margin-left: -71mm; object-fit: contain; opacity: .16; pointer-events: none;
    }

    .title {
        position: absolute; left: 0; right: 0; top: 76mm; margin: 0;
        text-align: center; font-family: var(--uncial); font-weight: 400;
        font-size: 21pt; letter-spacing: .01em; color: #0E0E0E;
    }
    .title .of { font-family: var(--script); font-style: italic; font-size: 14pt; padding: 0 .6mm; }

    /* Body, signatory and control number flow top to bottom, so a long name
       or barangay wraps without ever overlapping the signature. */
    .body {
        position: absolute; top: 104mm;
        font-family: var(--script); font-size: 12pt; line-height: 1.6; color: #151515;
    }
    .body p { margin: 0 0 6.2mm; text-align: justify; }
    .body p.left { text-align: left; }   /* paragraphs with several fill-in blanks */
    .body .indent { text-indent: 14mm; }
    .body .salutation { margin-bottom: 12mm; }
    .fill {
        display: inline-block; min-width: 72mm; padding: 0 2mm; text-indent: 0;
        border-bottom: 1px solid #111; text-align: center; line-height: 1.3;
        font-weight: 700; letter-spacing: .02em;
    }
    .fill--short { min-width: 21mm; }
    .fill--month { min-width: 42mm; }
    .fill--age { min-width: 13mm; }
    .fill--place { min-width: 44mm; }
    .fill--wide { min-width: 90mm; text-align: left; }
    .fill--route { min-width: 100mm; text-align: left; }

    /* Animal details table (Animal Travel Pass). */
    .animals { width: 100%; border-collapse: collapse; background: none; margin: 1mm 0 5mm; font-size: 10.5pt; }
    .animals th {
        background: none; color: #151515; border: 0; padding: 1mm 1.5mm; text-align: center;
        font-family: var(--script); font-size: 10pt; font-weight: 700; letter-spacing: .02em; text-transform: none;
    }
    .animals td { border: 0; padding: .6mm 1.5mm; background: none; }
    .animals .fill { display: block; min-width: 0; min-height: 6mm; font-weight: 400; }
    .body p.center { text-align: center; text-indent: 0; }

    /* Numbered attestations (Affidavit of Solo Parent). */
    .attest { margin: 4mm 0 6mm 9mm; padding-left: 7mm; }
    .attest li { margin-bottom: 1.5mm; text-align: justify; }
    .issued { margin-top: 12mm !important; }

    /* Compact spacing for longer certificates (e.g. the Barangay Permit). */
    .body--compact { top: 98mm; line-height: 1.5; }
    .body--compact p { margin-bottom: 4mm; }
    .body--compact .salutation { margin-bottom: 7mm; }
    .body--compact .issued { margin-top: 6mm !important; }
    .body--compact .signatory { margin-top: 15mm; }
    .body--compact .control { margin-top: 9mm; }

    /* "To wit:" options with their specify lines (Barangay Permit). */
    .towit { margin: 0 0 4mm; }
    .towit__label { margin: 0 0 1mm !important; }
    .towit__item { display: grid; grid-template-columns: 11mm 1fr; margin-top: 2.5mm; }
    .towit__box { font-family: "Segoe UI Symbol", "DejaVu Sans", Arial, sans-serif; font-weight: 700; }
    .towit__lines { margin: .5mm 0 0 48mm; width: 72mm; }
    .towit__lines .line {
        display: block; min-height: 6.2mm; line-height: 6.2mm;
        border-bottom: 1px solid #3D62A8; text-align: center; font-weight: 700;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .signatory { margin: 24mm 0 0 54mm; width: 100mm; text-align: center; }
    .signatory .name {
        display: inline-block; min-width: 72mm; min-height: 6mm;
        font-family: var(--script); font-weight: 700; font-size: 12.5pt; letter-spacing: .03em;
        border-bottom: 1px solid #111; line-height: 1.3;
    }
    .signatory .role {
        display: block; text-align: right; padding-right: 3mm; margin-top: 1mm;
        font-family: var(--script); font-style: italic; font-size: 11pt;
    }
    .signatory--center { margin-left: auto; margin-right: auto; }
    .signatory--center .role { text-align: center; padding-right: 0; }

    .control { margin-top: 16mm; font-family: var(--script); font-size: 10pt; line-height: 1.75; }
    .control .val { display: inline-block; min-width: 47mm; border-bottom: 1px solid #111; padding: 0 1.5mm; font-weight: 700; }
    .control .blank { display: inline-block; min-width: 40mm; border-bottom: 1px solid #111; }
    .control .at { text-decoration: underline; }
    /* Labels in one column, values lined up after them (Certification). */
    .control--grid > div { display: grid; grid-template-columns: 34mm 62mm; align-items: end; }
    .control--grid .val, .control--grid .blank { min-width: 0; width: 100%; }
    .control--grid .at { justify-self: start; }

    .validity {
        position: absolute; right: 12mm; bottom: 9mm;
        font-family: var(--script); font-style: italic; font-size: 9pt; line-height: 1.45; color: #333;
    }

    /* ---- Plain letterhead (Certification) ------------------------------ */
    .sheet--plain .header-row { top: 5mm; left: 36mm; right: 36mm; }
    .sheet--plain .left-logos img, .sheet--plain .seal-barangay--round { height: 21mm; }
    .heading--plain .line { font-size: 10.5pt; line-height: 1.45; letter-spacing: .03em; }
    .sheet--plain .banner { top: 36mm; left: 20mm; right: 24mm; height: 6.5mm; font-size: 9.5pt; }
    .sheet--plain .title { top: 50mm; font-size: 19pt; }
    .sheet--plain .body { top: 66mm; }

    /* Personal details table + 2x2 photo (Certification). */
    .body .caps { text-transform: uppercase; font-weight: 700; letter-spacing: .02em; }
    .info { display: flex; justify-content: space-between; align-items: flex-start; margin: 5mm 0 2mm; }
    .info table { border-collapse: collapse; background: none; width: auto; font-size: 11pt; }
    .info td { padding: .9mm 0; border: 0; vertical-align: bottom; background: none; }
    .info td.k { width: 37mm; font-size: 10pt; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; }
    .info td.c { width: 6mm; }
    .info .fill { min-width: 68mm; text-align: left; font-weight: 400; }
    .photo-box {
        width: 45mm; height: 45mm; margin-top: 3mm; flex: none;
        border: 1.3px solid #111; display: flex; align-items: center; justify-content: center;
        font-size: 15pt; letter-spacing: .1em; overflow: hidden; background: #fff;
    }
    .photo-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .sig-line { width: 62mm; margin: 2mm 4mm 5mm auto; text-align: center; font-size: 9.5pt; font-weight: 700; }
    .sig-line::before { content: ""; display: block; height: 9mm; border-bottom: 1px solid #111; margin-bottom: 1.2mm; }

    /* Staff can click underlined values to edit them on screen; the hint
       outline never prints or goes into the PDF. */
    body.can-edit [data-edit] { cursor: text; outline: 1px dashed rgba(201, 162, 124, .9); outline-offset: 1px; }
    body.can-edit [data-edit]:hover, body.can-edit [data-edit]:focus { background: rgba(255, 236, 196, .55); outline-style: solid; }
    body.exporting [data-edit] { outline: none !important; background: none !important; }
    .toolbar .edit-note { width: 100%; text-align: center; font-size: 12px; color: #6B7280; }

    @media print {
        [data-edit] { outline: none !important; background: none !important; }
        body { background: #fff; }
        .toolbar, .hints { display: none !important; }
        .sheet-wrap { padding: 0; }
        .sheet-scale { transform: none !important; width: auto; margin: 0; height: auto !important; }
        .sheet { box-shadow: none; }
    }
</style>
</head>
<body<?= !is_resident() ? ' class="can-edit"' : '' ?>>

<div class="toolbar">
    <span class="meta">Control No. <strong><?= e($c['control_no']) ?></strong></span>
    <a class="btn" href="<?= e($c['back_url']) ?>">&larr; <?= e($c['back_label']) ?></a>
    <button class="btn" type="button" id="print-btn">Print</button>
    <button class="btn btn--primary" type="button" id="pdf-btn">Download PDF</button>
    <?php if (!is_resident()): ?><span class="edit-note">Tip: click any underlined value to correct or fill it in before printing (changes are not saved).</span><?php endif; ?>
</div>

<?php if ($c['hints']): ?>
<div class="hints" role="note">
    <?php foreach ($c['hints'] as $h): ?><p><?= $h ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="sheet-wrap">
    <div class="sheet-scale" id="sheet-scale">
        <div class="sheet<?= $plain ? ' sheet--plain' : '' ?>" id="certificate">

            <?php if (!$plain): ?>
            <div class="band" aria-hidden="true"><div class="band__fill"></div></div>

            <?php if ($c['watermark']): ?>
                <img class="watermark" src="<?= e($c['watermark']) ?>" alt="" aria-hidden="true">
            <?php endif; ?>
            <?php endif; ?>

            <div class="header-row">
                <div class="left-logos">
                    <?php if ($c['municipal_seal']): ?>
                        <img class="seal-municipal" src="<?= e($c['municipal_seal']) ?>" alt="Seal of the Municipality of <?= e(CERT_MUNICIPALITY) ?>">
                    <?php endif; ?>
                    <?php if ($c['bagong_logo'] && !$plain): ?>
                        <img class="bp" src="<?= e($c['bagong_logo']) ?>" alt="Bagong Pilipinas">
                    <?php endif; ?>
                </div>

                <?php if ($plain): ?>
                <div class="heading heading--plain">
                    <span class="line">REPUBLIC OF THE PHILIPPINES</span>
                    <span class="line">Province of <?= e(CERT_PROVINCE) ?></span>
                    <span class="line">Municipality of <?= e(CERT_MUNICIPALITY) ?></span>
                    <span class="line">BARANGAY <?= e(mb_strtoupper($c['barangay'], 'UTF-8')) ?></span>
                </div>
                <?php else: ?>
                <div class="heading">
                    <span class="line">Republic of the Philippines</span>
                    <span class="line">Province of <?= e(CERT_PROVINCE) ?></span>
                    <span class="line">Municipality of <?= e(CERT_MUNICIPALITY) ?></span>
                    <span class="barangay<?= $brgyClass ?>">Barangay <?= e($c['barangay']) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($plain && $c['round_seal']): ?>
                    <img class="seal-barangay seal-barangay--round" src="<?= e($c['round_seal']) ?>" alt="Seal of Barangay <?= e($c['barangay']) ?>">
                <?php elseif ($c['header_seal']): ?>
                    <img class="seal-barangay<?= $c['header_round'] ? ' seal-barangay--round' : '' ?>" src="<?= e($c['header_seal']) ?>" alt="Seal of Barangay <?= e($c['barangay']) ?>"<?= $c['seal_style'] !== '' ? ' style="' . e($c['seal_style']) . '"' : '' ?>>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <div class="banner">OFFICE OF THE PUNONG BARANGAY</div>

            <h1 class="title"><?= $o['title_html'] ?></h1>

            <div class="body<?= !empty($o['compact']) ? ' body--compact' : '' ?>" style="left:<?= $bodyLeft ?>mm;right:<?= $bodyRight ?>mm">
                <?= $o['body_html'] ?>

                <div class="signatory<?= $signatory ?>">
                    <span class="name"><?= e($c['captain_line']) ?></span>
                    <span class="role">Punong Barangay</span>
                </div>

                <div class="control<?= !empty($o['control_grid']) ? ' control--grid' : '' ?>">
                    <?php foreach ($rows as [$label, $valueHtml]): ?>
                        <div><span class="k"><?= e($label) ?>:</span> <?= $valueHtml ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($o['validity_html'])): ?>
                <div class="validity"><?= $o['validity_html'] ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    // Shrink the A4 sheet to fit narrow screens (phones); print is unaffected.
    var scaleBox = document.getElementById('sheet-scale');
    var sheet = document.getElementById('certificate');
    function fit() {
        scaleBox.style.transform = '';
        scaleBox.style.height = '';
        var avail = document.documentElement.clientWidth - 24;
        var s = Math.min(1, avail / sheet.offsetWidth);
        if (s < 1) {
            scaleBox.style.transform = 'scale(' + s + ')';
            scaleBox.style.height = (sheet.offsetHeight * s) + 'px';
            scaleBox.style.marginLeft = '12px';
        } else {
            scaleBox.style.marginLeft = '';
        }
    }
    window.addEventListener('resize', fit);
    fit();

    if (document.body.classList.contains('can-edit')) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-edit]'), function (el) {
            el.setAttribute('contenteditable', 'true');
            el.setAttribute('spellcheck', 'false');
        });
    }

    function whenReady() {
        var fonts = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
        var imgs = Array.prototype.map.call(sheet.querySelectorAll('img'), function (img) {
            return img.complete ? Promise.resolve() : new Promise(function (r) { img.onload = img.onerror = r; });
        });
        return Promise.all([fonts].concat(imgs));
    }

    document.getElementById('print-btn').addEventListener('click', function () {
        whenReady().then(function () { window.print(); });
    });

    var pdfBtn = document.getElementById('pdf-btn');
    pdfBtn.addEventListener('click', function () {
        if (typeof window.html2pdf !== 'function') {
            // Library unavailable (offline): the browser's print dialog can save as PDF.
            window.print();
            return;
        }
        var label = pdfBtn.textContent;
        pdfBtn.disabled = true;
        pdfBtn.textContent = 'Preparing PDF…';
        document.body.classList.add('exporting');
        if (document.activeElement && document.activeElement.blur) { document.activeElement.blur(); }
        // The PDF renderer captures relative to the current scroll position:
        // scrolled down, the page came out shifted and cut off. Capture from
        // the top, then put the user back where they were.
        var scrollX = window.pageXOffset, scrollY = window.pageYOffset;
        window.scrollTo(0, 0);
        whenReady().then(function () {
            return window.html2pdf().set({
                margin: 0,
                filename: <?= json_encode($pdfName) ?>,
                image: { type: 'jpeg', quality: 0.97 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: 'avoid-all' }
            }).from(sheet).toPdf().get('pdf').then(function (pdf) {
                // The sheet is exactly one A4 page; drop any sliver page that
                // pixel rounding may add.
                while (pdf.internal.getNumberOfPages() > 1) {
                    pdf.deletePage(pdf.internal.getNumberOfPages());
                }
            }).save();
        }).catch(function () {
            window.print();
        }).then(function () {
            window.scrollTo(scrollX, scrollY);
            document.body.classList.remove('exporting');
            pdfBtn.disabled = false;
            pdfBtn.textContent = label;
        });
    });
})();
</script>
</body>
</html>
<?php
}
