<?php
/**
 * Yearly Reports.
 *
 * Lists the frozen per-year, per-barangay snapshots stored in
 * barangay_yearly_stats (written by admin/yearly_capture.php). Admins see every
 * barangay plus the barangay_id = 0 "all barangays" aggregate row; secretaries
 * only see the rows for their own barangay (same scoping as the dashboard).
 * Rows are filtered by the year picked in the toolbar (admins may also pick
 * a barangay), defaulting to the newest captured year. Admins get a
 * per-barangay "View / Print" action that opens pages/report_print.php.
 */
require_once __DIR__ . '/../config/config.php';
require_staff();

$page_title = 'Yearly Reports';
$is_admin   = (($_SESSION['role'] ?? '') === 'admin');

/* Years that have at least one captured snapshot, newest first. */
$years = [];
try {
    if ($yq = $conn->query('SELECT DISTINCT stat_year FROM barangay_yearly_stats ORDER BY stat_year DESC')) {
        while ($yr = $yq->fetch_assoc()) { $years[] = (int)$yr['stat_year']; }
        $yq->free();
    }
} catch (Throwable $e) {
    $years = []; // Table not created yet (see database/update.sql).
}

/* Selected year: a valid ?year= from the dropdown, else the newest captured
 * year, else the current year when nothing has been captured yet. */
$selYear = (int)($_GET['year'] ?? 0);
if (!in_array($selYear, $years, true)) {
    $selYear = $years[0] ?? (int)date('Y');
}

/* Barangay filter — admin only. Secretaries stay locked to their assigned
 * barangay (applied in the query below), so the picker is meaningless for
 * them and is not shown. */
$brgyOptions = [];
$selBrgy = 0;
if ($is_admin) {
    if ($bq = $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name ASC')) {
        while ($br = $bq->fetch_assoc()) { $brgyOptions[(int)$br['id']] = (string)$br['barangay_name']; }
        $bq->free();
    }
    $selBrgy = (int)($_GET['barangay'] ?? 0);
    if ($selBrgy !== 0 && !isset($brgyOptions[$selBrgy])) { $selBrgy = 0; }
}

/* IP / 4Ps figures: barangay_yearly_stats has no such columns, so they are
 * computed live from residents for the selected year (same logic as the
 * dashboard). Detect whichever column name exists — when neither does, the
 * cells fall back to a dash instead of breaking the page. */
$ip_col=null; $fp_col=null;
foreach(['is_ip','is_indigenous','indigenous'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $ip_col=$cand; $qc->free(); break; } $qc->free(); } }
foreach(['is_4ps','is_fourps','fourps','pantawid'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $fp_col=$cand; $qc->free(); break; } $qc->free(); } }

$ipByBrgy = []; $fpByBrgy = [];
try {
    if ($ip_col !== null) {
        if ($iq = $conn->prepare("SELECT barangay_id, COUNT(*) c FROM residents WHERE deleted_at IS NULL AND YEAR(created_at)=? AND `$ip_col` IN ('Yes',1,'1') GROUP BY barangay_id")) {
            $iq->bind_param('i', $selYear);
            $iq->execute();
            $ir = $iq->get_result();
            while ($iw = $ir->fetch_assoc()) { $ipByBrgy[(int)$iw['barangay_id']] = (int)$iw['c']; }
            $iq->close();
        }
    }
    if ($fp_col !== null) {
        if ($fq = $conn->prepare("SELECT barangay_id, COUNT(*) c FROM residents WHERE deleted_at IS NULL AND YEAR(created_at)=? AND `$fp_col` IN ('Yes',1,'1') GROUP BY barangay_id")) {
            $fq->bind_param('i', $selYear);
            $fq->execute();
            $fr = $fq->get_result();
            while ($fw = $fr->fetch_assoc()) { $fpByBrgy[(int)$fw['barangay_id']] = (int)$fw['c']; }
            $fq->close();
        }
    }
} catch (Throwable $e) {
    $ipByBrgy = []; $fpByBrgy = [];
}
// "All Barangays" totals for the footer row.
$ipTotal = array_sum($ipByBrgy);
$fpTotal = array_sum($fpByBrgy);

