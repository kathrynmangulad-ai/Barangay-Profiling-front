<?php









require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role(['resident']);

$page_title = 'My Profile';
$page_crumb = 'My Profile';

$uid = (int)current_user()['id'];

$profile = null;
$account_email = '';
$ps = $conn->prepare(
    'SELECT r.*, b.barangay_name, u.email AS account_email,
            h.household_no AS hh_no, h.head_resident_id AS hh_head_id
       FROM residents r
       LEFT JOIN barangays b ON b.id = r.barangay_id
       LEFT JOIN users u ON u.id = r.user_id
       LEFT JOIN households h ON h.id = r.household_id AND h.deleted_at IS NULL
      WHERE r.user_id = ? AND r.deleted_at IS NULL LIMIT 1'
);
$ps->bind_param('i', $uid);
$ps->execute();
$profile = $ps->get_result()->fetch_assoc();
$ps->close();
if ($profile) { $account_email = (string)($profile['account_email'] ?? ''); }

$rid = $profile['id'] ?? null;

$myBlots = [];

if ($rid !== null) {
    $b = $conn->prepare(
        'SELECT id, blotter_no, incident_type, place_of_incident, status, report_datetime
           FROM blotter_records WHERE resident_id=? ORDER BY id DESC'
    );
    $b->bind_param('i', $rid);
    $b->execute();
    $myBlots = $b->get_result()->fetch_all(MYSQLI_ASSOC);
    $b->close();
}

/* IP / 4Ps classification columns (added outside the base schema) — detect
 * whichever names exist (same candidates as the staff pages) so the display
 * never breaks on a missing column. The profile query is SELECT r.* so a
 * detected column is already present in $profile. */
$ip_col = null; $ip_int = false; $fp_col = null; $fp_int = false;
foreach (['is_ip','is_indigenous','indigenous'] as $cand) { $qc = $conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if ($qc) { if ($qc->num_rows > 0) { $cf = $qc->fetch_assoc(); $ip_col = $cand; $ip_int = (bool)preg_match('/int/i', (string)$cf['Type']); $qc->free(); break; } $qc->free(); } }
foreach (['is_4ps','is_fourps','fourps','pantawid'] as $cand) { $qc = $conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if ($qc) { if ($qc->num_rows > 0) { $cf = $qc->fetch_assoc(); $fp_col = $cand; $fp_int = (bool)preg_match('/int/i', (string)$cf['Type']); $qc->free(); break; } $qc->free(); } }

 
$ageKnown = ((int)($profile['age'] ?? 0)) > 0;












