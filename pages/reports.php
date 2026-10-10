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

/* Secretary-only Purok/Zone column (live distinct zones from
 * residents.address for the selected year). The snapshot table itself is
 * per-barangay, so this just lists which zones exist in that barangay.
 * When a zone is picked, the numbers row switches to live zone-only
 * totals (same residents + IP/4Ps logic as the column). */
$zonesByBrgy = [];
$zoneOptions = [];
$selZone     = '';
$zoneTotals  = null;
$zoneBreakdown = [];
$has_addr_col = false;
try {
    if ($ac = $conn->query("SHOW COLUMNS FROM residents LIKE 'address'")) {
        $has_addr_col = ($ac->num_rows > 0);
        $ac->free();
    }
} catch (Throwable $e) { $has_addr_col = false; }
if ($has_addr_col) {
    try {
        if ($zq = $conn->prepare("SELECT barangay_id, TRIM(address) z FROM residents WHERE deleted_at IS NULL AND YEAR(created_at)=? AND address IS NOT NULL AND TRIM(address) <> '' GROUP BY barangay_id, TRIM(address) ORDER BY TRIM(address) ASC")) {
            $zq->bind_param('i', $selYear);
            $zq->execute();
            $zres = $zq->get_result();
            while ($zw = $zres->fetch_assoc()) { $zonesByBrgy[(int)$zw['barangay_id']][] = (string)$zw['z']; }
            $zq->close();
        }
    } catch (Throwable $e) { $zonesByBrgy = []; }
    /* Zone filter value (secretary only): fixed Zone 1-7 options like the
     * New Resident form. Validated against that fixed list so an unknown
     * ?zone= resets to all zones. When a zone is picked, compute its live
     * totals for the selected year + own barangay (residents, households,
     * students, PWD, seniors, IP, 4Ps, male, female). Documents/Blotters
     * stay from the snapshot (they are barangay-level, with no zone link). */
    if (!$is_admin) {
        $zoneOptions = ['Zone 1','Zone 2','Zone 3','Zone 4','Zone 5','Zone 6','Zone 7'];
        $selZone = trim((string)($_GET['zone'] ?? ''));
        if ($selZone !== '' && !in_array($selZone, $zoneOptions, true)) { $selZone = ''; }
        $secBid = (int)($_SESSION['barangay_id'] ?? 0);
        $ipExpr = ($ip_col !== null) ? "SUM(CASE WHEN `$ip_col` IN ('Yes',1,'1') THEN 1 ELSE 0 END)" : '0';
        $fpExpr = ($fp_col !== null) ? "SUM(CASE WHEN `$fp_col` IN ('Yes',1,'1') THEN 1 ELSE 0 END)" : '0';
        if ($selZone !== '') {
            try {
                $zsql = "SELECT COUNT(*) residents,
                            COUNT(DISTINCT NULLIF(TRIM(household_no), '')) households,
                            SUM(CASE WHEN is_student IN ('Yes',1,'1') THEN 1 ELSE 0 END) students,
                            SUM(CASE WHEN is_pwd IN ('Yes',1,'1') THEN 1 ELSE 0 END) pwd,
                            SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) seniors,
                            $ipExpr ip, $fpExpr fp,
                            SUM(CASE WHEN sex='Male' THEN 1 ELSE 0 END) male,
                            SUM(CASE WHEN sex='Female' THEN 1 ELSE 0 END) female
                         FROM residents
                        WHERE deleted_at IS NULL AND barangay_id=? AND YEAR(created_at)=? AND TRIM(address)=?";
                if ($zt = $conn->prepare($zsql)) {
                    $zt->bind_param('iis', $secBid, $selYear, $selZone);
                    $zt->execute();
                    $zoneTotals = $zt->get_result()->fetch_assoc();
                    $zt->close();
                }
            } catch (Throwable $e) { $zoneTotals = null; }
            if ($zoneTotals !== null) { $zoneBreakdown[$selZone] = $zoneTotals; }
        } else {
            /* All zones: one row per zone with its own live totals. */
            try {
                $bsql = "SELECT TRIM(address) z,
                            COUNT(*) residents,
                            COUNT(DISTINCT NULLIF(TRIM(household_no), '')) households,
                            SUM(CASE WHEN is_student IN ('Yes',1,'1') THEN 1 ELSE 0 END) students,
                            SUM(CASE WHEN is_pwd IN ('Yes',1,'1') THEN 1 ELSE 0 END) pwd,
                            SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) seniors,
                            $ipExpr ip, $fpExpr fp,
                            SUM(CASE WHEN sex='Male' THEN 1 ELSE 0 END) male,
                            SUM(CASE WHEN sex='Female' THEN 1 ELSE 0 END) female
                         FROM residents
                        WHERE deleted_at IS NULL AND barangay_id=? AND YEAR(created_at)=? AND address IS NOT NULL AND TRIM(address) <> ''
                        GROUP BY TRIM(address) ORDER BY TRIM(address) ASC";
                if ($bt = $conn->prepare($bsql)) {
                    $bt->bind_param('ii', $secBid, $selYear);
                    $bt->execute();
                    $bres = $bt->get_result();
                    while ($bw = $bres->fetch_assoc()) { $zoneBreakdown[(string)$bw['z']] = $bw; }
                    $bt->close();
                }
            } catch (Throwable $e) { $zoneBreakdown = []; }
        }
    }
}

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
    if ($selZone !== '') { $scopeLabel .= ' — ' . $selZone; }
}