/* Snapshot rows for the selected year. */
$rows  = [];
$sql   = 'SELECT s.*, b.barangay_name
            FROM barangay_yearly_stats s
            LEFT JOIN barangays b ON b.id = s.barangay_id
           WHERE s.stat_year = ?';
$types = 'i';
$params = [$selYear];
if (!$is_admin) {
    $sql   .= ' AND s.barangay_id = ?';
    $types .= 'i';
    $params[] = (int)($_SESSION['barangay_id'] ?? 0);
} elseif ($selBrgy > 0) {
    // Admin barangay filter: only that barangay's row (no aggregate row).
    $sql   .= ' AND s.barangay_id = ?';
    $types .= 'i';
    $params[] = $selBrgy;
}
// The barangay_id = 0 aggregate sorts last so it reads like a total row.
$sql .= ' ORDER BY (s.barangay_id = 0) ASC, b.barangay_name ASC';
try {
    if ($s = $conn->prepare($sql)) {
        $s->bind_param($types, ...$params);
        $s->execute();
        $rs = $s->get_result();
        while ($row = $rs->fetch_assoc()) { $rows[] = $row; }
        $s->close();
    }
} catch (Throwable $e) {
    $rows = [];
}

/* The admin-only aggregate row is rendered as a tfoot total, like the
 * dashboard's per-barangay breakdown. */
$aggRow = null;
$body   = [];
foreach ($rows as $r) {
    if ((int)$r['barangay_id'] === 0) { $aggRow = $r; } else { $body[] = $r; }
}

/* Label shown on the printed softcopy (all barangays for admin, the assigned
 * barangay for a secretary). */
$scopeLabel = 'All Barangays';
if ($is_admin) {
    if ($selBrgy > 0) { $scopeLabel = $brgyOptions[$selBrgy] ?? ('Barangay #' . $selBrgy); }
} else {
    $scopeLabel = $body[0]['barangay_name'] ?? 'My Barangay';
}

include BASE_PATH . '/partials/header.php';
?>

<div class="print-only" style="margin-bottom:12px;">
    <p style="margin:0;font-size:16px;font-weight:800;">Sto. Niño Barangay Management System — Yearly Statistics Report</p>
    <p style="margin:3px 0 0;font-size:12.5px;">Year: <?= (int)$selYear ?> &middot; Scope: <?= e($scopeLabel) ?> &middot; Generated: <?= e(date('F j, Y g:i A')) ?></p>
</div>

<div class="section-head">
    <div>
        <h2>Yearly Statistics</h2>
        <p>Frozen figures captured for <strong><?= (int)$selYear ?></strong> · per-barangay snapshots</p>
    </div>
    <span class="muted-meta"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></span>
</div>

<div class="toolbar">
    <form method="get" action="<?= e(url('pages/reports.php')) ?>">
        <?php if ($is_admin): ?>
        <label class="sr-only" for="r_brgy">Barangay</label>
        <select id="r_brgy" name="barangay">
            <option value="0">All Barangays</option>
            <?php foreach ($brgyOptions as $boid => $bname): ?>
            <option value="<?= (int)$boid ?>"<?= $selBrgy === (int)$boid ? ' selected' : '' ?>><?= e($bname) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label class="sr-only" for="r_year">Year</label>
        <select id="r_year" name="year">
            <?php if (!in_array($selYear, $years, true)): ?>
            <option value="<?= (int)$selYear ?>" selected><?= (int)$selYear ?></option>
            <?php endif; ?>
            <?php foreach ($years as $y): ?>
            <option value="<?= (int)$y ?>"<?= $y === $selYear ? ' selected' : '' ?>><?= (int)$y ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filter</button>
        <?php if ($is_admin): ?>
        <button class="btn" type="submit">Search</button>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('pages/reports.php')) ?>">Reset</a>
    </form>

    <span class="push-right" style="display:inline-flex;gap:8px;align-items:center;">
        <?php if ($is_admin): ?>
        <a class="btn" href="<?= e(url('admin/yearly_capture.php?year=' . (int)$selYear . '&token=' . csrf_token())) ?>"
           data-confirm="Re-capture the <?= (int)$selYear ?> snapshot for every barangay? Re-running overwrites this year's figures."
           data-confirm-title="Capture yearly snapshot">Capture / refresh <?= (int)$selYear ?></a>
        <?php endif; ?>
        <button class="btn" type="button" onclick="window.print()">🖨 View / Print</button>
    </span>
