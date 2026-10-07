<?php

















require_once __DIR__ . '/../config/config.php';
require_role(['admin', 'secretary']);

$page_title = 'Resident Accounts';
$page_crumb = 'Resident Accounts';

$myBrgy = current_barangay_id();



$viewBrgy = $myBrgy;
if (is_admin() && isset($_GET['barangay_id']) && (int)$_GET['barangay_id'] > 0) {
    $viewBrgy = (int)$_GET['barangay_id'];
}
if ($viewBrgy === null) {
    deny_access('Your account is not assigned to a barangay yet.');
}

$notice = '';
$reset_link = '';

if (isset($_GET['action'], $_GET['id'])) {
    csrf_verify_get();

    $uid    = (int)$_GET['id'];
    $action = (string)$_GET['action'];

    

    $t = $conn->prepare(
        "SELECT id, username, full_name, role, barangay_id, status
           FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1"
    );
    $t->bind_param('i', $uid);
    $t->execute();
    $target = $t->get_result()->fetch_assoc();
    $t->close();

    if (!$target) {
        deny_access('Account not found.');
    }

    


    if ((int)$target['barangay_id'] !== (int)$viewBrgy || $target['role'] !== 'resident') {
        log_access('resident_account_denied:' . $action, 'user', $uid, 'denied');
        deny_access('You can only manage resident accounts in your own barangay.');
    }

    $status_for = [
        'approve'  => 'active',
        'activate' => 'active',
        'reject'   => 'rejected',
        'suspend'  => 'suspended',
    ];

    if (isset($status_for[$action])) {
        $new = $status_for[$action];
        

        $targetRole   = (string)$target['role'];
        $targetBrgyId = (int)$target['barangay_id'];

        $s = $conn->prepare('UPDATE users SET status=? WHERE id=? AND role=? AND barangay_id=?');
        if ($s === false) { deny_access('Could not update the account. Please try again.'); }

        



        $s->bind_param('sisi', $new, $uid, $targetRole, $targetBrgyId);
        $ran = $s->execute();
        $ok  = $ran ? ($s->affected_rows > 0) : false;
        $dbErr = $ran ? '' : $s->error;
        $s->close();

        log_access('resident_' . $action, 'user', $uid);

        if (!$ran) {
            

            log_access('resident_update_failed:' . $dbErr, 'user', $uid, 'denied');
            redirect(url('secretary/resident_accounts.php?notice=error'));
        }

        redirect(url('secretary/resident_accounts.php?notice=' . rawurlencode($action)
               . ($ok ? '' : '&blocked=1')));
    }

    if ($action === 'reset') {
        

        $plain     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plain);

        $inv = $conn->prepare('UPDATE password_reset_tokens SET used_at=NOW()
                                WHERE user_id=? AND used_at IS NULL');
        $inv->bind_param('i', $uid);
        $inv->execute();
        $inv->close();

        $ins = $conn->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_by)
             VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR),?)'
        );
        $actor = (int)current_user()['id'];
        $ins->bind_param('isi', $uid, $tokenHash, $actor);
        if ($ins->execute()) {
            $reset_link = 'reset_password.php?token=' . $plain;
        }
        $ins->close();

        log_access('resident_reset_link', 'user', $uid);
    }
}

$notices = [
    'approve'  => 'Resident account approved - the resident can now sign in.',
    'activate' => 'Resident account reactivated.',
    'reject'   => 'Registration rejected - that account cannot sign in.',
    'suspend'  => 'Resident account suspended.',
    'error'    => 'The account could not be updated. Check the access log for details.',
];
if (isset($_GET['notice'])) {
    $notice = $notices[$_GET['notice']] ?? '';
}
if (isset($_GET['blocked'])) {
    $notice = 'That account could not be changed (it may be staff, or belong to another barangay).';
}
 
$pending = [];
$pq = $conn->prepare(
    "SELECT u.id, u.username, u.full_name, u.created_at,
            r.id AS resident_id, r.last_name, r.first_name, r.middle_name, r.age
       FROM users u
       LEFT JOIN residents r ON r.user_id = u.id
      WHERE u.deleted_at IS NULL AND u.status = 'pending' AND u.role = 'resident' AND u.barangay_id = ?
   ORDER BY u.created_at ASC, u.id ASC"
);
$pq->bind_param('i', $viewBrgy);
$pq->execute();
$pending = $pq->get_result()->fetch_all(MYSQLI_ASSOC);
$pq->close();





$accounts = [];
$aq = $conn->prepare(
    "SELECT u.id, u.username, u.full_name, u.status, u.created_at,
            r.id AS resident_id, r.last_name, r.first_name, r.middle_name, r.age
       FROM users u
       LEFT JOIN residents r ON r.user_id = u.id
      WHERE u.deleted_at IS NULL AND u.role = 'resident' AND u.barangay_id = ? AND u.status <> 'pending'
   ORDER BY u.created_at DESC, u.id DESC"
);
$aq->bind_param('i', $viewBrgy);
$aq->execute();
$accounts = $aq->get_result()->fetch_all(MYSQLI_ASSOC);
$aq->close();

