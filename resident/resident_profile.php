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

<?php
/* Left: who the barangay has on record (read-only). Right: what the resident
   may change themselves. */
$p_photo    = trim((string)($profile['photo'] ?? ''));
$p_hasPhoto = ($p_photo !== '' && @file_exists(BASE_PATH . '/' . $p_photo));
$p_initials = '';
foreach (preg_split('/\s+/', trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) as $part) {
    if ($part !== '' && preg_match('/\p{L}/u', $part, $m)) { $p_initials .= strtoupper($m[0]); }
}
$p_initials = substr($p_initials, 0, 2) ?: '?';
$p_yes  = function ($v) { return in_array(strtolower(trim((string)($v ?? ''))), ['yes', '1', 'y', 'true'], true); };
$p_tags = [];
if ($p_yes($profile['is_pwd'] ?? null))     { $p_tags[] = 'PWD'; }
if ($p_yes($profile['is_student'] ?? null)) { $p_tags[] = 'Student'; }
if ($ip_col !== null && $p_yes($profile[$ip_col] ?? null)) { $p_tags[] = 'Indigenous People'; }
if ($fp_col !== null && $p_yes($profile[$fp_col] ?? null)) { $p_tags[] = '4Ps Beneficiary'; }
$p_is_head = !empty($profile['hh_no']) && (int)($profile['hh_head_id'] ?? 0) === (int)($profile['id']);
$p_birth   = trim((string)($profile['birth_date'] ?? ''));
$p_sex     = trim((string)($profile['sex'] ?? ''));
$p_purok   = trim((string)($profile['address'] ?? ''));
?>
<div class="profile-layout">
    <aside class="card profile-card" aria-labelledby="profile-name">
        <div class="profile-card__head">
            <?php if ($p_hasPhoto): ?>
                <img class="profile-card__photo" src="<?= e(url($p_photo)) ?>" alt="">
            <?php else: ?>
                <span class="profile-card__photo profile-card__photo--initials" aria-hidden="true"><?= e($p_initials) ?></span>
            <?php endif; ?>
            <h2 class="profile-card__name" id="profile-name"><?= e(resident_name($profile)) ?></h2>
            <p class="profile-card__sub">Resident &middot; <?= e($profile['barangay_name'] ?? 'Barangay not set') ?></p>
            <?php if ($p_tags || $p_is_head): ?>
            <div class="tags profile-card__tags">
                <?php if ($p_is_head): ?><span class="badge badge--info">Head of the family</span><?php endif; ?>
                <?php foreach ($p_tags as $tag): ?><span class="tag"><?= e($tag) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <dl class="detail-list">
            <div><dt>Sex</dt><dd><?= $p_sex !== '' ? e($p_sex) : '<span class="muted-meta">&mdash;</span>' ?></dd></div>
            <div><dt>Age</dt><dd><?php if ($ageKnown): ?><?= e($profile['age']) ?><?php else: ?><span class="muted-meta">Not yet verified</span><?php endif; ?></dd></div>
            <div><dt>Birth date</dt><dd><?= $p_birth !== '' ? e(fmt_date($p_birth)) : '<span class="muted-meta">&mdash;</span>' ?></dd></div>
            <div><dt>Household</dt><dd><?php if (!empty($profile['hh_no'])): ?><?= e($profile['hh_no']) ?><?php else: ?><span class="muted-meta">Not yet assigned</span><?php endif; ?></dd></div>
            <div><dt>Purok / Zone</dt><dd><?= $p_purok !== '' ? e($p_purok) : '<span class="muted-meta">&mdash;</span>' ?></dd></div>
            <div><dt>Email</dt><dd><?= $account_email !== '' ? e($account_email) : '<span class="muted-meta">None on file</span>' ?></dd></div>
        </dl>

        <p class="profile-card__note"><?= icon('shield', 'ico ico--xs') ?> Name, sex, age, birth date and household are kept by your barangay office<?php if (!$ageKnown || empty($profile['hh_no'])): ?>. Blank ones will be completed by your secretary<?php endif; ?>.</p>
    </aside>

    <div class="profile-edit">
        <div class="card">
            <h2 class="profile-edit__title">My Details</h2>
            <p class="profile-edit__sub">You may update your contact details and photo.</p>
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
                        <p class="field-hint"><?= $p_hasPhoto ? 'Leave blank to keep your current photo.' : 'No photo on file yet.' ?> JPG, JPEG, PNG or GIF, up to 10MB.</p>
                    </div>
                    <fieldset class="full check-group">
                        <legend class="field-label">Classification</legend>
                        <label class="check"><input type="checkbox" name="is_pwd" value="Yes"<?= in_array($profile['is_pwd'] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> <span>Person with Disability (PWD)</span></label>
                        <label class="check"><input type="checkbox" name="is_student" value="Yes"<?= in_array($profile['is_student'] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> <span>Student</span></label>
                        <?php if ($ip_col !== null): ?>
                        <label class="check"><input type="checkbox" name="is_ip" value="Yes"<?= in_array($profile[$ip_col] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> <span>Indigenous People (IP)</span></label>
                        <?php endif; ?>
                        <?php if ($fp_col !== null): ?>
                        <label class="check"><input type="checkbox" name="is_4ps" value="Yes"<?= in_array($profile[$fp_col] ?? '', ['Yes', '1', 1], true) ? ' checked' : '' ?>> <span>4Ps Beneficiary</span></label>
                        <?php endif; ?>
                    </fieldset>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Save My Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include BASE_PATH . '/partials/footer.php'; ?>
