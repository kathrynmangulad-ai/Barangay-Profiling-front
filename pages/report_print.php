<?php
/**
 * Print view for ONE barangay's yearly snapshot (softcopy).
 *
 * Admin-only; opened from the per-row "View / Print" action on
 * pages/reports.php. Renders a standalone page (no sidebar/topbar) with just
 * that barangay's frozen figures from barangay_yearly_stats, then opens the
 * browser print dialog so the report can be saved as a PDF — the same
 * pattern used by pages/document_print.php.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$year = (int)($_GET['year'] ?? 0);
$bid  = (int)($_GET['barangay'] ?? 0);
if ($year < 2000 || $year > (int)date('Y')) {
    die('Invalid year to print.');
}
if ($bid <= 0) {
    die('Invalid barangay to print.');
}

/* The single frozen row for this year + barangay. */
$stat = null;
$name = 'Barangay #' . $bid;
if ($st = $conn->prepare(
    'SELECT s.*, b.barangay_name
       FROM barangay_yearly_stats s
       LEFT JOIN barangays b ON b.id = s.barangay_id
      WHERE s.stat_year = ? AND s.barangay_id = ?
      LIMIT 1'
)) {
    $st->bind_param('ii', $year, $bid);
    $st->execute();
    $stat = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
}
if (!$stat) {
    die('No snapshot found for this barangay and year.');
}
$name = $stat['barangay_name'] ?? $name;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Yearly Report · <?= e($name) ?> · <?= (int)$year ?></title>
<style>
    body { font-family: 'Inter', Arial, Helvetica, sans-serif; color: #0F172A; background: #fff; margin: 0; padding: 24px; }
    .sheet { max-width: 1020px; margin: 0 auto; }
    .rep-head { text-align: center; border-bottom: 3px solid #075B6B; padding-bottom: 12px; margin-bottom: 14px; }
    .rep-head h1 { margin: 0; font-size: 19px; letter-spacing: .02em; }
    .rep-head h2 { margin: 5px 0 0; font-size: 15px; font-weight: 700; color: #075B6B; }
    .rep-meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; font-size: 12.5px; color: #475569; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border: 1px solid #D5DCE6; padding: 9px 11px; text-align: right; white-space: nowrap; }
    th { background: #EAF2F4; color: #075B6B; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    th:first-child, td:first-child { text-align: left; font-weight: 700; }
    tbody tr:nth-child(even) { background: #FBFCFE; }
    .controls { text-align: center; margin: 20px 0 8px; }
    .controls a, .controls button {
        display: inline-block; margin: 0 6px; padding: 10px 20px; font-size: 13px;
        border: 1px solid #D5DCE6; border-radius: 8px; background: #fff; color: #0F172A;
        text-decoration: none; cursor: pointer;
    }
    .controls button { background: #075B6B; border-color: #075B6B; color: #fff; }
    @media print {
        .controls { display: none; }
        body { padding: 0; }
        th, td { border-color: #94A3B8; }
    }
</style>
</head>
<body>
<div class="sheet">
    <div class="rep-head">
        <h1>Sto. Niño Barangay Management System</h1>
        <h2>Yearly Statistics Report — <?= e($name) ?></h2>
    </div>

    <div class="rep-meta">
        <span>Year: <strong><?= (int)$year ?></strong></span>
        <span>Barangay: <strong><?= e($name) ?></strong></span>
        <span>Captured: <strong><?= $stat['captured_at'] ? e(date('M j, Y', strtotime($stat['captured_at']))) : '—' ?></strong></span>
        <span>Generated: <strong><?= e(date('F j, Y g:i A')) ?></strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Barangay</th>
                <th>Residents</th>
                <th>Households</th>
                <th>Students</th>
                <th>PWD</th>
                <th>Seniors</th>
                <th>Male</th>
                <th>Female</th>
                <th>Documents</th>
                <th>Blotters</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= e($name) ?></td>
                <td><?= number_format((int)$stat['residents']) ?></td>
                <td><?= number_format((int)$stat['households']) ?></td>
                <td><?= number_format((int)$stat['students']) ?></td>
                <td><?= number_format((int)$stat['pwd']) ?></td>
                <td><?= number_format((int)$stat['seniors']) ?></td>
                <td><?= number_format((int)$stat['male']) ?></td>
                <td><?= number_format((int)$stat['female']) ?></td>
                <td><?= number_format((int)$stat['documents']) ?></td>
                <td><?= number_format((int)$stat['blotters']) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="controls">
        <a href="<?= e(url('pages/reports.php?year=' . $year . '&barangay=' . $bid)) ?>">&larr; Back to Reports</a>
        <button type="button" onclick="window.print()">🖨 View / Print</button>
    </div>
</div>

<script>
    // Same pattern as pages/document_print.php: open the print dialog (Save
    // as PDF = softcopy) shortly after the view loads.
    setTimeout(function () { window.print(); }, 500);
</script>
</body>
</html>