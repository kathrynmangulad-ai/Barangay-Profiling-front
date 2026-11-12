<?php
/**
 * KP Form No. 7 - Complaint (Office of the Lupong Tagapamayapa), filled from a
 * blotter record:  documents/kp_form_7.php?id=<blotter id>
 *
 *   Complainant/s        reporting person
 *   Respondent/s         suspect data
 *   Barangay Case No.    blotter number
 *   For                  incident type
 *   complaint text       narrative
 *   relief prayed for    relief sought
 *   Made this ...        date the report was made
 *
 * Nature of Case and the "received and filed" date are
 * not stored on a blotter record, so they print as blank lines. Every value
 * can be clicked on screen and corrected before printing (not saved).
 *
 * Staff see their own barangay's records (admin: all); a resident sees the
 * reports they filed. Logos come from the barangay's Document Logos.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/logos.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid blotter record.'); }

$has_captain = false;
if ($cc = $conn->query("SHOW COLUMNS FROM barangays LIKE 'punong_barangay'")) { $has_captain = $cc->num_rows > 0; $cc->free(); }
$s = $conn->prepare(
    'SELECT br.*, b.barangay_name, ' . ($has_captain ? 'b.punong_barangay' : 'NULL AS punong_barangay') . '
       FROM blotter_records br
       LEFT JOIN barangays b ON b.id = br.barangay_id
      WHERE br.id = ? LIMIT 1'
);
$s->bind_param('i', $id);
$s->execute();
$r = $s->get_result()->fetch_assoc();
$s->close();
if (!$r) { die('Blotter record not found.'); }

require_record_access($r['barangay_id'], $r['resident_id'] !== null ? (int)$r['resident_id'] : null, 'blotter_record', $id);

$brgyRaw  = trim((string)($r['barangay_name'] ?? ''));
$brgyId   = (int)$r['barangay_id'];
$slug     = logo_slug($brgyRaw);
$captain  = trim(preg_replace('/^hon\.?\s+/i', '', trim((string)($r['punong_barangay'] ?? ''))));
$left     = brgy_logo($brgyId, $slug, 'municipal');
$right    = brgy_logo($brgyId, $slug, 'watermark') ?: brgy_logo($brgyId, $slug, 'seal');

$oneLine  = function ($v) { return trim(preg_replace('/\s+/', ' ', (string)($v ?? ''))); };
$complainant = $oneLine($r['reporting_person']);
$respondent  = $oneLine($r['suspect_data']);
$caseNo      = $oneLine($r['blotter_no']);
$for         = $oneLine($r['incident_type']);
$narrative   = trim((string)($r['narrative'] ?? ''));
$relief      = trim((string)($r['relief_sought'] ?? ''));

$made     = !empty($r['report_datetime']) ? strtotime((string)$r['report_datetime']) : (!empty($r['created_at']) ? strtotime((string)$r['created_at']) : false);
$ordinal  = function (int $n) { $s = 'th'; if (!in_array($n % 100, [11, 12, 13], true)) { $s = [1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'; } return $n . $s; };
$madeDay  = $made ? $ordinal((int)date('j', $made)) : '';
$madeMon  = $made ? date('F', $made) : '';
$year     = $made ? date('Y', $made) : date('Y');

$back_url   = is_resident() ? url('resident/blotter_request.php') : url('pages/blotters.php');
$back_label = is_resident() ? 'My Blotter Reports' : 'Blotter Records';

/** A value on a ruled line; click to edit on screen. */
$fill = function (string $text, string $cls = '') { return '<span class="fill ' . e($cls) . '" contenteditable="true" spellcheck="false">' . e($text) . '</span>'; };
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KP Form No. 7 - Complaint - <?= e($caseNo) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fondamento:ital@0;1&family=Uncial+Antiqua&display=swap" rel="stylesheet">
<style>
    :root {
        --script: "Fondamento", "Lucida Calligraphy", "Apple Chancery", Georgia, serif;
        --uncial: "Uncial Antiqua", "Lucida Calligraphy", Georgia, serif;
        --orange: #ED7D31;
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: #ECEFF3; color: #111; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }

    .controls {
        position: sticky; top: 0; z-index: 10;
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px;
        padding: 10px 16px; background: #fff; border-bottom: 1px solid #D9DEE6;
    }
    .controls__meta { font-size: 14px; color: #475569; }
    .controls__meta strong { color: #111; }
    .controls__btns { display: flex; gap: 8px; }
    .controls a, .controls button {
        display: inline-flex; align-items: center; height: 36px; padding: 0 14px;
        border: 1px solid #B8C2D0; border-radius: 6px; background: #fff; color: #111;
        font: 500 14px/1 inherit; text-decoration: none; cursor: pointer;
    }
    .controls button { background: #1E3A5F; border-color: #1E3A5F; color: #fff; }
    .tip { margin: 0; padding: 8px 16px; text-align: center; font-size: 12.5px; color: #64748B; background: #fff; border-bottom: 1px solid #E5E9EF; }

    .sheet {
        position: relative; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm 16mm 14mm;
        background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .12);
        font-family: var(--script); font-size: 12pt; line-height: 1.5;
    }
    .form-no { margin: 0 0 3mm 10mm; font-size: 10pt; }

    .head { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; column-gap: 6mm; }
    .head img { display: block; height: 22mm; width: auto; object-fit: contain; }
    .head__left { justify-self: end; }
    .head__right { justify-self: start; }
    .head__text { text-align: center; line-height: 1.45; }
    .head__text span { display: block; font-size: 11pt; }
    .head__text .caps { font-family: var(--uncial); font-size: 10.5pt; letter-spacing: .02em; }
    .bar { height: 5mm; margin: 0 2mm; border-radius: 1mm; background: var(--orange); box-shadow: 0 .6mm .8mm rgba(0, 0, 0, .2); }
    .office { margin: 1.5mm 0 0; text-align: center; font-family: var(--uncial); font-size: 10.5pt; letter-spacing: .02em; }

    .fill { display: inline-block; min-width: 20mm; padding: 0 1.5mm; border-bottom: .3mm solid #111; outline: none; text-align: center; vertical-align: baseline; }
    .fill:focus { background: #FFF7D6; }
    .fill:empty::before { content: "\00a0"; }

    .parties { display: grid; grid-template-columns: 1fr 1fr; column-gap: 16mm; margin-top: 12mm; }
    .party { text-align: center; }
    .party .fill { display: block; width: 72mm; min-height: 7mm; }
    .party__label { display: block; width: 72mm; margin-top: 1mm; }
    .against { width: 72mm; margin: 5mm 0 7mm; text-align: center; }
    .case { padding-top: 2mm; }
    .case p { margin: 0 0 1.5mm; display: flex; align-items: baseline; gap: 2mm; white-space: nowrap; }
    .case .fill { flex: 1; text-align: left; }

    h1 { margin: 6mm 0 6mm; text-align: center; font-family: var(--uncial); font-weight: 400; font-size: 15pt; letter-spacing: .25em; }
    .lead { margin: 0 0 4mm; }

    /* Ruled writing area: the text sits on printed lines, 8mm apart. */
    .ruled {
        display: block; width: 100%; outline: none; white-space: pre-wrap; overflow-wrap: anywhere;
        line-height: 8mm; padding: 0 1mm;
        background-image: linear-gradient(to bottom, transparent calc(8mm - .3mm), #111 calc(8mm - .3mm));
        background-size: 100% 8mm;
    }
    .ruled:focus { background-color: #FFFBE8; }
    .ruled--10 { min-height: 80mm; }
    .ruled--3 { min-height: 24mm; }

    .therefore { margin: 5mm 0 1mm; font-size: 11pt; }
    .therefore b { font-family: system-ui, Arial, sans-serif; font-size: 11.5pt; }
    .dated { margin: 6mm 0 0; font-size: 11pt; }
    .dated .fill { min-width: 26mm; }
    .sign { margin: 4mm 0 0 auto; width: 62mm; text-align: center; font-size: 10.5pt; }
    .sign .fill { display: block; width: 100%; min-height: 7mm; }
    .chair { margin: 9mm 0 0 auto; width: 80mm; text-align: center; }
    .chair__name { display: block; font-family: var(--uncial); font-size: 11pt; text-decoration: underline; text-underline-offset: 1mm; }
    .chair__role { display: block; font-size: 10.5pt; }

    @page { size: A4; margin: 0; }
    @media print {
        body { background: #fff; }
        .controls, .tip { display: none !important; }
        .sheet { margin: 0; box-shadow: none; }
        .fill:focus, .ruled:focus { background-color: transparent; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @media screen and (max-width: 820px) { .sheet { transform-origin: top left; zoom: .5; } }
</style>
</head>
<body>
<div class="controls">
    <span class="controls__meta">KP Form No. 7 &middot; Blotter No. <strong><?= e($caseNo) ?></strong></span>
    <span class="controls__btns">
        <a href="<?= e($back_url) ?>">&larr; <?= e($back_label) ?></a>
        <button type="button" onclick="window.print()">Print</button>
    </span>
</div>
<p class="tip">Tip: click any line to correct or fill it in before printing (changes are not saved).</p>

<div class="sheet">
    <p class="form-no">KP Form No. 7</p>

    <div class="head">
        <span class="head__left"><?php if ($left): ?><img src="<?= e($left['url']) ?>" alt="Municipal seal"><?php endif; ?></span>
        <span class="head__text">
            <span class="caps">REPUBLIC OF THE PHILIPPINES</span>
            <span>Province of Cagayan</span>
            <span>Municipality of Sto. Niño</span>
            <span class="caps">BARANGAY <?= e(mb_strtoupper($brgyRaw, 'UTF-8')) ?></span>
        </span>
        <span class="head__right"><?php if ($right): ?><img src="<?= e($right['url']) ?>" alt="Barangay seal"><?php endif; ?></span>
    </div>
    <div class="bar"></div>
    <p class="office">OFFICE OF THE LUPONG TAGAPAMAYAPA</p>

    <div class="parties">
        <div>
            <div class="party"><?= $fill($complainant) ?><span class="party__label">Complainant/s</span></div>
            <p class="against">-Against-</p>
            <div class="party"><?= $fill($respondent) ?><span class="party__label">Respondent/s</span></div>
        </div>
        <div class="case">
            <p>Barangay Case No: <?= $fill($caseNo) ?></p>
            <p>For: <?= $fill($for) ?></p>
            <p>Nature of Case: <?= $fill('') ?></p>
        </div>
    </div>

    <h1>COMPLAINT</h1>

    <p class="lead">I/We hereby complain against above named respondent/s for violating my/our rights and interest in the following manner:</p>
    <div class="ruled ruled--10" contenteditable="true" spellcheck="false"><?= e($narrative) ?></div>

    <p class="therefore"><b>THEREFORE,</b> I/We pray that the following relief/s be granted to me/us in accordance with law/ or equity.</p>
    <div class="ruled ruled--3" contenteditable="true" spellcheck="false"><?= e($relief) ?></div>

    <p class="dated">Made this <?= $fill($madeDay) ?> day of <?= $fill($madeMon) ?>, <?= e($year) ?>.</p>
    <div class="sign"><?= $fill($complainant) ?>Complainant/s</div>

    <p class="dated">Received and filed this <?= $fill('') ?> day of <?= $fill('') ?>, <?= e($year) ?>.</p>

    <div class="chair">
        <span class="chair__name"><?= $captain !== '' ? 'HON. ' . e(mb_strtoupper($captain, 'UTF-8')) : '&nbsp;' ?></span>
        <span class="chair__role">Punong Barangay/Lupon Chairman</span>
    </div>
</div>
</body>
</html>
