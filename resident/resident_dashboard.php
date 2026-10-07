<?php










require_once __DIR__ . '/../config/config.php';
require_role(['resident']);

$page_title = 'My Dashboard';
$page_crumb = 'My Dashboard';

$me       = current_user();
$rid      = current_resident_id();
$fullName = $me['full_name'];

 
$profile = null;
if ($rid !== null) {
    $ps = $conn->prepare(
        'SELECT r.*, b.barangay_name
           FROM residents r
           LEFT JOIN barangays b ON b.id = r.barangay_id
          WHERE r.user_id = ? AND r.deleted_at IS NULL LIMIT 1'
    );
    $uid = (int)$me['id'];
    $ps->bind_param('i', $uid);
    $ps->execute();
    $profile = $ps->get_result()->fetch_assoc();
    $ps->close();
}

 
$docTotal = 0; $docPending = 0; $blotTotal = 0;

if ($rid !== null) {
    $c = $conn->prepare('SELECT COUNT(*) n FROM document_requests WHERE resident_id=?');
    $c->bind_param('i', $rid);
    $c->execute(); $docTotal = (int)$c->get_result()->fetch_assoc()['n']; $c->close();

    $c = $conn->prepare("SELECT COUNT(*) n FROM document_requests
                          WHERE resident_id=? AND status IN ('Pending','Processing')");
    $c->bind_param('i', $rid);
    $c->execute(); $docPending = (int)$c->get_result()->fetch_assoc()['n']; $c->close();

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

<div class="section-head">
    <div>
        <h2>My Records</h2>
        <p>Everything you have requested or filed</p>
    </div>
</div>

<div class="kpi-grid">
    <article class="kpi kpi--navy">
        <div class="kpi__top">
            <span class="kpi__icon" aria-hidden="true"><?= icon('file-text') ?></span>
            <?php if ($docPending > 0): ?>
                <span class="kpi__trend kpi__trend--up"><?= icon('clock', 'ico ico--xs') ?> <?= (int)$docPending ?> awaiting action</span>
            <?php else: ?>
                <span class="kpi__trend kpi__trend--muted"><?= icon('check-circle', 'ico ico--xs') ?> All processed</span>
            <?php endif; ?>
        </div>
        <p class="kpi__value"><?= number_format($docTotal) ?></p>
        <p class="kpi__title">My Document Requests</p>
        <p class="kpi__desc">Requests you have filed</p>
        <a class="kpi__link" href="<?= e(url('resident/document_request.php')) ?>">Request a document <?= icon('arrow-right', 'ico ico--xs') ?></a>
    </article>

    <article class="kpi kpi--coral">
        <div class="kpi__top">
            <span class="kpi__icon" aria-hidden="true"><?= icon('clipboard') ?></span>
        </div>
        <p class="kpi__value"><?= number_format($blotTotal) ?></p>
        <p class="kpi__title">My Blotter Reports</p>
        <p class="kpi__desc">Reports you have filed</p>
        <a class="kpi__link" href="<?= e(url('resident/blotter_request.php')) ?>">File a report <?= icon('arrow-right', 'ico ico--xs') ?></a>
    </article>

    <article class="kpi kpi--teal">
        <div class="kpi__top">
            <span class="kpi__icon" aria-hidden="true"><?= icon('user') ?></span>
        </div>
        <p class="kpi__value" style="font-size:1.1rem;line-height:1.35;padding-top:.5rem"><?= e(trim($profile['last_name'] . ', ' . $profile['first_name'] . ' ' . $profile['middle_name'], ', ')) ?></p>
        <p class="kpi__title">My Profile</p>
        <p class="kpi__desc"><?= e($profile['barangay_name'] ?? 'Barangay not set') ?></p>
        <a class="kpi__link" href="<?= e(url('resident/resident_profile.php')) ?>">View my profile <?= icon('arrow-right', 'ico ico--xs') ?></a>
    </article>
</div>

<div class="dash-grid">
    <section class="panel" aria-labelledby="mydocs-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="mydocs-heading">My Document Requests</h3>
                <p class="panel__sub">Your five most recent requests</p>
            </div>
            <a class="panel__link" href="<?= e(url('resident/document_request.php')) ?>">New request <?= icon('arrow-right', 'ico ico--xs') ?></a>
        </div>
        <?php if ($myDocs): ?>
            <ul class="stat-list">
                <?php foreach ($myDocs as $d): ?>
                    <li class="stat-row">
                        <span class="stat-row__label"><?= icon('file-text', 'ico ico--sm') ?> <?= e($d['document_type']) ?></span>
                        <span class="muted-meta">
                            <span class="badge"><?= e($d['status']) ?></span>
                            &middot; <?= e($d['requested_at'] ? date('M j, Y', strtotime($d['requested_at'])) : '') ?>
                            <?php if ($d['status'] === 'Released'): ?>
                                &middot; <a href="<?= e(url('pages/document_print.php?id=' . (int)$d['id'])) ?>"
                                      target="_blank" rel="noopener">View / Print</a>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">
                <span class="empty__ico" aria-hidden="true"><?= icon('inbox') ?></span>
                <p class="empty__title">No document requests yet</p>
                <p class="empty__text">When you request a document it will appear here with its current status.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="myblots-heading">
        <div class="panel__head">
            <div>
                <h3 class="panel__title" id="myblots-heading">My Blotter Reports</h3>
                <p class="panel__sub">Reports you filed with the barangay</p>
            </div>
            <a class="panel__link" href="<?= e(url('resident/blotter_request.php')) ?>">File a report <?= icon('arrow-right', 'ico ico--xs') ?></a>
        </div>
        <?php if ($myBlots): ?>
            <ul class="stat-list">
                <?php foreach ($myBlots as $b): ?>
                    <li class="stat-row">
                        <span class="stat-row__label"><?= icon('alert-triangle', 'ico ico--sm') ?> <?= e($b['incident_type']) ?></span>
                        <span class="muted-meta">
                            Entry #<?= e($b['blotter_no']) ?> &middot; <?= e($b['status']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">
                <span class="empty__ico" aria-hidden="true"><?= icon('clipboard') ?></span>
                <p class="empty__title">No blotter reports</p>
                <p class="empty__text">You have not filed any incident reports with your barangay.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php endif; ?>

<?php include BASE_PATH . '/partials/footer.php'; ?>

