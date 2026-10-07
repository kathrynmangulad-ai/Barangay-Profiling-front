<?php
require_once __DIR__ . '/../config/config.php';
require_staff();
$page_title = 'Dashboard';
$page_crumb = 'Overview';

/* ---------------------------------------------------------------------------
 * Dashboard filters (Option C): a Year filter and an admin-only Barangay
 * filter. Everything on the dashboard is scoped to the selected year by the
 * record's own date column (residents.created_at, document_requests.requested_at,
 * blotter incident/report date). Note: for residents this means "registered
 * in that year", not a historical population snapshot.
 *
 * Scope is expressed as reusable fragments that each query appends:
 *   - $scopeBrgy : " AND <col> = ?"      barangay restriction (always for a
 *                                        secretary; for an admin only when a
 *                                        specific barangay is picked)
 *   - year is applied per query via year_cond('<dateColumn>') because each
 *     table stores its date in a different column.
 * ------------------------------------------------------------------------- */

$isAdmin = ($_SESSION['role'] === 'admin');

// --- Year filter -----------------------------------------------------------
$currentYear = (int)date('Y');
// Build the selectable range from the oldest record year up to the current
// year, always including the current year even if there is no data yet.
$minYear = $currentYear;
if ($yq = $conn->query("SELECT MIN(y) m FROM (
        SELECT YEAR(created_at) y FROM residents WHERE deleted_at IS NULL
        UNION ALL SELECT YEAR(requested_at) FROM document_requests
        UNION ALL SELECT YEAR(COALESCE(incident_datetime, report_datetime, created_at)) FROM blotter_records
    ) t WHERE y IS NOT NULL")) {
    $mrow = $yq->fetch_assoc();
    if ($mrow && $mrow['m'] !== null) { $minYear = min($currentYear, (int)$mrow['m']); }
    $yq->close();
}
// Years that already have a captured snapshot should be selectable too, even
// if no live records exist for them.
$capturedYears = [];
if ($cyq = $conn->query('SELECT DISTINCT stat_year FROM barangay_yearly_stats')) {
    while ($cyr = $cyq->fetch_assoc()) { $capturedYears[(int)$cyr['stat_year']] = true; }
    $cyq->close();
}

$yearSet = [];
for ($y = $currentYear; $y >= $minYear; $y--) { $yearSet[$y] = true; }
foreach (array_keys($capturedYears) as $cy) { $yearSet[$cy] = true; }
$yearOptions = array_keys($yearSet);
rsort($yearOptions); // newest first

$selYear = (int)($_GET['year'] ?? $currentYear);
if (!in_array($selYear, $yearOptions, true)) { $selYear = $currentYear; }

// --- Barangay filter -------------------------------------------------------
// Secretaries are always locked to their own barangay. Admins may pick one, or
// leave it on "All Barangays" (0).
if ($isAdmin) {
    $selBrgy = (int)($_GET['barangay'] ?? 0);
} else {
    $selBrgy = (int)($_SESSION['barangay_id'] ?? 0);
}

// A barangay restriction applies for any secretary, or for an admin who picked
// a specific barangay. $scopeOn drives the " AND barangay_id = ?" fragments
// that the queries below append (kept as $scopeN for minimal churn).
$scoped = (!$isAdmin) || ($selBrgy > 0);
$bid    = $selBrgy;
$scopeN = $scoped ? ' AND barangay_id=?' : '';

// Resolve the barangay name for the active-scope label, and the admin picker.
$brgyName = $scoped ? 'Selected barangay' : 'All Barangays';
if ($scoped && $bid > 0) {
    if ($bn = $conn->prepare('SELECT barangay_name FROM barangays WHERE id=? LIMIT 1')) {
        $bn->bind_param('i', $bid);
        $bn->execute();
        $bnr = $bn->get_result()->fetch_assoc();
        $bn->close();
        if ($bnr) { $brgyName = $bnr['barangay_name']; }
    }
}
$barangayList = $isAdmin ? $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name ASC') : null;

/* ---------------------------------------------------------------------------
 * Yearly snapshot (Option B).
 * For a PAST year we prefer a frozen snapshot (barangay_yearly_stats) over the
 * live recompute, because the live numbers would drift as rows are later
 * edited or deleted. The current year is always shown live (it isn't final
 * yet). $snapRow holds the snapshot for the active (year, barangay) scope, if
 * one was captured; barangay_id = 0 is the "all barangays" aggregate row.
 * ------------------------------------------------------------------------- */
$snapRow = null;
$isPastYear = ($selYear < $currentYear);
$snapScopeBid = $scoped ? $bid : 0; // 0 = all-barangays aggregate row
if ($isPastYear) {
    if ($sq = $conn->prepare('SELECT * FROM barangay_yearly_stats WHERE stat_year=? AND barangay_id=? LIMIT 1')) {
        $sq->bind_param('ii', $selYear, $snapScopeBid);
        $sq->execute();
        $snapRow = $sq->get_result()->fetch_assoc() ?: null;
        $sq->close();
    }
}
$usingSnapshot = ($snapRow !== null);

/**
 * Build a "YEAR(col) = ?" fragment for the selected year.
 * Returns ['sql' => ' AND YEAR(col) = ?', 'type' => 'i', 'val' => $selYear].
 */
function year_cond($col) {
    global $selYear;
    return ' AND YEAR(' . $col . ') = ?';
}

