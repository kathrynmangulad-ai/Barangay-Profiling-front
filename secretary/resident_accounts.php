<?php

















require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
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
        "SELECT u.id, u.username, u.full_name, u.role, u.barangay_id, u.status,
                u.email AS account_email, r.email AS resident_email, b.barangay_name
           FROM users u
           LEFT JOIN residents r ON r.user_id = u.id AND r.deleted_at IS NULL
           LEFT JOIN barangays b ON b.id = u.barangay_id
          WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1"
    );
    if ($t === false) { die("Prepare Error: " . $conn->error); }
    $t->bind_param('i', $uid);
    $t->execute();
    $target = $t->get_result()->fetch_assoc();
    $t->close();

    // Best email we have for this resident: account email, else profile email.
    $target_email = '';
    if ($target) {
        $target_email = trim((string)($target['account_email'] ?? '')) !== ''
            ? (string)$target['account_email']
            : (string)($target['resident_email'] ?? '');
    }

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

        // On approval/activation, notify the resident by email (if we have one).
        $mail_flag = '';
        if ($ok && $new === 'active' && in_array($action, ['approve', 'activate'], true)) {
            if ($target_email !== '' && mail_is_configured()) {
                $sent = mail_send_account_approved(
                    $target_email,
                    (string)$target['full_name'],
                    (string)$target['username'],
                    (string)($target['barangay_name'] ?? '')
                );
                $mail_flag = $sent ? '&mailed=1' : '&mailed=0';
                log_access($sent ? 'resident_approved_emailed' : 'resident_approved_email_failed', 'user', $uid, $sent ? 'allowed' : 'denied');
            } else {
                $mail_flag = '&mailed=0';
            }
        }

        redirect(url('secretary/resident_accounts.php?notice=' . rawurlencode($action)
               . ($ok ? '' : '&blocked=1') . $mail_flag));
    }

    if ($action === 'reset') {
        // Issue a fresh single-use token (invalidates any previous one).
        $actor = (int)current_user()['id'];
        $plain = reset_issue_token($conn, $uid, $actor);

        if ($plain === '') {
            log_access('resident_reset_link_failed', 'user', $uid, 'denied');
            redirect(url('secretary/resident_accounts.php?notice=error'));
        }

        // Prefer to email the reset link to the resident; fall back to showing
        // the one-time link on screen if there's no email / mail is unconfigured
        // / the send fails.
        if ($target_email !== '' && mail_is_configured()) {
            $url    = mail_build_reset_url($plain);
            $mailed = mail_send_reset($target_email, (string)$target['full_name'], $url);
            if ($mailed) {
                log_access('resident_reset_link_emailed', 'user', $uid);
                redirect(url('secretary/resident_accounts.php?notice=reset_sent'));
            }
            log_access('resident_reset_link_email_failed', 'user', $uid, 'denied');
        }

        // Fallback: show the one-time link on the page.
        log_access('resident_reset_link_shown', 'user', $uid);
        $reset_link = url('auth/reset_password.php?token=' . $plain);
    }
}

$notices = [
    'approve'    => 'Resident account approved - the resident can now sign in.',
    'activate'   => 'Resident account reactivated.',
    'reject'     => 'Registration rejected - that account cannot sign in.',
    'suspend'    => 'Resident account suspended.',
    'reset_sent' => 'Password-reset link emailed to the resident. It expires in 1 hour and can be used once.',
    'error'      => 'The account could not be updated. Check the access log for details.',
];
if (isset($_GET['notice'])) {
    $notice = $notices[$_GET['notice']] ?? '';
    // Append email status to the approve/activate notice.
    if (in_array($_GET['notice'], ['approve', 'activate'], true) && isset($_GET['mailed'])) {
        $notice .= ($_GET['mailed'] === '1')
            ? ' A notification email was sent to the resident.'
            : ' (No email on file or email could not be sent.)';
    }
}
if (isset($_GET['blocked'])) {
    $notice = 'That account could not be changed (it may be staff, or belong to another barangay).';
}
 
