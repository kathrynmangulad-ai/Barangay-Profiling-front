<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();
$page_title = 'Users';

$notice       = '';
$reset_link   = '';
$create_error = '';





$tabs = [
    'approved'  => ['label' => 'All Approved',     'role' => null,        'status' => ['active']],
    'admin'     => ['label' => 'Administrators',   'role' => 'admin',     'status' => ['active']],
    'secretary' => ['label' => 'Secretaries',      'role' => 'secretary', 'status' => ['active']],
    'resident'  => ['label' => 'Residents',        'role' => 'resident',  'status' => ['active']],
    'pending'   => ['label' => 'Pending Approval', 'role' => null,        'status' => ['pending']],
    'blocked'   => ['label' => 'Not Approved',     'role' => null,        'status' => ['rejected', 'suspended']],
    'deleted'   => ['label' => 'Deleted',          'role' => null,        'status' => [], 'deleted' => true],
];





if (isset($_GET['action'], $_GET['id'])) {
    csrf_verify_get();

    $uid    = (int)$_GET['id'];
    $action = $_GET['action'];

    $status_for = [
        'approve'  => 'active',
        'activate' => 'active',
        'reject'   => 'rejected',
        'suspend'  => 'suspended',
    ];

    if (isset($status_for[$action])) {
        $new  = $status_for[$action];
        $stmt = $conn->prepare('UPDATE users SET status=? WHERE id=?');
        $stmt->bind_param('si', $new, $uid);
        $stmt->execute();
        $stmt->close();

        





        $land = 'approved';
        if ($action === 'approve' || $action === 'activate') {
            if ($rq = $conn->prepare('SELECT role FROM users WHERE id=? LIMIT 1')) {
                $rq->bind_param('i', $uid);
                $rq->execute();
                $rrow = $rq->get_result()->fetch_assoc();
                $rq->close();
                $role_after = (string)($rrow['role'] ?? '');
                if (isset($tabs[$role_after]) && $role_after !== 'approved') {
                    $land = $role_after;
                }
            }
        }

        log_access('user_status_' . $action, 'user', $uid);
        redirect(url('admin/users.php?notice=' . rawurlencode($action) . '&tab=' . rawurlencode($land)));
    }

    if ($action === 'restore') {
        // Bring a soft-deleted account back by clearing its deletion stamp.
        $stmt = $conn->prepare('UPDATE users SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
        log_access('user_restored', 'user', $uid);
        redirect(url('admin/users.php?notice=restored&tab=deleted'));
    }

    if ($action === 'reset') {
        $tab_back = (string)($_GET['tab'] ?? 'approved');

        // Fetch the target user's email + name so we can email the link.
        $email_to = '';
        $name_to  = '';
        if ($uq = $conn->prepare('SELECT email, full_name FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1')) {
            $uq->bind_param('i', $uid);
            $uq->execute();
            $urow = $uq->get_result()->fetch_assoc();
            $uq->close();
            if ($urow) {
                $email_to = trim((string)($urow['email'] ?? ''));
                $name_to  = (string)($urow['full_name'] ?? '');
            }
        }

        // Issue a fresh single-use token (invalidates any previous one).
        $admin_id = (int)$_SESSION['user_id'];
        $plain = reset_issue_token($conn, $uid, $admin_id);

        if ($plain === '') {
            log_access('user_reset_link_failed', 'user', $uid, 'denied');
            redirect(url('admin/users.php?notice=reset_failed&tab=' . rawurlencode($tab_back)));
        }

        $hasEmail  = ($email_to !== '' && filter_var($email_to, FILTER_VALIDATE_EMAIL));
        $canMail   = $hasEmail && mail_is_configured();

        // Preferred path: email the reset link straight to the user.
        if ($canMail) {
            $url    = mail_build_reset_url($plain);
            $mailed = mail_send_reset($email_to, $name_to, $url);
            if ($mailed) {
                log_access('user_reset_link_emailed', 'user', $uid);
                redirect(url('admin/users.php?notice=reset_sent&tab=' . rawurlencode($tab_back)));
            }
            // Send failed - fall through to showing the link so the admin can
            // still deliver it manually.
            log_access('user_reset_link_email_failed', 'user', $uid, 'denied');
            $notice = 'Could not email the reset link (mail server error). '
                    . 'Copy the one-time link below and give it to the user instead.';
        } elseif ($hasEmail && !mail_is_configured()) {
            $notice = 'Email is not configured, so the link was not sent. '
                    . 'Copy the one-time link below and give it to the user instead.';
        } else {
            $notice = 'This account has no email address on file, so the link could not be sent. '
                    . 'Copy the one-time link below and give it to the user instead.';
        }

        // Fallback: show the one-time link on the page (previous behaviour).
        log_access('user_reset_link_shown', 'user', $uid);
        $reset_link = 'reset_password.php?token=' . $plain;
    }
}

$notices = [
    'approve'  => 'Account approved - the user can now sign in.',
    'activate' => 'Account reactivated - the user can sign in again.',
    'reject'   => 'Registration rejected - the account cannot sign in.',
    'suspend'  => 'Account suspended - the user has been signed out.',
    'created'  => 'Account created - the user can sign in right away.',
    'restored' => 'Account restored - the user is back in its category.',
    'reset_sent'   => 'Password-reset link emailed to the user. It expires in 1 hour and can be used once.',
    'reset_failed' => 'Could not generate a reset link right now. Please try again.',
];
if (isset($_GET['notice'])) {
    $notice = $notices[$_GET['notice']] ?? '';
}















 
$tab_key = (string)($_GET['tab'] ?? 'approved');
if (!isset($tabs[$tab_key])) { $tab_key = 'approved'; }

 
$tab_counts = array_fill_keys(array_keys($tabs), 0);
if ($cq = $conn->query('SELECT role, status, COUNT(*) c FROM users WHERE deleted_at IS NULL GROUP BY role, status')) {
    while ($cr = $cq->fetch_assoc()) {
        foreach ($tabs as $key => $def) {
            if ($def['role'] !== null && $def['role'] !== $cr['role']) { continue; }
            if (!in_array($cr['status'], $def['status'], true))            { continue; }
            $tab_counts[$key] += (int)$cr['c'];
        }
    }
    $cq->close();
}

// The signed-in administrator is excluded from both the Administrators
// list and the "All Approved" list below, so keep the tab badges
// consistent with the rows actually shown.
$tab_counts['admin']    = max(0, $tab_counts['admin'] - 1);
$tab_counts['approved'] = max(0, $tab_counts['approved'] - 1);

// The Deleted tab counts soft-deleted accounts (any status), excluding the
// signed-in admin's own id for safety/consistency.
if ($dq = $conn->prepare('SELECT COUNT(*) c FROM users WHERE deleted_at IS NOT NULL AND id <> ?')) {
    $self_id = (int)$_SESSION['user_id'];
    $dq->bind_param('i', $self_id);
    $dq->execute();
    $tab_counts['deleted'] = (int)($dq->get_result()->fetch_assoc()['c'] ?? 0);
    $dq->close();
}







$def     = $tabs[$tab_key];
$role_v  = $def['role'];
$status_v = $def['status'];

$where  = ($role_v === null) ? '' : ' AND u.role = ?';
$params = ($role_v === null) ? [] : [$role_v];
$types  = ($role_v === null) ? '' : 's';

// Never list the signed-in account itself in the Administrators tab or
// the combined "All Approved" tab.
if ($tab_key === 'admin' || $tab_key === 'approved') {
    $where  .= ' AND u.id <> ?';
    $params[] = (int)$_SESSION['user_id'];
    $types  .= 'i';
}




$search_v = trim($_GET['q'] ?? $_GET['search'] ?? '');

// Text search filter (all tabs).
if ($search_v !== '') {
    $like = '%' . $search_v . '%';
    $where  .= ' AND (u.username LIKE ? OR u.full_name LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $types  .= 'ss';
}


$is_deleted_tab = !empty($def['deleted']);

if ($is_deleted_tab) {
    // Deleted tab: list soft-deleted accounts of any status/role.
    $deleted_where = ' AND u.deleted_at IS NOT NULL';
} else {
    $status_sql = "'" . implode("','", array_map('addslashes', $status_v)) . "'";
    $deleted_where = " AND u.deleted_at IS NULL AND u.status IN ($status_sql)";
}

$rows = $conn->prepare(
    "SELECT u.*, b.barangay_name
       FROM users u
       LEFT JOIN barangays b ON b.id = u.barangay_id
      WHERE 1=1$deleted_where$where
      ORDER BY u.id"
);
if ($types !== '') { $rows->bind_param($types, ...$params); }
$rows->execute();
$result = $rows->get_result();



$pending = [];






if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $allowed_create = in_array($tab_key, ['admin', 'secretary'], true);
    if (!$allowed_create) {
        redirect(url('admin/users.php?tab=' . rawurlencode($tab_key)));
    }

    // The role is fixed by the tab the create form lives on: the admin
    // tab always creates admins, the secretary tab always secretaries.
    // ($_POST['role'] is deliberately ignored so it cannot be tampered with.)
    $role = ($tab_key === 'admin') ? 'admin' : 'secretary';

    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = (string)($_POST['password'] ?? '');
    $bid = ($role === 'admin') ? null : (int)($_POST['barangay_id'] ?? 0);
    // Email is optional; store NULL when left blank.
    $email_val = ($email === '') ? null : $email;

    // Validate required fields.
    if ($username === '' || $full_name === '' || $password === '') {
        $create_error = 'Username, full name and password are all required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $create_error = 'Please enter a valid email address.';
    } elseif ($role === 'secretary' && $bid <= 0) {
        $create_error = 'Please select a barangay for the secretary.';
    } else {
        // Reject duplicate usernames/emails before attempting the insert so
        // we can show a friendly message instead of a database error.
        $dup = $conn->prepare('SELECT username, email FROM users WHERE deleted_at IS NULL AND (username=? OR (email IS NOT NULL AND email=?)) LIMIT 1');
        $dup->bind_param('ss', $username, $email);
        $dup->execute();
        $exists = $dup->get_result()->fetch_assoc();
        $dup->close();

        if ($exists && strcasecmp((string)$exists['username'], $username) === 0) {
            $create_error = 'That username is already taken. Please choose another.';
        } elseif ($exists) {
            $create_error = 'That email address is already in use. Please choose another.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s = $conn->prepare('INSERT INTO users(username,email,password,full_name,role,barangay_id,status) VALUES(?,?,?,?,?,?,\'active\')');
            $s->bind_param('sssssi', $username, $email_val, $hash, $full_name, $role, $bid);
            if ($s->execute()) {
                $s->close();
                log_access('user_create', 'user', $conn->insert_id);
                redirect(url('admin/users.php?tab=' . rawurlencode($tab_key) . '&notice=created'));
            }
            $create_error = 'Could not create the account: ' . $conn->error;
            $s->close();
        }
    }
}
include BASE_PATH . '/partials/header.php';?>


