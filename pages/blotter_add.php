<?php
require_once __DIR__ . '/../config/config.php'; require_staff(); $page_title = 'New Blotter';

$error = '';
$old = [
    'barangay_id' => (int)($_SESSION['barangay_id'] ?? 0), 'blotter_no' => '', 'reporting_person' => '',
    'reporting_address' => '', 'incident_type' => '', 'report_datetime' => date('Y-m-d\TH:i'),
    'incident_datetime' => '', 'place_of_incident' => '', 'suspect_data' => '', 'victim_data' => '', 'narrative' => '', 'relief_sought' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($old as $k => $v) { if ($k !== 'barangay_id') { $old[$k] = trim((string)($_POST[$k] ?? '')); } }
    $old['barangay_id'] = $_SESSION['role'] === 'admin' ? (int)($_POST['barangay_id'] ?? 0) : (int)$_SESSION['barangay_id'];

    // datetime-local gives "YYYY-MM-DDTHH:MM"; the column wants "YYYY-MM-DD HH:MM:SS" or NULL.
    $to_sql = static function (string $v): ?string {
        if ($v === '') { return null; }
        $t = strtotime($v);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    };
    $report_dt   = $to_sql($old['report_datetime']);
    $incident_dt = $to_sql($old['incident_datetime']);

    if ($old['barangay_id'] <= 0) { $error = 'Choose a barangay.'; }
    elseif ($old['blotter_no'] === '' || $old['reporting_person'] === '' || $old['incident_type'] === '') { $error = 'Fill in the entry number, reporting person and type of incident.'; }
    else {
        $stmt = $conn->prepare('INSERT INTO blotter_records
            (barangay_id, blotter_no, reporting_person, reporting_address, incident_type, report_datetime, incident_datetime,
             place_of_incident, suspect_data, victim_data, narrative, relief_sought, recorded_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $recorded = (string)($_SESSION['full_name'] ?? '');
        $stmt->bind_param('issssssssssss', $old['barangay_id'], $old['blotter_no'], $old['reporting_person'], $old['reporting_address'],
            $old['incident_type'], $report_dt, $incident_dt, $old['place_of_incident'], $old['suspect_data'], $old['victim_data'],
            $old['narrative'], $old['relief_sought'], $recorded);
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            $stmt->close();
            redirect(url('pages/blotters.php'));
        }
        $error = 'The record could not be saved. Please try again.';
        $stmt->close();
    }
}

if ($old['blotter_no'] === '') {
    $next = 1;
    if ($x = $conn->query('SELECT COALESCE(MAX(CAST(blotter_no AS UNSIGNED)), 0) + 1 AS n FROM blotter_records')) { $next = (int)$x->fetch_assoc()['n']; }
    $old['blotter_no'] = (string)$next;
}
$barangays = $_SESSION['role'] === 'admin' ? $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name') : null;
include BASE_PATH . '/partials/header.php';?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="card">
<?php /* Four columns on a desktop so the whole form fits one screen; the three text areas share one row. */ ?>
<form method="post"><?= csrf_field() ?><div class="form-grid form-grid--4">
    <h2 class="form-section full">Report</h2>
    <?php if ($barangays): ?>
    <div><label for="barangay_id">Barangay</label>
        <select id="barangay_id" name="barangay_id" required>
            <?php while ($b = $barangays->fetch_assoc()): ?>
            <option value="<?= (int)$b['id'] ?>"<?= (int)$b['id'] === $old['barangay_id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option>
            <?php endwhile; ?>
        </select></div>
    <?php endif; ?>
    <div><label for="blotter_no">Blotter Entry Number</label><input id="blotter_no" name="blotter_no" value="<?= e($old['blotter_no']) ?>" required></div>
    <div><label for="report_datetime">Date/Time of Report</label><input id="report_datetime" type="datetime-local" name="report_datetime" value="<?= e($old['report_datetime']) ?>"></div>
    <div><label for="reporting_person">Reporting Person</label><input id="reporting_person" name="reporting_person" value="<?= e($old['reporting_person']) ?>" required></div>
    <div<?= $barangays ? ' class="full"' : '' ?>><label for="reporting_address">Address of Reporting Person</label><input id="reporting_address" name="reporting_address" value="<?= e($old['reporting_address']) ?>"></div>

    <h2 class="form-section full">Incident</h2>
    <div><label for="incident_type">Type of Incident</label><input id="incident_type" name="incident_type" value="<?= e($old['incident_type']) ?>" required></div>
    <div><label for="incident_datetime">Date/Time of Incident</label><input id="incident_datetime" type="datetime-local" name="incident_datetime" value="<?= e($old['incident_datetime']) ?>"></div>
    <div class="span-2"><label for="place_of_incident">Place of Incident</label><input id="place_of_incident" name="place_of_incident" value="<?= e($old['place_of_incident']) ?>"></div>
    <div><label for="suspect_data">Suspect Data</label><textarea id="suspect_data" name="suspect_data" rows="7"><?= e($old['suspect_data']) ?></textarea></div>
    <div><label for="victim_data">Victim Data</label><textarea id="victim_data" name="victim_data" rows="7"><?= e($old['victim_data']) ?></textarea></div>
    <div class="span-2"><label for="narrative">Narrative of Incident</label><textarea id="narrative" name="narrative" rows="7"><?= e($old['narrative']) ?></textarea></div>
    <div class="span-2"><label for="relief_sought">Relief Sought <small>(printed on KP Form 7 after "THEREFORE, I/We pray...")</small></label><textarea id="relief_sought" name="relief_sought" rows="3"><?= e($old['relief_sought']) ?></textarea></div>
</div>
<div class="form-actions"><button class="btn" type="submit">Save Blotter</button>
    <a class="btn btn--ghost" href="<?= e(url('pages/blotters.php')) ?>">Cancel</a></div></form></div>
<?php include BASE_PATH . '/partials/footer.php';
