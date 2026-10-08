<?php









require_once __DIR__ . '/../config/config.php';
require_role(['resident']);

$page_title = 'Request Document';
$page_crumb = 'Request Document';

$rid  = current_resident_id();
$bid  = current_barangay_id();
$me   = current_user();

$error = '';
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $document_type = trim($_POST['document_type'] ?? '');
    $purpose       = trim($_POST['purpose'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');

    if ($rid === null || $bid === null) {
        $error = 'Your account is not linked to a resident record yet. Please contact your barangay secretary.';

    } elseif ($document_type === '' || $purpose === '') {
        $error = 'Please complete all required fields.';

    } else {
        $requestor_name = $me['full_name'];

        $stmt = $conn->prepare(
            'INSERT INTO document_requests
                (barangay_id, resident_id, requestor_name, document_type, purpose, notes, status)
             VALUES (?,?,?,?,?,?,?)'
        );
        $status = 'Pending';
        $stmt->bind_param('iisssss', $bid, $rid, $requestor_name, $document_type, $purpose, $notes, $status);

        if ($stmt->execute()) {
            $stmt->close();
            log_access('document_requested', 'document_request', (int)$conn->insert_id);
            redirect(url('resident/document_request.php?saved=1'));
        }
        $stmt->close();
        $error = 'We could not save your request right now. Please try again.';
    }
}

 
$types = [];
$t = $conn->query("SELECT DISTINCT document_type FROM document_requests
                    WHERE document_type <> '' ORDER BY document_type ASC");
if ($t) { while ($x = $t->fetch_assoc()) { $types[] = $x['document_type']; } }

/* Full history of this resident's filed requests (moved here from
 * resident_profile.php so the request page shows form + history together). */
$myDocs = [];
if ($rid !== null) {
    $d = $conn->prepare(
        'SELECT id, document_type, purpose, status, requested_at, released_at
           FROM document_requests WHERE resident_id=? ORDER BY id DESC'
    );
    if ($d) {
        $d->bind_param('i', $rid);
        $d->execute();
        $myDocs = $d->get_result()->fetch_all(MYSQLI_ASSOC);
        $d->close();
    }
}

$page_title = 'Request Document';
include BASE_PATH . '/partials/header.php';
?>

<?php if ($saved): ?>
    <div class="alert alert-success" role="status">
        Your document request has been submitted and is awaiting action from your barangay.
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>



<div class="section-head section-head--tight">
    <div>
        <h2>My Document Requests</h2>
        <p>Every request you have filed</p>
    </div>
    <div>
        <button class="btn" type="button" id="docNewBtn">+ New Request</button>
    </div>
</div>

<div class="card" id="docFormCard"<?= $error !== '' ? '' : ' hidden' ?>>
    <form method="post">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div>
                <label for="document_type">Document Type <span aria-hidden="true">*</span></label>
                <input id="document_type" name="document_type" required maxlength="100"
                       list="document-types" placeholder="e.g. Barangay Clearance">
                <datalist id="document-types">
                    <?php foreach ($types as $ty): ?>
                        <option value="<?= e($ty) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div>
                <label for="purpose">Purpose <span aria-hidden="true">*</span></label>
                <input id="purpose" name="purpose" required maxlength="255"
                       placeholder="Why you need this document">
            </div>

            <div class="full">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" rows="4"
                          placeholder="Optional details for the barangay office"></textarea>
            </div>
        </div>

        <br>
        <div class="toolbar">
            <button class="btn" type="submit">Submit Request</button>
            <a class="btn btn--ghost" href="<?= e(url('resident/resident_dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<script>
document.getElementById('docNewBtn').addEventListener('click', function () {
    var card = document.getElementById('docFormCard');
    card.hidden = !card.hidden;
});
</script>

<?php if ($myDocs): ?>
    <div class="card table-wrap">
        <table>
            <tr><th>Type</th><th>Purpose</th><th>Status</th><th>Requested</th><th>Action</th></tr>
            <?php foreach ($myDocs as $d): ?>
            <tr>
                <td><?= e($d['document_type']) ?></td>
                <td><?= e($d['purpose']) ?></td>
                <td><span class="badge"><?= e($d['status']) ?></span></td>
                <td><?= e($d['requested_at'] ? date('M j, Y', strtotime($d['requested_at'])) : '-') ?></td>
                <td>
                    <?php if ($d['status'] === 'Released'): ?>
                        <a class="btn btn--ghost" href="<?= e(url('pages/document_print.php?id=' . (int)$d['id'])) ?>"
                           target="_blank" rel="noopener">View / Print</a>
                    <?php else: ?>
                        <span class="muted-meta">Available once released</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php else: ?>
    <div class="card">
        <p>You have not filed any document requests yet.</p>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/partials/footer.php'; ?>