<?php if ($reset_link !== ''): ?>
<div class="alert alert-danger" role="alert">
    <strong>One-time password reset link</strong> - copy it now, it will not be shown again.
    Give this link to the user; it expires in 1 hour and can be used only once.<br>
    <code style="word-break:break-all"><?= e($reset_link) ?></code>
</div>
<?php endif; ?>

<?php if ($notice !== ''): ?>
<div class="alert <?= $reset_link !== '' ? 'alert-danger' : 'alert-success' ?>" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<?php if ($create_error !== ''): ?>
<div class="alert alert-danger" role="alert"><?= e($create_error) ?></div>
<?php endif; ?>






<div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><nav class="tabs" style="flex:1;flex-wrap:wrap" aria-label="User categories">
    <?php foreach ($tabs as $key => $t): ?>
    <a class="<?= $key === $tab_key ? 'active' : '' ?>"
       href="<?= e(url('admin/users.php?tab=' . rawurlencode($key))) ?>"
       <?= $key === $tab_key ? 'aria-current="page"' : '' ?>>
        <?= e($t['label']) ?> (<?= (int)$tab_counts[$key] ?>)
    </a>
    <?php endforeach; ?>
</nav>
<?php if (in_array($tab_key, ['admin', 'secretary'], true)): ?>
<button type="button" class="btn" data-toggle="#create-user-form">Create User</button>
<?php endif; ?>
</div><!-- /.toolbar -->