// Convenience: assemble bound params in the right order for a query that uses
// (year, [barangay]) scoping.
function scope_params($selYear, $scoped, $bid) {
    return $scoped ? [$selYear, $bid] : [$selYear];
}
function scope_types($scoped) {
    return $scoped ? 'ii' : 'i';
}

function count_table($conn, $table, $where, $types, $params) {
    $sql = "SELECT COUNT(*) c FROM " . $table . $where;
    $s = $conn->prepare($sql);
    if ($types) { $s->bind_param($types, ...$params); }
    $s->execute();
    return (int)$s->get_result()->fetch_assoc()['c'];
}

// Date column used for the year filter on each table.
$resYear = year_cond('created_at');   // residents
$docYear = year_cond('requested_at'); // document_requests
$blotYearExpr = 'COALESCE(incident_datetime, report_datetime, created_at)';
$blotYear = year_cond($blotYearExpr); // blotter_records

 
function run_count($conn, $sql, $types = '', $params = []) {
    $s = $conn->prepare($sql);
    if ($types !== '') { $s->bind_param($types, ...$params); }
    $s->execute();
    return (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
}

$yParams = scope_params($selYear, $scoped, $bid);
$yTypes  = scope_types($scoped);

// Headline KPIs, scoped to the selected year (and barangay).
$residents = run_count($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL$resYear$scopeN", $yTypes, $yParams);
$docs      = run_count($conn, "SELECT COUNT(*) c FROM document_requests WHERE 1$docYear$scopeN", $yTypes, $yParams);
$blotters  = run_count($conn, "SELECT COUNT(*) c FROM blotter_records WHERE 1$blotYear$scopeN", $yTypes, $yParams);

$total_households = run_count($conn, "SELECT COUNT(DISTINCT household_no) c FROM residents WHERE deleted_at IS NULL AND household_no IS NOT NULL AND household_no <> ''$resYear$scopeN", $yTypes, $yParams);
$total_pwd        = run_count($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND is_pwd IN ('Yes',1,'1')$resYear$scopeN", $yTypes, $yParams);
$total_students   = run_count($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND is_student IN ('Yes',1,'1')$resYear$scopeN", $yTypes, $yParams);

// "This month" tiles stay relative to today, but only make sense when the
// selected year is the current year; otherwise show the whole selected year.
$monthStart = date('Y-m-01 00:00:00');
if ($selYear === $currentYear) {
    $resMonth  = run_count($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ?" . $scopeN, $scoped ? 'si' : 's', $scoped ? [$monthStart, $bid] : [$monthStart]);
    $docMonth  = run_count($conn, "SELECT COUNT(*) c FROM document_requests WHERE requested_at >= ?" . $scopeN, $scoped ? 'si' : 's', $scoped ? [$monthStart, $bid] : [$monthStart]);
    $blotMonth = run_count($conn, "SELECT COUNT(*) c FROM blotter_records WHERE created_at >= ?" . $scopeN, $scoped ? 'si' : 's', $scoped ? [$monthStart, $bid] : [$monthStart]);
} else {
    // For a past year the "this month" figure is meaningless; use the year total.
    $resMonth = $residents; $docMonth = $docs; $blotMonth = $blotters;
}

 
// Growth chart: the 12 months (Jan–Dec) of the selected year.
$months = [];
for ($mn = 1; $mn <= 12; $mn++) { $months[] = sprintf('%04d-%02d', $selYear, $mn); }
$monthLabels = array_map(function ($m) { return date('M', strtotime($m . '-01')); }, $months);
$growthData = array_fill(0, 12, 0);
$gs = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM residents WHERE deleted_at IS NULL$resYear$scopeN GROUP BY ym");
$gs->bind_param($yTypes, ...$yParams);
$gs->execute();
$gr = $gs->get_result();
while ($row = $gr->fetch_assoc()) {
    $idx = array_search($row['ym'], $months, true);
    if ($idx !== false) { $growthData[$idx] = (int)$row['c']; }
}

 
// Sparklines: the second half (Jul–Dec) of the selected year as the recent
// trend, compared against the first half (Jan–Jun) of the same year.
$sparkMonths = [];
for ($mn = 7; $mn <= 12; $mn++) { $sparkMonths[] = sprintf('%04d-%02d', $selYear, $mn); }
$sparkStart = sprintf('%04d-07-01 00:00:00', $selYear); // start of second half
$prevStart  = sprintf('%04d-01-01 00:00:00', $selYear); // start of first half
// $prevStart .. $sparkStart is the first-half comparison window.

function spark_series($conn, $sql, $types = '', $params = []) {
    $out = [];
    $s = $conn->prepare($sql);
    if ($s === false) { return $out; }
    if ($types !== '') { $s->bind_param($types, ...$params); }
    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) { $out[$row['ym']] = (int)$row['c']; }
    $s->close();
    return $out;
}
function spark_prev_total($conn, $sql, $types = '', $params = []) {
    $s = $conn->prepare($sql);
    if ($s === false) { return 0; }
    if ($types !== '') { $s->bind_param($types, ...$params); }
    $s->execute();
    $total = (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
    $s->close();
    return $total;
}
$residentsMonthly = spark_series($conn, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ?" . $scopeN . " GROUP BY ym", $scoped ? 'si' : 's', $scoped ? [$sparkStart, $bid] : [$sparkStart]);
$docsMonthly = spark_series($conn, "SELECT DATE_FORMAT(requested_at,'%Y-%m') ym, COUNT(*) c FROM document_requests WHERE requested_at >= ?" . $scopeN . " GROUP BY ym", $scoped ? 'si' : 's', $scoped ? [$sparkStart, $bid] : [$sparkStart]);
$blotExpr = "COALESCE(incident_datetime, report_datetime, created_at)";
$blotMonthly = spark_series($conn, "SELECT DATE_FORMAT($blotExpr,'%Y-%m') ym, COUNT(*) c FROM blotter_records WHERE $blotExpr >= ?" . $scopeN . " GROUP BY ym", $scoped ? 'si' : 's', $scoped ? [$sparkStart, $bid] : [$sparkStart]);
$hhMonthly = spark_series($conn, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(DISTINCT household_no) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND household_no IS NOT NULL AND household_no <> ''" . $scopeN . " GROUP BY ym", $scoped ? 'si' : 's', $scoped ? [$sparkStart, $bid] : [$sparkStart]);
$pwdMonthly = spark_series($conn, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND is_pwd IN ('Yes',1,'1')" . $scopeN . " GROUP BY ym", $scoped ? 'si' : 's', $scoped ? [$sparkStart, $bid] : [$sparkStart]);
$stuMonthly = spark_series($conn, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND is_student IN ('Yes',1,'1')" . $scopeN . " GROUP BY ym", $scoped ? 'si' : 's', $scoped ? [$sparkStart, $bid] : [$sparkStart]);
 
$resPrev = spark_prev_total($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ?" . $scopeN, $scoped ? 'sii' : 'ss', $scoped ? [$prevStart, $sparkStart, $bid] : [$prevStart, $sparkStart]);
$docPrev = spark_prev_total($conn, "SELECT COUNT(*) c FROM document_requests WHERE requested_at >= ? AND requested_at < ?" . $scopeN, $scoped ? 'sii' : 'ss', $scoped ? [$prevStart, $sparkStart, $bid] : [$prevStart, $sparkStart]);
$bloPrev = spark_prev_total($conn, "SELECT COUNT(*) c FROM blotter_records WHERE $blotExpr >= ? AND $blotExpr < ?" . $scopeN, $scoped ? 'sii' : 'ss', $scoped ? [$prevStart, $sparkStart, $bid] : [$prevStart, $sparkStart]);
$hhPrev  = spark_prev_total($conn, "SELECT COUNT(DISTINCT household_no) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ? AND household_no IS NOT NULL AND household_no <> ''" . $scopeN, $scoped ? 'sii' : 'ss', $scoped ? [$prevStart, $sparkStart, $bid] : [$prevStart, $sparkStart]);
$pwdPrev = spark_prev_total($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ? AND is_pwd IN ('Yes',1,'1')" . $scopeN, $scoped ? 'sii' : 'ss', $scoped ? [$prevStart, $sparkStart, $bid] : [$prevStart, $sparkStart]);
$stuPrev = spark_prev_total($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ? AND is_student IN ('Yes',1,'1')" . $scopeN, $scoped ? 'sii' : 'ss', $scoped ? [$prevStart, $sparkStart, $bid] : [$prevStart, $sparkStart]);
function spark_build($monthly, $sparkMonths, $currentTotal, $prevTotal) {
    $data = [];
    $cur = 0;
    foreach ($sparkMonths as $m) { $v = (int)($monthly[$m] ?? 0); $data[] = $v; $cur += $v; }
    if ($cur === 0) { $cur = $currentTotal; }
    if ($prevTotal > 0) { $pct = (($cur - $prevTotal) / $prevTotal) * 100; }
    else { $pct = $cur > 0 ? 100 : 0; }
    // "meaningful" = there was a prior period to compare against; otherwise the
    // pct is just a placeholder and should not be shown as a real trend.
    return ['data' => $data, 'pct' => $pct, 'up' => ($cur - $prevTotal) >= 0, 'prev' => $prevTotal, 'meaningful' => ($prevTotal > 0)];
}
$sparkResidents = spark_build($residentsMonthly, $sparkMonths, $residents, $resPrev);
$sparkDocs      = spark_build($docsMonthly, $sparkMonths, $docs, $docPrev);
$sparkBlotters  = spark_build($blotMonthly, $sparkMonths, $blotters, $bloPrev);
$sparkHouse     = spark_build($hhMonthly, $sparkMonths, $total_households, $hhPrev);
$sparkPwd       = spark_build($pwdMonthly, $sparkMonths, $total_pwd, $pwdPrev);
$sparkStu       = spark_build($stuMonthly, $sparkMonths, $total_students, $stuPrev);
 
$total_seniors = run_count($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND age >= 60$resYear$scopeN", $yTypes, $yParams);
$senMonthly = spark_series($conn, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND age >= 60" . $scopeN . " GROUP BY ym", $scoped ? 'si' : 's', $scoped ? [$sparkStart, $bid] : [$sparkStart]);
$senPrev = spark_prev_total($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ? AND age >= 60" . $scopeN, $scoped ? 'sii' : 'ss', $scoped ? [$prevStart, $sparkStart, $bid] : [$prevStart, $sparkStart]);
$sparkSen = spark_build($senMonthly, $sparkMonths, $total_seniors, $senPrev);
$male = 0; $female = 0;
$ps = $conn->prepare("SELECT sex, COUNT(*) c FROM residents WHERE deleted_at IS NULL$resYear$scopeN GROUP BY sex");
$ps->bind_param($yTypes, ...$yParams);
$ps->execute();
$pr = $ps->get_result();
while ($row = $pr->fetch_assoc()) {
    $sx = strtolower(trim((string)$row['sex']));
    if ($sx === 'male') { $male = (int)$row['c']; }
    elseif ($sx === 'female') { $female = (int)$row['c']; }
}
$seniors   = $total_seniors;
$pwdScoped = run_count($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND is_pwd IN ('Yes',1,'1')$resYear$scopeN", $yTypes, $yParams);
$stuScoped = run_count($conn, "SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND is_student IN ('Yes',1,'1')$resYear$scopeN", $yTypes, $yParams);

/* Override the headline/population figures with the frozen snapshot when one
 * exists for this past year+scope. Trend charts stay live (illustrative). */
if ($usingSnapshot) {
    $residents        = (int)$snapRow['residents'];
    $total_households = (int)$snapRow['households'];
    $total_students   = (int)$snapRow['students'];
    $total_pwd        = (int)$snapRow['pwd'];
    $total_seniors    = (int)$snapRow['seniors'];
    $docs             = (int)$snapRow['documents'];
    $blotters         = (int)$snapRow['blotters'];
    $male             = (int)$snapRow['male'];
    $female           = (int)$snapRow['female'];
    $seniors          = $total_seniors;
    $pwdScoped        = $total_pwd;
    $stuScoped        = $total_students;
    $resMonth = $residents; $docMonth = $docs; $blotMonth = $blotters;
}

$popLabels = []; $popData = []; $popColors = [];
if ($male > 0)      { $popLabels[] = 'Male';            $popData[] = $male;      $popColors[] = '#0E7490'; }
if ($female > 0)    { $popLabels[] = 'Female';          $popData[] = $female;    $popColors[] = '#E58B82'; }
if ($seniors > 0)   { $popLabels[] = 'Senior Citizens'; $popData[] = $seniors;   $popColors[] = '#F59E0B'; }
if ($pwdScoped > 0) { $popLabels[] = 'PWD';             $popData[] = $pwdScoped; $popColors[] = '#16A34A'; }
if ($stuScoped > 0) { $popLabels[] = 'Students';        $popData[] = $stuScoped; $popColors[] = '#075B6B'; }

 
$docStatus = ['Pending' => 0, 'Processing' => 0, 'Released' => 0, 'Cancelled' => 0];
$ds = $conn->prepare("SELECT status, COUNT(*) c FROM document_requests WHERE 1$docYear$scopeN GROUP BY status");
$ds->bind_param($yTypes, ...$yParams);
$ds->execute();
$dsr = $ds->get_result();
while ($row = $dsr->fetch_assoc()) {
    if (isset($docStatus[$row['status']])) { $docStatus[$row['status']] = (int)$row['c']; }
}
$docStatusTotal = array_sum($docStatus);

 
$recentBlotters = [];
$bs = $conn->prepare("SELECT id, blotter_no, incident_type, report_datetime, created_at FROM blotter_records WHERE 1$blotYear$scopeN ORDER BY created_at DESC, id DESC LIMIT 4");
$bs->bind_param($yTypes, ...$yParams);
$bs->execute();
$bsr = $bs->get_result();
while ($row = $bsr->fetch_assoc()) { $recentBlotters[] = $row; }

 
function time_ago($ts) {
    if (!$ts) { return ''; }
    $diff = time() - strtotime($ts);
    if ($diff < 60) { return 'just now'; }
    if ($diff < 3600) { return floor($diff / 60) . ' min ago'; }
    if ($diff < 86400) { return floor($diff / 3600) . ' hr ago'; }
    if ($diff < 604800) { $d = (int)floor($diff / 86400); return $d . ' day' . ($d > 1 ? 's' : '') . ' ago'; }
    return date('M j, Y', strtotime($ts));
}

$activity = [];

$as = $conn->prepare("SELECT id, CONCAT_WS(' ', first_name, last_name) nm, created_at ts FROM residents WHERE deleted_at IS NULL$resYear$scopeN ORDER BY created_at DESC LIMIT 5");
$as->bind_param($yTypes, ...$yParams);
$as->execute();
$asr = $as->get_result();
while ($row = $asr->fetch_assoc()) {
    $activity[] = [
        'ts' => $row['ts'], 'tone' => '', 'icon' => 'user-plus',
        'title' => 'New resident registered',
        'desc' => trim((string)$row['nm']) . ' was added to the resident database',
        'href' => url('pages/residents.php')
    ];
}

$dsa = $conn->prepare("SELECT id, requestor_name nm, document_type dt, status st, requested_at ts FROM document_requests WHERE 1$docYear$scopeN ORDER BY requested_at DESC LIMIT 5");
$dsa->bind_param($yTypes, ...$yParams);
$dsa->execute();
$dsar = $dsa->get_result();
while ($row = $dsar->fetch_assoc()) {
    $released = ($row['st'] === 'Released');
    $activity[] = [
        'ts' => $row['ts'], 'tone' => $released ? 'green' : 'amber',
        'icon' => $released ? 'check-circle' : 'file-text',
        'title' => $released ? 'Document released' : 'Document request submitted',
        'desc' => trim((string)$row['dt']) . ' — ' . trim((string)$row['nm']),
        'href' => url('pages/documents.php')
    ];
}

$bsa = $conn->prepare("SELECT id, blotter_no bn, incident_type it, created_at ts FROM blotter_records WHERE 1$blotYear$scopeN ORDER BY created_at DESC LIMIT 5");
$bsa->bind_param($yTypes, ...$yParams);
$bsa->execute();
$bsar = $bsa->get_result();
while ($row = $bsar->fetch_assoc()) {
    $activity[] = [
        'ts' => $row['ts'], 'tone' => 'coral', 'icon' => 'alert-triangle',
        'title' => 'Blotter record created',
        'desc' => 'Incident: ' . trim((string)$row['it']) . ' (Entry #' . trim((string)$row['bn']) . ')',
        'href' => url('pages/blotters.php')
    ];
}

usort($activity, function ($a, $b) { return strtotime($b['ts']) <=> strtotime($a['ts']); });
$activity = array_slice($activity, 0, 6);

/* ---------------------------------------------------------------------------
 * Per-barangay breakdown ("see all barangays at once").
 * Only built for an admin viewing the combined (all-barangays) scope. Each row
 * carries the key figures for the selected year. Snapshot-aware: for a past
 * year with a captured snapshot we read the frozen per-barangay rows; otherwise
 * we compute live. $breakdown is keyed by barangay name for display.
 * ------------------------------------------------------------------------- */
$showBreakdown = ($isAdmin && !$scoped);
$breakdown = [];
$breakdownTotals = ['residents' => 0, 'households' => 0, 'students' => 0, 'pwd' => 0, 'seniors' => 0, 'documents' => 0, 'blotters' => 0];
if ($showBreakdown) {
    // Base map of every barangay (so barangays with zero records still appear).
    $brow = $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name ASC');
    $byId = [];
    while ($brow && $b = $brow->fetch_assoc()) {
        $byId[(int)$b['id']] = [
            'name' => $b['barangay_name'],
            'residents' => 0, 'households' => 0, 'students' => 0, 'pwd' => 0, 'seniors' => 0, 'documents' => 0, 'blotters' => 0,
        ];
    }

    if ($usingSnapshot) {
        // Frozen per-barangay snapshot rows for this year (skip the id=0 aggregate).
        if ($bs2 = $conn->prepare('SELECT barangay_id, residents, households, students, pwd, seniors, documents, blotters FROM barangay_yearly_stats WHERE stat_year=? AND barangay_id<>0')) {
            $bs2->bind_param('i', $selYear);
            $bs2->execute();
            $r2 = $bs2->get_result();
            while ($row = $r2->fetch_assoc()) {
                $id = (int)$row['barangay_id'];
                if (!isset($byId[$id])) { continue; }
                foreach (['residents','households','students','pwd','seniors','documents','blotters'] as $k) {
                    $byId[$id][$k] = (int)$row[$k];
                }
            }
            $bs2->close();
        }
    } else {
        // Live: residents-derived metrics grouped by barangay for the year.
        $sqlR = "SELECT barangay_id,
                        COUNT(*) residents,
                        COUNT(DISTINCT CASE WHEN household_no IS NOT NULL AND household_no <> '' THEN household_no END) households,
                        SUM(CASE WHEN is_student IN ('Yes',1,'1') THEN 1 ELSE 0 END) students,
                        SUM(CASE WHEN is_pwd IN ('Yes',1,'1') THEN 1 ELSE 0 END) pwd,
                        SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) seniors
                   FROM residents
                  WHERE deleted_at IS NULL AND YEAR(created_at) = ?
               GROUP BY barangay_id";
        if ($rq2 = $conn->prepare($sqlR)) {
            $rq2->bind_param('i', $selYear);
            $rq2->execute();
            $r2 = $rq2->get_result();
            while ($row = $r2->fetch_assoc()) {
                $id = (int)$row['barangay_id'];
                if (!isset($byId[$id])) { continue; }
                $byId[$id]['residents']  = (int)$row['residents'];
                $byId[$id]['households'] = (int)$row['households'];
                $byId[$id]['students']   = (int)$row['students'];
                $byId[$id]['pwd']        = (int)$row['pwd'];
                $byId[$id]['seniors']    = (int)$row['seniors'];
            }
            $rq2->close();
        }
        // Documents per barangay for the year.
        if ($dq2 = $conn->prepare("SELECT barangay_id, COUNT(*) c FROM document_requests WHERE YEAR(requested_at)=? GROUP BY barangay_id")) {
            $dq2->bind_param('i', $selYear);
            $dq2->execute();
            $r2 = $dq2->get_result();
            while ($row = $r2->fetch_assoc()) { $id = (int)$row['barangay_id']; if (isset($byId[$id])) { $byId[$id]['documents'] = (int)$row['c']; } }
            $dq2->close();
        }
        // Blotters per barangay for the year.
        if ($bq2 = $conn->prepare("SELECT barangay_id, COUNT(*) c FROM blotter_records WHERE YEAR(COALESCE(incident_datetime, report_datetime, created_at))=? GROUP BY barangay_id")) {
            $bq2->bind_param('i', $selYear);
            $bq2->execute();
            $r2 = $bq2->get_result();
            while ($row = $r2->fetch_assoc()) { $id = (int)$row['barangay_id']; if (isset($byId[$id])) { $byId[$id]['blotters'] = (int)$row['c']; } }
            $bq2->close();
        }
    }

    // Finalise: ordered list + totals.
    foreach ($byId as $v) {
        $breakdown[] = $v;
        foreach ($breakdownTotals as $k => $_) { $breakdownTotals[$k] += (int)$v[$k]; }
    }
}

 
$chartPayload = [
    'growth' => ['labels' => $monthLabels, 'data' => $growthData, 'color' => '#0E7490'],
    'population' => ['labels' => $popLabels, 'data' => $popData, 'colors' => $popColors],
    'sparks' => [
        'labels' => array_map(function ($m) { return date('M', strtotime($m . '-01')); }, $sparkMonths),
        'residents' => $sparkResidents['data'],
        'docs'      => $sparkDocs['data'],
        'blotters'  => $sparkBlotters['data'],
        'house'     => $sparkHouse['data'],
        'pwd'       => $sparkPwd['data'],
        'students'  => $sparkStu['data'],
        'seniors'   => $sparkSen['data'],
    ],
];

$page_scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'assets/js/dashboard.js',
];

include BASE_PATH . '/partials/header.php';
?>

<script type="application/json" id="dashboard-data"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<!-- ============================ Filter / control bar ===================== -->
<div class="dash-bar">
    <div class="dash-bar__scope">
        <span class="dash-bar__year"><?= (int)$selYear ?></span>
        <span class="dash-bar__brgy"><?= e($brgyName) ?></span>
        <?php if ($isPastYear): ?>
            <?php if ($usingSnapshot): ?>
                <span class="chip chip--snapshot" title="Figures come from a frozen year-end snapshot">
                    <?= icon('shield', 'ico ico--xs') ?> Snapshot
                </span>
            <?php else: ?>
                <span class="chip chip--live" title="No snapshot captured for this year; figures are recomputed live">
                    <?= icon('activity', 'ico ico--xs') ?> Live estimate
                </span>
            <?php endif; ?>
        <?php else: ?>
            <span class="chip chip--live" title="Current year, calculated live"><?= icon('activity', 'ico ico--xs') ?> Live</span>
        <?php endif; ?>
    </div>

    <form method="get" class="dash-bar__filters">
        <?php if ($isAdmin): ?>
        <div class="field">
            <label class="field__label" for="f_brgy">Barangay</label>
            <select id="f_brgy" name="barangay" title="Filter by barangay">
                <option value="0">All Barangays</option>
                <?php while ($barangayList && $bb = $barangayList->fetch_assoc()): ?>
                <option value="<?= (int)$bb['id'] ?>"<?= $selBrgy === (int)$bb['id'] ? ' selected' : '' ?>><?= e($bb['barangay_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="field">
            <label class="field__label" for="f_year">Year</label>
            <select id="f_year" name="year" title="Filter by year">
                <?php foreach ($yearOptions as $yopt): ?>
                <option value="<?= (int)$yopt ?>"<?= $selYear === (int)$yopt ? ' selected' : '' ?>><?= (int)$yopt ?><?= isset($capturedYears[(int)$yopt]) ? ' (saved)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="dash-bar__actions">
            <button class="btn btn-primary" type="submit">Apply</button>
            <?php if ($selYear !== $currentYear || ($isAdmin && $selBrgy > 0)): ?>
            <a class="btn btn--ghost" href="<?= e(url('pages/dashboard.php')) ?>">Reset</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a class="btn btn--ghost" href="<?= e(url('admin/yearly_capture.php?year=' . (int)$selYear . '&token=' . csrf_token())) ?>"
               data-confirm="Capture a frozen snapshot of <?= (int)$selYear ?> for every barangay? Re-running overwrites this year's snapshot."
               data-confirm-title="Capture yearly snapshot">
               <?= icon('shield', 'ico ico--xs') ?> <?= isset($capturedYears[$selYear]) ? 'Re-capture' : 'Capture' ?> <?= (int)$selYear ?>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ============================ Quick Actions (top) ====================== -->
<div class="section-head section-head--tight">
    <div>
        <h2>Quick Actions</h2>
        <p>Jump straight into the most common tasks</p>
    </div>
</div>
<div class="quick-grid quick-grid--top">
    <a class="quick" href="<?= e(url('pages/resident_add.php')) ?>">
        <span class="quick__ico" aria-hidden="true"><?= icon('user-plus') ?></span>
        <span class="quick__text"><span class="quick__label">Add Resident</span><span class="quick__hint">Register a resident</span></span>
    </a>
    <a class="quick quick--coral" href="<?= e(url('pages/documents.php')) ?>">
        <span class="quick__ico" aria-hidden="true"><?= icon('file-plus') ?></span>
        <span class="quick__text"><span class="quick__label">Document Request</span><span class="quick__hint">Log a request</span></span>
    </a>
    <a class="quick quick--amber" href="<?= e(url('pages/blotter_add.php')) ?>">
        <span class="quick__ico" aria-hidden="true"><?= icon('alert-triangle') ?></span>
        <span class="quick__text"><span class="quick__label">New Blotter</span><span class="quick__hint">Record an incident</span></span>
    </a>
    <a class="quick quick--green" href="<?= e(url('pages/incident_report.php')) ?>">
        <span class="quick__ico" aria-hidden="true"><?= icon('receipt') ?></span>
        <span class="quick__text"><span class="quick__label">Incident Receipt</span><span class="quick__hint">Issue a receipt</span></span>
    </a>
    <?php if ($isAdmin): ?>
    <a class="quick quick--navy" href="<?= e(url('admin/users.php')) ?>">
        <span class="quick__ico" aria-hidden="true"><?= icon('shield') ?></span>
        <span class="quick__text"><span class="quick__label">Manage Users</span><span class="quick__hint">Accounts &amp; roles</span></span>
    </a>
    <?php endif; ?>
</div>

<!-- ============================ At a Glance ============================== -->
<div class="section-head">
    <div>
        <h2>At a Glance</h2>
        <p>
            Key figures for <strong><?= (int)$selYear ?></strong> · <?= e($brgyName) ?>
            <?php if ($usingSnapshot): ?> · from year-end snapshot<?php endif; ?>
        </p>
    </div>
</div>

<div class="kpi-grid kpi-grid--glance">
    <?php
     
    $glance = [
        ['Residents registered', $residents, 'residents', '#0E7490', 'pages/residents.php', 'View residents'],
        ['Document Requests', $docs, 'docs', '#075B6B', 'pages/documents.php', 'View requests'],
        ['Blotter Records', $blotters, 'blotters', '#C2544A', 'pages/blotters.php', 'View records'],
        ['Households', $total_households, 'house', '#B45309', 'pages/residents.php', 'View residents'],
        ['PWDs', $total_pwd, 'pwd', '#15803D', 'pages/residents.php', 'View residents'],
        ['Students', $total_students, 'students', '#6D28D9', 'pages/residents.php', 'View residents'],
        ['Seniors (60+)', $total_seniors, 'seniors', '#F59E0B', 'pages/residents.php', 'View residents'],
    ];
    $sparkMap = ['residents' => $sparkResidents, 'docs' => $sparkDocs, 'blotters' => $sparkBlotters, 'house' => $sparkHouse, 'pwd' => $sparkPwd, 'students' => $sparkStu, 'seniors' => $sparkSen];
    foreach ($glance as $g):
        [$gLabel, $gTotal, $gKey, $gColor, $gHref, $gLink] = $g;
        $sp = $sparkMap[$gKey];
        // Only show a growth delta when there is a real prior period to compare
        // against; otherwise show a neutral tag so cards aren't all "+100%".
        $showDelta = (!$usingSnapshot) && $sp['meaningful'];
    ?>
    <article class="kpi kpi--glance" style="--kpi-accent: <?= e($gColor) ?>; --spark: <?= e($gColor) ?>">
        <div class="kpi__head">
            <span class="kpi__dot" aria-hidden="true"></span>
            <p class="kpi__title"><?= e($gLabel) ?></p>
        </div>
        <p class="kpi__value"><?= number_format((int)$gTotal) ?></p>
        <?php if ($showDelta): ?>
        <p class="kpi__delta <?= $sp['up'] ? 'kpi__delta--up' : 'kpi__delta--down' ?>">
            <span aria-hidden="true"><?= $sp['up'] ? '↗' : '↘' ?></span>
            <?= ($sp['up'] ? '+' : '') . number_format($sp['pct'], 0) ?>% vs. H1
        </p>
        <?php else: ?>
        <p class="kpi__delta kpi__delta--muted"><?= $usingSnapshot ? 'Year-end total' : 'in ' . (int)$selYear ?></p>
        <?php endif; ?>
        <a class="kpi__link" href="<?= e(url($gHref)) ?>"><?= e($gLink) ?> <?= icon('arrow-right', 'ico ico--xs') ?></a>
    </article>
    <?php endforeach; ?>
</div>

<?php if ($showBreakdown): ?>
<!-- ===================== Per-barangay breakdown (see all) =============== -->
<div class="section-head">
    <div>
        <h2>All Barangays</h2>
        <p>Every barangay's figures for <strong><?= (int)$selYear ?></strong><?php if ($usingSnapshot): ?> · year-end snapshot<?php endif; ?></p>
    </div>
    <span class="muted-meta"><?= count($breakdown) ?> barangays</span>
</div>
<div class="card table-wrap table-wrap--sticky">
    <table class="data-table data-table--compact">
        <thead>
            <tr>
                <th class="is-left">Barangay</th>
                <th>Residents</th>
                <th>Households</th>
                <th>Students</th>
                <th>PWD</th>
                <th>Seniors</th>
                <th>Documents</th>
                <th>Blotters</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($breakdown as $b): ?>
            <tr>
                <td class="is-left"><?= e($b['name']) ?></td>
                <td><?= number_format((int)$b['residents']) ?></td>
                <td><?= number_format((int)$b['households']) ?></td>
                <td><?= number_format((int)$b['students']) ?></td>
                <td><?= number_format((int)$b['pwd']) ?></td>
                <td><?= number_format((int)$b['seniors']) ?></td>
                <td><?= number_format((int)$b['documents']) ?></td>
                <td><?= number_format((int)$b['blotters']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="data-table__total">
                <td class="is-left">All Barangays</td>
                <td><?= number_format($breakdownTotals['residents']) ?></td>
                <td><?= number_format($breakdownTotals['households']) ?></td>
                <td><?= number_format($breakdownTotals['students']) ?></td>
                <td><?= number_format($breakdownTotals['pwd']) ?></td>
                <td><?= number_format($breakdownTotals['seniors']) ?></td>
                <td><?= number_format($breakdownTotals['documents']) ?></td>
                <td><?= number_format($breakdownTotals['blotters']) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<div class="section-head">
    <div>
        <h2>Insights</h2>
        <p>Trends calculated from actual records</p>
    </div>
</div>

<div class="dash-grid">
    <section class="panel" aria-labelledby="growth-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="growth-heading">Resident Statistics</h3>
                <p class="panel__sub">New residents registered per month in <?= (int)$selYear ?></p>
            </div>
        </div>
        <div class="chart-box">
            <canvas id="growthChart" role="img" aria-label="Bar chart of new residents registered per month over the last 12 months"></canvas>
        </div>
    </section>

    <section class="panel" aria-labelledby="pop-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="pop-heading">Population Breakdown</h3>
                <p class="panel__sub">Residents registered in <?= (int)$selYear ?></p>
            </div>
        </div>
        <div class="chart-box chart-box--donut">
            <canvas id="populationChart" role="img" aria-label="Donut chart of the resident population breakdown"></canvas>
        </div>
    </section>
</div>

<div class="dash-grid">
    <section class="panel" aria-labelledby="activity-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="activity-heading">Recent Activity</h3>
                <p class="panel__sub">Latest updates across the system</p>
            </div>
        </div>
        <?php if ($activity): ?>
            <ul class="activity">
                <?php foreach ($activity as $item): ?>
                    <li class="activity__item">
                        <span class="activity__dot<?= $item['tone'] !== '' ? ' activity__dot--' . e($item['tone']) : '' ?>" aria-hidden="true"><?= icon($item['icon'], 'ico ico--sm') ?></span>
                        <div class="activity__body">
                            <p class="activity__title"><?= e($item['title']) ?></p>
                            <p class="activity__desc"><?= e($item['desc']) ?></p>
                            <span class="activity__time"><?= e(time_ago($item['ts'])) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">
                <span class="empty__ico" aria-hidden="true"><?= icon('inbox') ?></span>
                <p class="empty__title">No recent activity</p>
                <p class="empty__text">New residents, document requests and blotter records will appear here as they are created.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="docs-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="docs-heading">Document Requests</h3>
                <p class="panel__sub">Requests by current status</p>
            </div>
            <a class="panel__link" href="<?= e(url('pages/documents.php')) ?>">View all <?= icon('arrow-right', 'ico ico--xs') ?></a>
        </div>
        <?php if ($docStatusTotal > 0): ?>
            <ul class="stat-list">
                <?php foreach ($docStatus as $label => $count): ?>
                    <li class="stat-row">
                        <span class="stat-row__label"><span class="badge badge--<?= strtolower($label) ?>"><?= e($label) ?></span></span>
                        <span class="stat-row__value"><?= (int)$count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">
                <span class="empty__ico" aria-hidden="true"><?= icon('file-text') ?></span>
                <p class="empty__title">No document requests yet</p>
                <p class="empty__text">Submitted document requests and their statuses will be summarised here.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="dash-grid dash-grid--even">
    <section class="panel" aria-labelledby="blotter-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="blotter-heading">Blotter / Incidents</h3>
                <p class="panel__sub">Summary of recorded incidents</p>
            </div>
            <a class="panel__link" href="<?= e(url('pages/blotters.php')) ?>">View all <?= icon('arrow-right', 'ico ico--xs') ?></a>
        </div>
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="mini-stat__value"><?= number_format($blotters) ?></div>
                <div class="mini-stat__label">Total incidents</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat__value"><?= number_format($blotMonth) ?></div>
                <div class="mini-stat__label"><?= $selYear === $currentYear ? 'Recorded this month' : 'Recorded in ' . (int)$selYear ?></div>
            </div>
        </div>
        <?php if ($recentBlotters): ?>
            <ul class="stat-list">
                <?php foreach ($recentBlotters as $b): ?>
                    <li class="stat-row">
                        <span class="stat-row__label"><?= icon('alert-triangle', 'ico ico--sm') ?> <?= e(trim((string)$b['incident_type']) !== '' ? $b['incident_type'] : 'Incident') ?></span>
                        <span class="muted-meta">Entry #<?= e($b['blotter_no']) ?> · <?= e($b['report_datetime'] ? date('M j, Y', strtotime($b['report_datetime'])) : time_ago($b['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">
                <span class="empty__ico" aria-hidden="true"><?= icon('clipboard') ?></span>
                <p class="empty__title">No incidents recorded</p>
                <p class="empty__text">Blotter entries will be listed here once incidents are logged.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="docstat-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="docstat-heading">Document Snapshot</h3>
                <p class="panel__sub">Requests recorded in <?= (int)$selYear ?></p>
            </div>
            <a class="panel__link" href="<?= e(url('pages/documents.php')) ?>">Manage <?= icon('arrow-right', 'ico ico--xs') ?></a>
        </div>
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="mini-stat__value"><?= number_format($docs) ?></div>
                <div class="mini-stat__label">Total requests</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat__value"><?= number_format($docStatus['Released'] ?? 0) ?></div>
                <div class="mini-stat__label">Released</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat__value"><?= number_format(($docStatus['Pending'] ?? 0) + ($docStatus['Processing'] ?? 0)) ?></div>
                <div class="mini-stat__label">In progress</div>
            </div>
        </div>
    </section>
</div>

<?php include BASE_PATH . '/partials/footer.php'; ?>