$error = '';
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile) {
    require_csrf();

     
    $photo = (string)$profile['photo'];

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = BASE_PATH . '/uploads/residents/';
        if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

        $extension = strtolower(pathinfo((string)$_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($extension, $allowed, true)) {
            $error = 'Invalid photo type. Allowed formats: JPG, JPEG, PNG, GIF.';
        } elseif ((int)$_FILES['photo']['size'] > 10 * 1024 * 1024) {
            $error = 'Photo is too large. Maximum size is 10MB.';
        } else {
            $new_filename = uniqid('resident_', true) . '.' . $extension;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_filename)) {
                $photo = 'uploads/residents/' . $new_filename;
            }
        }
    }

    if ($error === '') {
        $contact_no   = trim($_POST['contact_no'] ?? '');
        $occupation   = trim($_POST['occupation'] ?? '');
        $civil_status = trim($_POST['civil_status'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $email        = trim($_POST['email'] ?? '');

        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
            $error = 'Please enter a valid email address.';
        } elseif ($email !== '' && users_has_email_column($conn)) {
            $ck = $conn->prepare('SELECT id FROM users WHERE email=? AND id<>? AND deleted_at IS NULL LIMIT 1');
            if ($ck) {
                $ck->bind_param('si', $email, $uid);
                $ck->execute();
                $dup = (bool)$ck->get_result()->fetch_assoc();
                $ck->close();
                if ($dup) { $error = 'That email address is already registered. Please use another.'; }
            }
        }

        if ($error !== '') {
             
            $account_email = $email;
        } else {
        

        $u = $conn->prepare(
            'UPDATE residents
                SET contact_no = ?, occupation = ?, civil_status = ?, address = ?, photo = ?,
                    is_pwd = ?, is_student = ?
              WHERE user_id = ?'
        );
        



        $u_pwd     = isset($_POST['is_pwd']) ? 'Yes' : 'No';
        $u_student = isset($_POST['is_student']) ? 'Yes' : 'No';
        $u->bind_param('sssssssi', $contact_no, $occupation, $civil_status, $address, $photo, $u_pwd, $u_student, $uid);

        if ($u->execute()) {
            $u->close();

            // IP / 4Ps columns live outside the base schema — write them only
            // when the detected column exists on this install.
            // IP / 4Ps columns live outside the base schema — write them only
            // when the detected column exists on this install.
            $u_ip_val = isset($_POST['is_ip']) ? ($ip_int ? 1 : 'Yes') : ($ip_int ? 0 : 'No');
            $u_fp_val = isset($_POST['is_4ps']) ? ($fp_int ? 1 : 'Yes') : ($fp_int ? 0 : 'No');
            if ($ip_col !== null) {
                $uv = $u_ip_val;
                if ($cs = $conn->prepare("UPDATE residents SET `$ip_col`=? WHERE user_id=? AND deleted_at IS NULL")) {
                    $cs->bind_param(($ip_int ? 'i' : 's') . 'i', $uv, $uid);
                    $cs->execute();
                    $cs->close();
                }
            }
            if ($fp_col !== null) {
                $uv = $u_fp_val;
                if ($cs = $conn->prepare("UPDATE residents SET `$fp_col`=? WHERE user_id=? AND deleted_at IS NULL")) {
                    $cs->bind_param(($fp_int ? 'i' : 's') . 'i', $uv, $uid);
                    $cs->execute();
                    $cs->close();
                }
            }

            // IP / 4Ps columns live outside the base schema — write them only
            // when the detected column exists on this install.
            $u_ip_on  = isset($_POST['is_ip']);
            $u_fp_on  = isset($_POST['is_4ps']);
            $u_ip_val = $u_ip_on ? ($ip_int ? 1 : 'Yes') : ($ip_int ? 0 : 'No');
            $u_fp_val = $u_fp_on ? ($fp_int ? 1 : 'Yes') : ($fp_int ? 0 : 'No');
            if ($ip_col !== null || $fp_col !== null) {
                $setSql = ''; $setTypes = ''; $setVals = [];
                if ($ip_col !== null) { $setSql .= "`$ip_col`=?"; $setTypes .= $ip_int ? 'i' : 's'; $setVals[] = $u_ip_val; }
                if ($fp_col !== null) { $setSql .= ($setSql !== '' ? ',' : '') . "`$fp_col`=?"; $setTypes .= $fp_int ? 'i' : 's'; $setVals[] = $u_fp_val; }
                $setVals[] = $uid;
                if ($cs = $conn->prepare("UPDATE residents SET $setSql WHERE user_id=? AND deleted_at IS NULL")) {
                    $types = $setTypes . 'i';
                    $cs->bind_param($types, ...$setVals);
                    $cs->execute();
                    $cs->close();
                }
            }

            if (users_has_email_column($conn)) {
             
            if (users_has_email_column($conn)) {
                $ue = $conn->prepare('UPDATE users SET email=? WHERE id=? LIMIT 1');
                if ($ue) {
                    $ue->bind_param('si', $email, $uid);
                    $ue->execute();
                    $ue->close();
                    $account_email = $email;
                }
            }
            log_access('profile_updated', 'resident', $rid);
            redirect(url('resident/resident_profile.php?saved=1'));
        }
        $u->close();
        $error = 'We could not save your details right now. Please try again.';
        }  
    }

     
    $profile['contact_no']   = $contact_no;
    $profile['occupation']   = $occupation;
    $profile['civil_status'] = $civil_status;
    $profile['address']      = $address;
}

}

include BASE_PATH . '/partials/header.php';
?>



<?php if (!$profile): ?>
    <div class="alert alert-danger" role="alert">
        Your resident profile could not be loaded. Please contact your barangay secretary.
    </div>
<?php else: ?>

<?php if ($saved): ?>
    <div class="alert alert-success" role="status">Your details have been updated.</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="section-head">
    <div>
        <h2>My Details</h2>
        <p>You may update your contact details and photo below</p>
    </div>