<?php if ($tab_key === 'pending'): ?>
<p class="muted-meta" style="margin-top:-6px">
    These accounts are awaiting a decision and cannot sign in yet.
    Approving one moves it straight into its role category.
</p>
<?php endif; ?>

<?php






$show_create_form = in_array($tab_key, ['admin', 'secretary'], true); $auto_pw = bin2hex(random_bytes(8));
$bgq = null;
if ($show_create_form && $tab_key === 'secretary') {
    $bgq = $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name ASC');
    $page_scripts = ['assets/js/barangay_filter.js'];
}
if ($show_create_form): ?>
<div class="card create-form" id="create-user-form"<?= $create_error === '' ? ' hidden' : '' ?>><form method="post"><?= csrf_field() ?>
    <div class="form-grid"><div><label>
    Username</label><input name="username" required></div>
    <div><label>Password (auto-generated)</label>
    <input type="text" name="password" value="<?= e($auto_pw) ?>" readonly required></div>
    <div><label>Full Name</label>
    <input name="full_name" required></div>
    <div><label>Email (optional)</label>
    <input type="email" name="email" placeholder="name@example.com"></div>
    <div><label>Role</label>
    <input type="text" value="<?= $tab_key === 'admin' ? 'Admin' : 'Secretary' ?>" readonly aria-readonly="true" title="Role is fixed for this tab" style="cursor:default">
    </div>
    <?php if ($tab_key === 'secretary' && $bgq): ?>
    <div class="form-group">
        <label>Barangay</label>
        <select name="barangay_id" class="brgy-select" required>
            <option value="">Select barangay</option>
            <?php while ($bg = $bgq->fetch_assoc()): ?>
            <option value="<?= (int)$bg['id'] ?>"><?= e($bg['barangay_name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <?php endif; ?>
    </div>
    <div class="form-actions" style="margin-top:12px;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Create <?= $tab_key === 'admin' ? 'Administrator' : 'Secretary' ?></button>
        <button type="button" class="btn-cancel" data-toggle="#create-user-form">Cancel</button>
    </div>
    </form></div>
<?php endif; ?>

<?php if ($search_v !== '' && $tab_key !== 'secretary'): ?>
<p class="muted-meta" style="margin:6px 0 12px 0">
    Showing results for &ldquo;<?= e($search_v) ?>&rdquo; in <?= e($tabs[$tab_key]['label']) ?>.
    <a href="<?= e(url('admin/users.php?tab=' . rawurlencode($tab_key))) ?>" class="btn-link">Clear</a>
</p>
<?php endif; ?>

<div class="card table-wrap">
    <table>
        <tr>
            <th>Username</th><th>Name</th>
            <th>Role</th><th>Status</th><th>Barangays</th> <th>Action</th>
        </tr>
        <?php if (!$result->num_rows): ?>
        <tr><td colspan="6" class="muted-meta">No accounts in this category.</td></tr>
        <?php endif; ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
            <td><?= e($r['username']) ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['role']) ?></td>
            <td><span class="badge badge--<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            <td><?= e($r['barangay_name'] ?? 'All Barangays') ?></td>
            <td>
                <?php if ($is_deleted_tab): ?>
                    <a class="btn-approve" href="<?= e(url('admin/users.php?action=restore&id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>"
                       data-confirm="Restore this account? It will return to its role category and the user can sign in again."
                       data-confirm-title="Restore account">Restore</a>
                <?php else: ?>
                <?php if ($r['status'] === 'pending'): ?>
                    <a class="btn-approve" href="<?= e(url('admin/users.php?action=approve&id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>">Approve</a>
                    <a class="btn-delete" href="<?= e(url('admin/users.php?action=reject&id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>"
                       data-confirm="Reject this registration? The account will not be able to sign in."
                       data-confirm-title="Reject registration">Reject</a>
                <?php elseif ($r['status'] === 'active'): ?>
                    <a class="btn-cancel" href="<?= e(url('admin/users.php?action=suspend&id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>"
                       data-confirm="Suspend this account? The user will be signed out and cannot sign in until reactivated."
                       data-confirm-title="Suspend account">Suspend</a>
                <?php else: ?>
                    <a class="btn-approve" href="<?= e(url('admin/users.php?action=activate&id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>">Activate</a>
                <?php endif; ?>

                <?php if (in_array($tab_key, ['admin', 'secretary'], true)): ?>
                <a class="btn-cancel" href="<?= e(url('admin/users.php?action=reset&id=' . (int)$r['id'] . '&tab=' . rawurlencode($tab_key) . '&token=' . csrf_token())) ?>"
                   data-confirm="Email a one-time password reset link to this user? Any previous link will stop working. If the account has no email, the link will be shown here so you can share it manually."
                   data-confirm-title="Send reset link">Reset link</a>
                <?php endif; ?>

                <?php if ($tab_key !== 'resident'): ?><a href="<?= e(url('admin/user_edit.php?id=' . (int)$r['id'])) ?>" class="btn-edit">Edit</a><?php endif; ?>

                <a href="<?= e(url('actions/user_delete.php?id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>"
                   class="btn-delete"
                   data-confirm="Delete this user account? It will be moved to the Deleted tab and can be restored later."
                   data-confirm-title="Delete user">
                    Delete
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php include BASE_PATH . '/partials/footer.php';
