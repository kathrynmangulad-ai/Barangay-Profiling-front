<?php










require_once __DIR__ . '/../config/config.php';
require_role(['resident']);

$page_title = 'My Dashboard';
$page_crumb = 'My Dashboard';
$page_head  = false; // the page greets the resident in its own title row

$me       = current_user();
$rid      = current_resident_id();
$fullName = $me['full_name'];

 
$profile = null;
if ($rid !== null) {
    $ps = $conn->prepare(
        'SELECT r.*, b.barangay_name, hh.household_no AS hh_no
           FROM residents r
           LEFT JOIN barangays b ON b.id = r.barangay_id
           LEFT JOIN households hh ON hh.id = r.household_id AND hh.deleted_at IS NULL
          WHERE r.user_id = ? AND r.deleted_at IS NULL LIMIT 1'
    );
    $uid = (int)$me['id'];
    $ps->bind_param('i', $uid);
    $ps->execute();
    $profile = $ps->get_result()->fetch_assoc();
    $ps->close();
}

 
$docTotal = 0; $docPending = 0; $docReleased = 0; $blotTotal = 0;

if ($rid !== null) {
    $c = $conn->prepare('SELECT COUNT(*) n FROM document_requests WHERE resident_id=?');
    $c->bind_param('i', $rid);
    $c->execute(); $docTotal = (int)$c->get_result()->fetch_assoc()['n']; $c->close();

    $c = $conn->prepare("SELECT COUNT(*) n FROM document_requests
                          WHERE resident_id=? AND status IN ('Pending','Processing')");
    $c->bind_param('i', $rid);
    $c->execute(); $docPending = (int)$c->get_result()->fetch_assoc()['n']; $c->close();

    $c = $conn->prepare("SELECT COUNT(*) n FROM document_requests WHERE resident_id=? AND status='Released'");
    $c->bind_param('i', $rid);
    $c->execute(); $docReleased = (int)$c->get_result()->fetch_assoc()['n']; $c->close();

    $c = $conn->prepare('SELECT COUNT(*) n FROM blotter_records WHERE resident_id=?');
    $c->bind_param('i', $rid);
    $c->execute(); $blotTotal = (int)$c->get_result()->fetch_assoc()['n']; $c->close();
}

 
$myDocs = [];
$myBlots = [];
if ($rid !== null) {
    $d = $conn->prepare(
        'SELECT id, document_type, status, requested_at
           FROM document_requests WHERE resident_id=? ORDER BY id DESC LIMIT 5'
    );
    $d->bind_param('i', $rid);
    $d->execute();
    $myDocs = $d->get_result()->fetch_all(MYSQLI_ASSOC);
    $d->close();

    $b = $conn->prepare(
        'SELECT id, blotter_no, incident_type, status
           FROM blotter_records WHERE resident_id=? ORDER BY id DESC LIMIT 5'
    );
    $b->bind_param('i', $rid);
    $b->execute();
    $myBlots = $b->get_result()->fetch_all(MYSQLI_ASSOC);
    $b->close();
}

include BASE_PATH . '/partials/header.php';
?>
<?php if (!$profile): ?>
    <div class="alert alert-danger" role="alert">
        Your resident profile could not be loaded. Please contact your barangay secretary.
    </div>
<?php else: ?>

<?php
/* Small helpers for this page: first name for the greeting, initials for the
   photo fallback, and the pill tone for each blotter status. */
$first_name = trim((string)($profile['first_name'] ?? '')) ?: trim((string)$fullName);
$d_photo    = trim((string)($profile['photo'] ?? ''));
$d_hasPhoto = ($d_photo !== '' && @file_exists(BASE_PATH . '/' . $d_photo));
$d_initials = '';
foreach (preg_split('/\s+/', trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) as $part) {
    if ($part !== '' && preg_match('/\p{L}/u', $part, $m)) { $d_initials .= strtoupper($m[0]); }
}
$d_initials = substr($d_initials, 0, 2) ?: '?';
$blot_tone  = ['Open' => 'pending', 'In Progress' => 'processing', 'Resolved' => 'released', 'Cancelled' => 'cancelled'];
$d_contact  = trim((string)($profile['contact_no'] ?? ''));
$d_purok    = trim((string)($profile['address'] ?? ''));
$d_hh       = trim((string)($profile['hh_no'] ?? ($profile['household_no'] ?? '')));
?>
<div class="me">

<!-- ===================== Greeting and the two things a resident does ====== -->
<div class="dash-head">
    <div>
        <h1 class="page-head__title">Hello, <?= e($first_name) ?></h1>
        <p class="page-head__sub">Here is everything you have requested or filed with Barangay <?= e($profile['barangay_name'] ?? '') ?>.</p>
    </div>
    <div class="dash-head__filters">
        <a class="btn" href="<?= e(url('resident/document_request.php')) ?>"><?= icon('file-plus') ?> Request a document</a>
        <a class="btn btn--ghost" href="<?= e(url('resident/blotter_request.php')) ?>"><?= icon('alert-triangle') ?> File a blotter</a>
    </div>
</div>

<!-- ============================ Figures ================================== -->
<div class="stat-strip stat-strip--4">
    <a class="stat-tile kpi--navy" href="<?= e(url('resident/document_request.php')) ?>">
        <span class="stat-tile__label">Document requests</span>
        <span class="stat-tile__icon kpi__icon" aria-hidden="true"><?= icon('file-text') ?></span>
        <span class="stat-tile__value"><?= number_format($docTotal) ?></span>
    </a>
    <a class="stat-tile kpi--amber" href="<?= e(url('resident/document_request.php')) ?>">
        <span class="stat-tile__label">In progress</span>
        <span class="stat-tile__icon kpi__icon" aria-hidden="true"><?= icon('clock') ?></span>
        <span class="stat-tile__value"><?= number_format($docPending) ?></span>
    </a>
    <a class="stat-tile kpi--green" href="<?= e(url('resident/document_request.php')) ?>">
        <span class="stat-tile__label">Released</span>
        <span class="stat-tile__icon kpi__icon" aria-hidden="true"><?= icon('check-circle') ?></span>
        <span class="stat-tile__value"><?= number_format($docReleased) ?></span>
    </a>
    <a class="stat-tile kpi--coral" href="<?= e(url('resident/blotter_request.php')) ?>">
        <span class="stat-tile__label">Blotter reports</span>
        <span class="stat-tile__icon kpi__icon" aria-hidden="true"><?= icon('clipboard') ?></span>
        <span class="stat-tile__value"><?= number_format($blotTotal) ?></span>
    </a>
</div>

<!-- ===================== Recent requests, reports, and who you are ======== -->
<div class="me-board">
    <section class="panel" aria-labelledby="mydocs-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="mydocs-heading">My Document Requests</h3>
                <p class="panel__sub">Your five most recent requests</p>
            </div>
            <a class="panel__link" href="<?= e(url('resident/document_request.php')) ?>">View all <?= icon('arrow-right', 'ico ico--xs') ?></a>
        </div>
        <?php if ($myDocs): ?>
            <ul class="stat-list">
                <?php foreach ($myDocs as $d): ?>
                    <li class="stat-row">
                        <span class="cell-stack">
                            <span class="cell-main"><?= e($d['document_type']) ?></span>
                            <span class="cell-sub">Requested <?= e(fmt_date($d['requested_at'])) ?></span>
                        </span>
                        <span class="stat-row__end">
                            <?php if ($d['status'] === 'Released'): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('pages/document_print.php?id=' . (int)$d['id'])) ?>"><?= icon('printer') ?> View / Print</a>
                            <?php endif; ?>
                            <span class="badge badge--<?= e(strtolower((string)$d['status'])) ?>"><?= e($d['status']) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('file-text', 'No document requests yet', 'When you request a document it will appear here with its current status.',
                '<a class="btn" href="' . e(url('resident/document_request.php')) . '">' . icon('file-plus') . ' Request a document</a>') ?>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="myblots-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="myblots-heading">My Blotter Reports</h3>
                <p class="panel__sub">Reports you filed with the barangay</p>
            </div>
            <a class="panel__link" href="<?= e(url('resident/blotter_request.php')) ?>">View all <?= icon('arrow-right', 'ico ico--xs') ?></a>
        </div>
        <?php if ($myBlots): ?>
            <ul class="stat-list">
                <?php foreach ($myBlots as $b): ?>
                    <li class="stat-row">
                        <span class="cell-stack">
                            <span class="cell-main"><?= e(trim((string)$b['incident_type']) !== '' ? $b['incident_type'] : 'Incident') ?></span>
                            <span class="cell-sub">Entry #<?= e($b['blotter_no']) ?></span>
                        </span>
                        <span class="badge badge--<?= e($blot_tone[$b['status']] ?? 'neutral') ?>"><?= e($b['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('clipboard', 'No blotter reports', 'You have not filed any incident reports with your barangay.') ?>
        <?php endif; ?>
    </section>

    <aside class="card profile-card" aria-labelledby="me-name">
        <div class="profile-card__head">
            <?php if ($d_hasPhoto): ?>
                <img class="profile-card__photo" src="<?= e(url($d_photo)) ?>" alt="">
            <?php else: ?>
                <span class="profile-card__photo profile-card__photo--initials" aria-hidden="true"><?= e($d_initials) ?></span>
            <?php endif; ?>
            <h2 class="profile-card__name" id="me-name"><?= e(resident_name($profile)) ?></h2>
            <p class="profile-card__sub">Resident &middot; <?= e($profile['barangay_name'] ?? 'Barangay not set') ?></p>
        </div>
        <dl class="detail-list">
            <div><dt>Household</dt><dd><?= $d_hh !== '' ? e($d_hh) : '<span class="muted-meta">Not yet assigned</span>' ?></dd></div>
            <div><dt>Purok / Zone</dt><dd><?= $d_purok !== '' ? e($d_purok) : '<span class="muted-meta">&mdash;</span>' ?></dd></div>
            <div><dt>Contact</dt><dd><?= $d_contact !== '' ? e($d_contact) : '<span class="muted-meta">None on file</span>' ?></dd></div>
        </dl>
        <div class="profile-card__foot">
            <a class="btn btn--ghost btn--block" href="<?= e(url('resident/resident_profile.php')) ?>"><?= icon('user') ?> View my profile</a>
        </div>
    </aside>
</div>

</div>
<?php endif; ?>

<?php include BASE_PATH . '/partials/footer.php'; ?>