</div>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="contact_no">Contact No.</label>
                <input id="contact_no" name="contact_no" maxlength="30"
                       value="<?= e($profile['contact_no'] ?? '') ?>" placeholder="e.g. 09171234567">
            </div>
            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" maxlength="255" autocomplete="email"
                       value="<?= e($account_email) ?>" placeholder="Used for password recovery">
            </div>
            <div>
                <label for="occupation">Occupation</label>
                <input id="occupation" name="occupation" maxlength="100"
                       value="<?= e($profile['occupation'] ?? '') ?>">
            </div>
            <div>
                <label for="civil_status">Civil Status</label>
                <select id="civil_status" name="civil_status">
                    <option value="">Select</option>
                    <option value="Single"<?= ($profile['civil_status'] ?? '') === 'Single' ? ' selected' : '' ?>>Single</option>
                    <option value="Married"<?= ($profile['civil_status'] ?? '') === 'Married' ? ' selected' : '' ?>>Married</option>
                    <option value="Widowed"<?= ($profile['civil_status'] ?? '') === 'Widowed' ? ' selected' : '' ?>>Widowed</option>
                    <option value="Separated"<?= ($profile['civil_status'] ?? '') === 'Separated' ? ' selected' : '' ?>>Separated</option>
                </select>
            </div>
            <div>
                <label for="address">Purok / Zone</label>
                <input id="address" name="address" maxlength="255"
                       value="<?= e($profile['address'] ?? '') ?>">
            </div>
            <div>
                <label for="photo">My Photo</label>
                <input id="photo" type="file" name="photo" accept="image/*">
                <?php if (!empty($profile['photo']) && @file_exists(BASE_PATH . '/' . $profile['photo'])): ?>
                    <p class="field-hint">Current photo on file. Leave blank to keep it.</p>
                    <img src="<?= e(url($profile['photo'])) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:10px;display:block;margin-top:8px">
                <?php else: ?>
                    <p class="field-hint">No photo on file yet. JPG, JPEG, PNG or GIF, up to 10MB.</p>
                <?php endif; ?>
            </div>
            <div>
                <span class="field-label">Classification</span>
                <label><input type="checkbox" name="is_pwd" value="Yes"<?= in_array($profile['is_pwd'] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> Person with Disability (PWD)</label>
                <label><input type="checkbox" name="is_student" value="Yes"<?= in_array($profile['is_student'] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> Student</label>
                <?php if ($ip_col !== null): ?>
                <label><input type="checkbox" name="is_ip" value="Yes"<?= in_array($profile[$ip_col] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> Indigenous People (IP)</label>
                <?php endif; ?>
                <?php if ($fp_col !== null): ?>
                <label><input type="checkbox" name="is_4ps" value="Yes"<?= in_array($profile[$fp_col] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> 4Ps Beneficiary</label>
                <?php endif; ?>
            </div>
        </div>
        <br>
        <button class="btn" type="submit">Save My Details</button>
    </form>
</div>

<div class="section-head">
    <div>
        <h2>Verified by the Barangay</h2>
        <p>These details are maintained by your barangay office</p>
    </div>
</div>


<div class="card table-wrap">
    <table>
        <tr><th>Field</th><th>Value</th></tr>
        <tr><td>Full Name</td><td><?= e(trim($profile['last_name'] . ', ' . $profile['first_name'] . ' ' . $profile['middle_name'], ', ')) ?></td></tr>
        <tr><td>Email address</td><td><?= e($account_email !== '' ? $account_email : '-') ?></td></tr>
        <tr><td>Barangay</td><td><?= e($profile['barangay_name'] ?? 'Not set') ?></td></tr>
        <tr><td>Sex</td><td><?= e($profile['sex'] ?? '-') ?></td></tr>
        <tr>
            <td>Age</td>
            <td><?php if ($ageKnown): ?><?= e($profile['age']) ?><?php else: ?>
                <span class="muted-meta">Not yet verified &mdash; your secretary will complete this</span>
            <?php endif; ?></td>
        </tr>
        <tr><td>Birth Date</td><td><?= e($profile['birth_date'] ?? '-') ?></td></tr>
        <tr><td>Household</td><td><?php if (!empty($profile['hh_no'])): ?><?= e($profile['hh_no']) ?><?= (int)($profile['hh_head_id'] ?? 0) === (int)($profile['id']) ? ' <span class="badge badge--info">Head of the family</span>' : '' ?><?php else: ?><span class="muted-meta">Not yet assigned &mdash; your barangay office will bind you to a household</span><?php endif; ?></td></tr>
        <tr><td>PWD</td><td><?= e($profile['is_pwd'] ?? '-') ?></td></tr>
        <tr><td>Student</td><td><?= e($profile['is_student'] ?? '-') ?></td></tr>
        <tr><td>Indigenous People (IP)</td><td><?= e($ip_col !== null ? ($profile[$ip_col] ?? '-') : '-') ?></td></tr>
        <tr><td>4Ps Beneficiary</td><td><?= e($fp_col !== null ? ($profile[$fp_col] ?? '-') : '-') ?></td></tr>
    </table>
</div>

<?php if (!$ageKnown): ?>
    <div class="alert" role="status">
        Some details on your profile are still blank. Your barangay secretary will verify and complete them.
    </div>
<?php endif; ?>

<?php endif; ?>

<?php include BASE_PATH . '/partials/footer.php'; ?>