$brgyName = '';
if ($b = $conn->prepare('SELECT barangay_name FROM barangays WHERE id=?')) {
    $b->bind_param('i', $viewBrgy);
    $b->execute();
    $br = $b->get_result()->fetch_assoc();
    $brgyName = $br['barangay_name'] ?? '';
    $b->close();
}

 
$allBarangays = null;
if (is_admin()) {
    $allBarangays = $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name');
}

include BASE_PATH . '/partials/header.php';
?>


<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<?php if ($reset_link !== ''): ?>
    <div class="alert alert-success" role="status">
        <strong>One-time password reset link</strong> &mdash; copy it now, it will not be shown again:<br>
        <code style="word-break:break-all"><?= e($reset_link) ?></code>
    </div>
<?php endif; ?>

<?php if ($allBarangays): ?>
<div class="toolbar">
    <form method="get">
        <label class="sr-only" for="barangay_id">Barangay</label>
        <select id="barangay_id" name="barangay_id">
            <?php while ($bb = $allBarangays->fetch_assoc()): ?>
                <option value="<?= (int)$bb['id'] ?>"<?= (int)$viewBrgy === (int)$bb['id'] ? ' selected' : '' ?>>
                    <?= e($bb['barangay_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button class="btn" type="submit">View</button>
    </form>
</div>
<?php endif; ?>

<?php if ($pending): ?>
<div class="card table-wrap">
    <h3 style="padding:12px 14px;margin:0">Awaiting your approval</h3>
    <table>
        <tr><th>Username</th><th>Resident Name</th><th>Registered</th><th>Decision</th></tr>
        <?php foreach ($pending as $p): ?>
        <tr>
            <td><?= e($p['username']) ?></td>
            <td>
                <?= e(trim(($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''), ', ')) ?>
                <?php if (empty($p['resident_id'])): ?>
                    <br><span class="muted-meta">No linked resident record yet</span>
                <?php elseif ((int)($p['age'] ?? 0) === 0): ?>
                    <br><span class="muted-meta">Details still need verification</span>
                <?php endif; ?>
            </td>
            <td><?= e($p['created_at'] ? date('M j, Y', strtotime($p['created_at'])) : '-') ?></td>
            <td>
                <a class="btn-approve" href="<?= e(url('secretary/resident_accounts.php?action=approve&id=' . (int)$p['id'] . '&token=' . csrf_token())) ?>">Approve</a>
                <a class="btn-delete" href="<?= e(url('secretary/resident_accounts.php?action=reject&id=' . (int)$p['id'] . '&token=' . csrf_token())) ?>"
                   data-confirm="Reject this registration? The account will not be able to sign in."
                   data-confirm-title="Reject registration">Reject</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="card table-wrap">
    <h3 style="padding:12px 14px;margin:0">Resident accounts</h3>
    <table>
        <tr><th>Username</th><th>Resident Name</th><th>Status</th><th>Registered</th><th>Action</th></tr>
        <?php if (!$accounts): ?>
            <tr><td colspan="5" style="text-align:center">No resident accounts in this barangay yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($accounts as $a): ?>
        <tr>
            <td><?= e($a['username']) ?></td>
            <td><?= e(trim(($a['last_name'] ?? '') . ', ' . ($a['first_name'] ?? '') . ' ' . ($a['middle_name'] ?? ''), ', ')) ?></td>
            <td><span class="badge badge--<?= e($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span></td>
            <td><?= e($a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '-') ?></td>
            <td>
                <?php if ($a['status'] === 'pending'): ?>
                    <a class="btn-approve" href="<?= e(url('secretary/resident_accounts.php?action=approve&id=' . (int)$a['id'] . '&token=' . csrf_token())) ?>">Approve</a>
                <?php elseif ($a['status'] === 'active'): ?>
                    <a class="btn-cancel" href="<?= e(url('secretary/resident_accounts.php?action=suspend&id=' . (int)$a['id'] . '&token=' . csrf_token())) ?>"
                       data-confirm="Suspend this account? The resident will not be able to sign in."
                       data-confirm-title="Suspend account">Suspend</a>
                <?php else: ?>
                    <a class="btn-approve" href="<?= e(url('secretary/resident_accounts.php?action=activate&id=' . (int)$a['id'] . '&token=' . csrf_token())) ?>">Activate</a>
                <?php endif; ?>

                <a class="btn-cancel" href="<?= e(url('secretary/resident_accounts.php?action=reset&id=' . (int)$a['id'] . '&token=' . csrf_token())) ?>"
                   data-confirm="Generate a one-time password reset link for this resident? Any previous link will stop working."
                   data-confirm-title="Generate reset link">Reset link</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php include BASE_PATH . '/partials/footer.php'; ?>