</div>

<div class="card table-wrap">
    <table class="data-table data-table--compact">
        <thead>
            <tr>
                <th class="is-left">Barangay</th>
                <th>Residents</th>
                <th>Households</th>
                <th>Students</th>
                <th>PWD</th>
                <th>Seniors</th>
                <th>IP</th>
                <th>4Ps</th>
                <th>Male</th>
                <th>Female</th>
                <th>Documents</th>
                <th>Blotters</th>
                <th>Captured</th>
                <?php if ($is_admin): ?><th>Action</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
            <tr>
                <td colspan="<?= $is_admin ? 14 : 13 ?>">
                    <div class="empty">
                        <span class="empty__ico" aria-hidden="true"><?= icon('inbox') ?></span>
                        <p class="empty__title">No snapshot captured for <?= (int)$selYear ?></p>
                        <p class="empty__text"><?php if ($is_admin): ?>Use the &ldquo;Capture / refresh <?= (int)$selYear ?>&rdquo; button above to freeze this year's figures into barangay_yearly_stats.<?php else: ?>Ask an administrator to capture the <?= (int)$selYear ?> yearly snapshot.<?php endif; ?></p>
                    </div>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($body as $r): ?>
                <tr>
                    <td class="is-left"><?= e($r['barangay_name'] ?? ('Barangay #' . (int)$r['barangay_id'])) ?></td>
                    <td><?= number_format((int)$r['residents']) ?></td>
                    <td><?= number_format((int)$r['households']) ?></td>
                    <td><?= number_format((int)$r['students']) ?></td>
                    <td><?= number_format((int)$r['pwd']) ?></td>
                    <td><?= number_format((int)$r['seniors']) ?></td>
                    <td><?= $ip_col !== null ? number_format((int)($ipByBrgy[(int)$r['barangay_id']] ?? 0)) : '-' ?></td>
                    <td><?= $fp_col !== null ? number_format((int)($fpByBrgy[(int)$r['barangay_id']] ?? 0)) : '-' ?></td>
                    <td><?= number_format((int)$r['male']) ?></td>
                    <td><?= number_format((int)$r['female']) ?></td>
                    <td><?= number_format((int)$r['documents']) ?></td>
                    <td><?= number_format((int)$r['blotters']) ?></td>
                    <td><?= $r['captured_at'] ? e(date('M j, Y', strtotime($r['captured_at']))) : '—' ?></td>
                    <?php if ($is_admin): ?>
                    <td><a class="btn" href="<?= e(url('pages/report_print.php?year=' . (int)$r['stat_year'] . '&barangay=' . (int)$r['barangay_id'])) ?>">🖨 View / Print</a></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if ($aggRow): ?>
        <tfoot>
            <tr class="data-table__total">
                <td class="is-left">All Barangays</td>
                <td><?= number_format((int)$aggRow['residents']) ?></td>
                <td><?= number_format((int)$aggRow['households']) ?></td>
                <td><?= number_format((int)$aggRow['students']) ?></td>
                <td><?= number_format((int)$aggRow['pwd']) ?></td>
                <td><?= number_format((int)$aggRow['seniors']) ?></td>
                <td><?= $ip_col !== null ? number_format($ipTotal) : '-' ?></td>
                <td><?= $fp_col !== null ? number_format($fpTotal) : '-' ?></td>
                <td><?= number_format((int)$aggRow['male']) ?></td>
                <td><?= number_format((int)$aggRow['female']) ?></td>
                <td><?= number_format((int)$aggRow['documents']) ?></td>
                <td><?= number_format((int)$aggRow['blotters']) ?></td>
                <td><?= $aggRow['captured_at'] ? e(date('M j, Y', strtotime($aggRow['captured_at']))) : '—' ?></td>
                <?php if ($is_admin): ?><td></td><?php endif; ?>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<?php include BASE_PATH . '/partials/footer.php';