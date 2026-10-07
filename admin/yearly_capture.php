
<?php
/**
 * Capture (finalize) a yearly snapshot of per-barangay figures.
 *
 * Admin-only. Recomputes the key figures for the chosen year straight from the
 * live tables and writes one row per barangay into barangay_yearly_stats, plus
 * a barangay_id = 0 "all barangays" aggregate row. Re-running for the same year
 * overwrites that year's snapshot (upsert), so an admin can refresh it until
 * they consider the year closed.
 */

require_once __DIR__ . '/../config/config.php';
require_admin();
csrf_verify_get();

$year = (int)($_GET['year'] ?? 0);
$currentYear = (int)date('Y');
if ($year < 2000 || $year > $currentYear) {
    die('Invalid year to capture.');
}

/*
 * Per-barangay figures for the year, computed from live rows. Residents-based
 * metrics exclude soft-deleted rows and are counted by registration year
 * (created_at). Documents and blotters are counted by their own date columns.
 * We build an associative map keyed by barangay_id and fill it from several
 * grouped queries.
 */
$stats = []; // [barangay_id => [residents, households, students, pwd, seniors, male, female, documents, blotters]]
$blank = ['residents' => 0, 'households' => 0, 'students' => 0, 'pwd' => 0, 'seniors' => 0, 'male' => 0, 'female' => 0, 'documents' => 0, 'blotters' => 0];

$touch = function ($bid) use (&$stats, $blank) {
    $bid = (int)$bid;
    if (!isset($stats[$bid])) { $stats[$bid] = $blank; }
    return $bid;
};

// Residents breakdown (residents, students, pwd, seniors, male, female).
$sql = "SELECT barangay_id,
               COUNT(*) residents,
               SUM(CASE WHEN is_student IN ('Yes',1,'1') THEN 1 ELSE 0 END) students,
               SUM(CASE WHEN is_pwd IN ('Yes',1,'1') THEN 1 ELSE 0 END) pwd,
               SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) seniors,
               SUM(CASE WHEN LOWER(sex) = 'male' THEN 1 ELSE 0 END) male,
               SUM(CASE WHEN LOWER(sex) = 'female' THEN 1 ELSE 0 END) female
          FROM residents
         WHERE deleted_at IS NULL AND YEAR(created_at) = ?
      GROUP BY barangay_id";
$st = $conn->prepare($sql);
$st->bind_param('i', $year);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) {
    $bid = $touch($row['barangay_id']);
    $stats[$bid]['residents'] = (int)$row['residents'];
    $stats[$bid]['students']  = (int)$row['students'];
    $stats[$bid]['pwd']       = (int)$row['pwd'];
    $stats[$bid]['seniors']   = (int)$row['seniors'];
    $stats[$bid]['male']      = (int)$row['male'];
    $stats[$bid]['female']    = (int)$row['female'];
}
$st->close();

// Households: distinct non-empty household_no per barangay.
$sql = "SELECT barangay_id, COUNT(DISTINCT household_no) hh
          FROM residents
         WHERE deleted_at IS NULL AND YEAR(created_at) = ?
           AND household_no IS NOT NULL AND household_no <> ''
      GROUP BY barangay_id";
$st = $conn->prepare($sql);
$st->bind_param('i', $year);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) {
    $bid = $touch($row['barangay_id']);
    $stats[$bid]['households'] = (int)$row['hh'];
}
$st->close();

// Document requests per barangay for the year.
$sql = "SELECT barangay_id, COUNT(*) c FROM document_requests WHERE YEAR(requested_at) = ? GROUP BY barangay_id";
$st = $conn->prepare($sql);
$st->bind_param('i', $year);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) {
    $bid = $touch($row['barangay_id']);
    $stats[$bid]['documents'] = (int)$row['c'];
}
$st->close();

// Blotter records per barangay for the year (by incident/report/created date).
$sql = "SELECT barangay_id, COUNT(*) c FROM blotter_records
         WHERE YEAR(COALESCE(incident_datetime, report_datetime, created_at)) = ?
      GROUP BY barangay_id";
$st = $conn->prepare($sql);
$st->bind_param('i', $year);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) {
    $bid = $touch($row['barangay_id']);
    $stats[$bid]['blotters'] = (int)$row['c'];
}
$st->close();

// Build the barangay_id = 0 aggregate (sum across all barangays).
$agg = $blank;
foreach ($stats as $bid => $v) {
    foreach ($blank as $k => $_) { $agg[$k] += (int)$v[$k]; }
}
$stats[0] = $agg;

// Upsert each row.
$adminId = (int)$_SESSION['user_id'];
$up = $conn->prepare(
    "INSERT INTO barangay_yearly_stats
        (stat_year, barangay_id, residents, households, students, pwd, seniors, male, female, documents, blotters, captured_by)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        residents=VALUES(residents), households=VALUES(households), students=VALUES(students),
        pwd=VALUES(pwd), seniors=VALUES(seniors), male=VALUES(male), female=VALUES(female),
        documents=VALUES(documents), blotters=VALUES(blotters),
        captured_at=CURRENT_TIMESTAMP, captured_by=VALUES(captured_by)"
);

$conn->begin_transaction();
try {
    foreach ($stats as $bid => $v) {
        $bidI = (int)$bid;
        $up->bind_param(
            'iiiiiiiiiiii',
            $year, $bidI, $v['residents'], $v['households'], $v['students'],
            $v['pwd'], $v['seniors'], $v['male'], $v['female'], $v['documents'], $v['blotters'], $adminId
        );
        $up->execute();
    }
    $up->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    die('Snapshot capture failed: ' . e($e->getMessage()));
}

log_access('yearly_stats_captured', 'year', $year);
flash('ok', "Yearly snapshot for {$year} captured — " . (count($stats) - 1) . " barangay record(s) saved.");
redirect(url('pages/dashboard.php?year=' . $year . '&snap=1'));
