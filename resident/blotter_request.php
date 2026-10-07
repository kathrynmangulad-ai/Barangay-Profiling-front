<?php











require_once __DIR__ . '/../config/config.php';
require_role(['resident']);

$page_title = 'File a Blotter Report';
$page_crumb = 'File a Blotter Report';

$rid = current_resident_id();
$bid = current_barangay_id();
$me  = current_user();

$error = '';
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $incident_type = trim($_POST['incident_type'] ?? '');
    $place         = trim($_POST['place_of_incident'] ?? '');
    $incident_dt   = trim($_POST['incident_datetime'] ?? '');
    $narrative     = trim($_POST['narrative'] ?? '');

    if ($rid === null || $bid === null) {
        $error = 'Your account is not linked to a resident record yet. Please contact your barangay secretary.';

    } elseif ($incident_type === '' || $place === '' || $narrative === '') {
        $error = 'Please complete all required fields.';

    } else {
         
        $nq = $conn->prepare(
            'SELECT COALESCE(MAX(CAST(blotter_no AS UNSIGNED)),0)+1 n
               FROM blotter_records WHERE barangay_id=?'
        );
        $nq->bind_param('i', $bid);
        $nq->execute();
        $next = (int)$nq->get_result()->fetch_assoc()['n'];
        $nq->close();

        $report_dt     = date('Y-m-d H:i:s');
        $incident_dt_v = ($incident_dt !== '') ? $incident_dt : null;
        $suspect       = null;
        $victim        = null;
        $status        = 'Open';
        

        $reporterAddr  = null;
        if ($rid !== null) {
            $aq = $conn->prepare('SELECT address FROM residents WHERE id=? LIMIT 1');
            $aq->bind_param('i', $rid);
            $aq->execute();
            $arow = $aq->get_result()->fetch_assoc();
            $aq->close();
            $reporterAddr = $arow['address'] ?? null;
        }

        


        $blotterNo = (string)$next;

        $stmt = $conn->prepare(
            'INSERT INTO blotter_records
                (barangay_id, resident_id, blotter_no, reporting_person, reporting_address,
                 incident_type, report_datetime, incident_datetime, place_of_incident,
                 suspect_data, victim_data, narrative, recorded_by, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        





        $stmt->bind_param(
            'ii' . str_repeat('s', 12),
            $bid, $rid, $blotterNo, $me['full_name'], $reporterAddr,
            $incident_type, $report_dt, $incident_dt_v, $place,
            $suspect, $victim, $narrative, $me['full_name'], $status
        );

        if ($stmt->execute()) {
            $newId = (int)$conn->insert_id;
            $stmt->close();
            log_access('blotter_filed', 'blotter_record', $newId);
            redirect(url('resident/blotter_request.php?saved=1'));
        }
        $stmt->close();
        $error = 'We could not file your report right now. Please try again.';
    }
}

include BASE_PATH . '/partials/header.php';
?>

<?php if ($saved): ?>
    <div class="alert alert-success" role="status">
        Your blotter report has been filed. The barangay office will contact you regarding this incident.
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>


<div class="card">
    <form method="post">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div>
                <label for="incident_type">Type of Incident <span aria-hidden="true">*</span></label>
                <input id="incident_type" name="incident_type" required maxlength="150"
                       placeholder="e.g. Physical Injury">
            </div>

            <div>
                <label for="place_of_incident">Place of Incident <span aria-hidden="true">*</span></label>
                <input id="place_of_incident" name="place_of_incident" required maxlength="255"
                       placeholder="Where it happened">
            </div>

            <div>
                <label for="incident_datetime">Date/Time of Incident</label>
                <input id="incident_datetime" type="datetime-local" name="incident_datetime">
            </div>

            <div class="full">
                <label for="narrative">Narrative <span aria-hidden="true">*</span></label>
                <textarea id="narrative" name="narrative" rows="6" required
                          placeholder="Describe what happened"></textarea>
            </div>
        </div>

        <br>
        <div class="toolbar">
            <button class="btn" type="submit">File Report</button>
            <a class="btn btn--ghost" href="<?= e(url('resident/resident_dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
</div>

<?php include BASE_PATH . '/partials/footer.php'; ?>