include BASE_PATH . '/partials/header.php';
?>

<div class="print-only" style="margin-bottom:12px;">
    <p style="margin:0;font-size:16px;font-weight:800;">Sto. Niño Barangay Management System — Yearly Statistics Report</p>
    <p style="margin:3px 0 0;font-size:12.5px;">Year: <?= (int)$selYear ?> &middot; Scope: <?= e($scopeLabel) ?> &middot; Generated: <?= e(date('F j, Y g:i A')) ?></p>
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
        <?php if (!$is_admin && $has_addr_col && $zoneOptions): ?>
        <label class="sr-only" for="r_zone">Purok / Zone</label>
        <select id="r_zone" name="zone">
            <option value="">All Purok / Zones</option>
            <?php foreach ($zoneOptions as $zo): ?>
            <option value="<?= e($zo) ?>"<?= $selZone === $zo ? ' selected' : '' ?>><?= e($zo) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn" type="submit">Filter</button>
        <?php if (!$is_admin && $selZone !== ''): ?>
        <a class="btn btn--ghost" href="<?= e(url('pages/reports.php?year=' . (int)$selYear)) ?>">Clear zone</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('pages/reports.php')) ?>">Reset</a>
    </form>
<span class="muted-meta">Total: <?= number_format(count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?></span>
    <span class="push-right" style="display:inline-flex;gap:8px;align-items:center;">
        <button class="btn" type="button" onclick="window.print()">🖨 View / Print</button>
    </span>
</div>

<div class="card table-wrap">
    <table class="data-table data-table--compact">
        <thead>
            <tr>
                <?php if ($is_admin): ?><th class="is-left">Barangay</th><?php else: ?><th class="is-left">Purok/Zone</th><?php endif; ?>
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
                <?php if (!$is_admin): ?>
                    <?php $secRow = $body[0] ?? null; ?>
                    <?php $secZoneRows = $zoneBreakdown ?: []; ?>
                    <?php if ($secZoneRows): ?>
                        <?php foreach ($secZoneRows as $zname => $zt): ?>
                        <tr>
                            <td class="is-left"><?= e((string)$zname) ?></td>
                            <td><?= number_format((int)($zt['residents'] ?? 0)) ?></td>
                            <td><?= number_format((int)($zt['households'] ?? 0)) ?></td>
                            <td><?= number_format((int)($zt['students'] ?? 0)) ?></td>
                            <td><?= number_format((int)($zt['pwd'] ?? 0)) ?></td>
                            <td><?= number_format((int)($zt['seniors'] ?? 0)) ?></td>
                            <td><?= $ip_col !== null ? number_format((int)($zt['ip'] ?? 0)) : '-' ?></td>
                            <td><?= $fp_col !== null ? number_format((int)($zt['fp'] ?? 0)) : '-' ?></td>
                            <td><?= number_format((int)($zt['male'] ?? 0)) ?></td>
                            <td><?= number_format((int)($zt['female'] ?? 0)) ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['documents']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['blotters']) : '0' ?></td>
                            <td><?= ($secRow && $secRow['captured_at']) ? e(date('M j, Y', strtotime($secRow['captured_at']))) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php $zoneListArr = $secRow ? ($zonesByBrgy[(int)$secRow['barangay_id']] ?? []) : []; if ($selZone !== '') { $zoneListArr = array_values(array_intersect($zoneListArr, [$selZone])); } $zoneList = $has_addr_col ? implode(', ', $zoneListArr) : ''; ?>
                        <tr>
                            <td class="is-left"><?= $selZone !== '' ? e($selZone) : ($zoneList !== '' ? e($zoneList) : '—') ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['residents']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['households']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['students']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['pwd']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['seniors']) : '0' ?></td>
                            <td><?= $ip_col !== null ? number_format((int)($secRow ? ($ipByBrgy[(int)$secRow['barangay_id']] ?? 0) : 0)) : '-' ?></td>
                            <td><?= $fp_col !== null ? number_format((int)($secRow ? ($fpByBrgy[(int)$secRow['barangay_id']] ?? 0) : 0)) : '-' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['male']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['female']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['documents']) : '0' ?></td>
                            <td><?= $secRow ? number_format((int)$secRow['blotters']) : '0' ?></td>
                            <td><?= ($secRow && $secRow['captured_at']) ? e(date('M j, Y', strtotime($secRow['captured_at']))) : '—' ?></td>
                        </tr>
                    <?php endif; ?>
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
                    <td><a class="btn" href="<?= e(url('pages/report_print.php?year=' . (int)$r['stat_year'] . '&barangay=' . (int)$r['barangay_id'])) ?>">🖨 View / Print</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
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