$pending = [];
$pq = $conn->prepare(
    "SELECT u.id, u.username, u.full_name, u.created_at, u.email AS account_email,
            r.id AS resident_id, r.last_name, r.first_name, r.middle_name, r.age, r.email AS resident_email, r.photo
       FROM users u
       LEFT JOIN residents r ON r.user_id = u.id
      WHERE u.deleted_at IS NULL AND u.status = 'pending' AND u.role = 'resident' AND u.barangay_id = ?
   ORDER BY u.created_at ASC, u.id ASC"
);
if ($pq === false) { die("Prepare Error: " . $conn->error); }
$pq->bind_param('i', $viewBrgy);
$pq->execute();
$pending = $pq->get_result()->fetch_all(MYSQLI_ASSOC);
$pq->close();





$accounts = [];
$aq = $conn->prepare(
    "SELECT u.id, u.username, u.full_name, u.status, u.created_at, u.email AS account_email,
            r.id AS resident_id, r.last_name, r.first_name, r.middle_name, r.age, r.email AS resident_email, r.photo
       FROM users u
       LEFT JOIN residents r ON r.user_id = u.id
      WHERE u.deleted_at IS NULL AND u.role = 'resident' AND u.barangay_id = ? AND u.status <> 'pending'
   ORDER BY u.created_at DESC, u.id DESC"
);
if ($aq === false) { die("Prepare Error: " . $conn->error); }
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
        <tr><th>Photo</th><th>Username</th><th>Resident Name</th><th>Email</th><th>Registered</th><th>Decision</th></tr>
        <?php foreach ($pending as $p): ?>
        <?php $p_email = trim((string)($p['account_email'] ?? '')) !== '' ? $p['account_email'] : ($p['resident_email'] ?? ''); ?>
        <?php $p_photo = trim((string)($p['photo'] ?? '')); ?>
        <tr>
            <td style="text-align:center"><?php if ($p_photo !== '' && @file_exists(BASE_PATH . '/' . $p_photo)): ?><img src="<?= e(url($p_photo)) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:50%;display:block;margin:0 auto"><?php else: ?><span class="muted-meta">&mdash;</span><?php endif; ?></td>
            <td><?= e($p['username']) ?></td>
            <td>
                <?= e(trim(($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? ''), ', ')) ?>
                <?php if (empty($p['resident_id'])): ?>
                    <br><span class="muted-meta">No linked resident record yet</span>
                <?php elseif ((int)($p['age'] ?? 0) === 0): ?>
                    <br><span class="muted-meta">Details still need verification</span>
                <?php endif; ?>
            </td>
            <td><?= $p_email !== '' ? e($p_email) : '<span class="muted-meta">&mdash;</span>' ?></td>
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
        <tr><th>Photo</th><th>Username</th><th>Resident Name</th><th>Email</th><th>Status</th><th>Registered</th><th>Action</th></tr>
        <?php if (!$accounts): ?>
            <tr><td colspan="7" style="text-align:center">No resident accounts in this barangay yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($accounts as $a): ?>
        <?php $a_email = trim((string)($a['account_email'] ?? '')) !== '' ? $a['account_email'] : ($a['resident_email'] ?? ''); ?>
        <?php $a_photo = trim((string)($a['photo'] ?? '')); ?>
        <tr>
            <td style="text-align:center"><?php if ($a_photo !== '' && @file_exists(BASE_PATH . '/' . $a_photo)): ?><img src="<?= e(url($a_photo)) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:50%;display:block;margin:0 auto"><?php else: ?><span class="muted-meta">&mdash;</span><?php endif; ?></td>
            <td><?= e($a['username']) ?></td>
            <td><?= e(trim(($a['last_name'] ?? '') . ', ' . ($a['first_name'] ?? '') . ' ' . ($a['middle_name'] ?? ''), ', ')) ?></td>
            <td><?= $a_email !== '' ? e($a_email) : '<span class="muted-meta">&mdash;</span>' ?></td>
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

