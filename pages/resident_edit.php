<?php
require_once __DIR__ . '/../config/config.php'; require_once __DIR__ . '/../includes/mailer.php'; require_staff(); $id=(int)($_GET['id']??0);$page_title='Edit Resident';
$s=$conn->prepare('SELECT r.*, u.email AS account_email, u.id AS account_id, u.username AS account_username,
                          b.barangay_name, h.household_no AS hh_no, h.head_resident_id AS hh_head_id
                     FROM residents r
                     LEFT JOIN barangays b ON b.id = r.barangay_id
                     LEFT JOIN users u ON u.id = r.user_id
                     LEFT JOIN households h ON h.id = r.household_id
                    WHERE r.id=? AND r.deleted_at IS NULL LIMIT 1');
$s->bind_param('i',$id);
$s->execute();
$r=$s->get_result()->fetch_assoc();$s->close();if(!$r)die('Resident not found.');

// Household form state (overwritten from POST when a save fails).
$r['household_mode']    = ((int)($r['household_id'] ?? 0) > 0) ? 'existing' : 'none';
$r['household_form_id'] = (int)($r['household_id'] ?? 0);
$r['household_form_no'] = '';
$r['is_head_form']      = ((int)($r['hh_head_id'] ?? 0) === $id);

 
require_record_access($r['barangay_id'], null, 'resident', $id);

// IP / 4Ps classification columns (added outside the base schema) — detect
// whichever names exist (same candidates as the dashboard/list) so the form
// and the save stay in step with the DB and never break on a missing column.
$ip_col=null; $ip_int=false; $fp_col=null; $fp_int=false;
foreach(['is_ip','is_indigenous','indigenous'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $cf=$qc->fetch_assoc(); $ip_col=$cand; $ip_int=(bool)preg_match('/int/i',(string)$cf['Type']); $qc->free(); break; } $qc->free(); } }
foreach(['is_4ps','is_fourps','fourps','pantawid'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $cf=$qc->fetch_assoc(); $fp_col=$cand; $fp_int=(bool)preg_match('/int/i',(string)$cf['Type']); $qc->free(); break; } $qc->free(); } }

// Current ticked state — from the stored row on GET (overwritten by the POST below).
$ip_on = ($ip_col !== null) && in_array(strtolower((string)($r[$ip_col] ?? '')), ['yes','1'], true);
$fp_on = ($fp_col !== null) && in_array(strtolower((string)($r[$fp_col] ?? '')), ['yes','1'], true);

$error = '';

if($_SERVER['REQUEST_METHOD']==='POST')
{
    require_csrf();

    

    $photo = (string)$r['photo'];

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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

    if($error === '') {
        $last_name    = trim($_POST['last_name'] ?? '');
        $first_name   = trim($_POST['first_name'] ?? '');
        $middle_name  = trim($_POST['middle_name'] ?? '');
        $suffix       = trim($_POST['suffix'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $sex          = trim($_POST['sex'] ?? '');
        // Age is computed from the birth date, never trusted from the form.
        $birth_date   = trim($_POST['birth_date'] ?? '');
        $civil_status = trim($_POST['civil_status'] ?? '');
        $occupation   = trim($_POST['occupation'] ?? '');
        $contact_no   = trim($_POST['contact_no'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        // Household binding: new (create + this resident becomes head),
        // existing (join / stay; optional headship), none (leave unassigned).
        $household_mode = trim($_POST['household_mode'] ?? 'none');
        $hh_form_id     = (int)($_POST['household_id'] ?? 0);
        $household_no   = trim($_POST['household_no'] ?? ''); // only used when mode = new
        $is_head        = isset($_POST['is_head']);           // only used when mode = existing
        if (!in_array($household_mode, ['new', 'existing', 'none'], true)) { $household_mode = 'none'; }
         
        $is_pwd       = isset($_POST['is_pwd']) ? 'Yes' : 'No';
        $is_student   = isset($_POST['is_student']) ? 'Yes' : 'No';
        $ip_on        = isset($_POST['is_ip']);
        $fp_on        = isset($_POST['is_4ps']);
        // Stored value matches the column type: enum/varchar get Yes/No, int gets 1/0.
        $is_ip        = $ip_on ? ($ip_int ? 1 : 'Yes') : ($ip_int ? 0 : 'No');
        $is_4ps       = $fp_on ? ($fp_int ? 1 : 'Yes') : ($fp_int ? 0 : 'No');

        $birth_date_v = ($birth_date !== '') ? $birth_date : null;

        // Validate the birth date (exact YYYY-MM-DD, not future, within 120
        // years) and compute age from it. Rejects bad input like a 5-digit year.
        $age = (int)$r['age'];
        if ($birth_date_v !== null) {
            $bd     = DateTime::createFromFormat('!Y-m-d', $birth_date_v);
            $today  = new DateTime('today');
            $oldest = (new DateTime('today'))->modify('-120 years');
            if (($bd instanceof DateTime) && $bd->format('Y-m-d') === $birth_date_v && $bd <= $today && $bd >= $oldest) {
                $age = (int)$today->diff($bd)->y;
            } else {
                $error = 'Please enter a valid birth date (year must be realistic and not in the future).';
                $birth_date_v = null;
            }
        } else {
            $age = 0;
        }

        $account_id = (int)($r['account_id'] ?? 0);
        // Names must not contain numbers (rejects "Juan2", "III 3", etc.).
        $name_fields = ['last_name' => 'Last Name', 'first_name' => 'First Name', 'middle_name' => 'Middle Name', 'suffix' => 'Suffix'];
        foreach ($name_fields as $nfield => $nlabel) {
            if ($$nfield !== '' && preg_match('/\d/', $$nfield)) {
                $error = $nlabel . ' must not contain numbers. Please use letters only.';
                break;
            }
        }
        if ($error === '' && strlen($suffix) > 20) {
            $error = 'Suffix is too long (max 20 characters).';
        } elseif ($error === '' && $email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
            $error = 'Please enter a valid email address.';
        } elseif ($error === '' && $email !== '' && $account_id > 0 && users_has_email_column($conn)) {
            $ck = $conn->prepare('SELECT id FROM users WHERE email=? AND id<>? AND deleted_at IS NULL LIMIT 1');
            if ($ck) {
                $ck->bind_param('si', $email, $account_id);
                $ck->execute();
                $dup = (bool)$ck->get_result()->fetch_assoc();
                $ck->close();
                if ($dup) { $error = 'That email address is already registered. Please use another.'; }
            }
        }

        /* --- Household binding (single-head rule) ---------------------------
         * new      -> create a household for this barangay and make this
         *            resident its head;
         * existing -> stay in / move to a household in the same barangay;
         *            headship only while the household has no living head;
         * none     -> unassign (headship of the old household is released).
         */
        $new_household_no = null;
        $old_household_id = (int)($r['household_id'] ?? 0);
        if ($error === '' && $household_mode === 'existing') {
            if ($hh_form_id <= 0) {
                $error = 'Please choose a household.';
            } else {
                $hh_live = household_fetch($conn, $hh_form_id);
                if (!$hh_live || $hh_live['deleted_at'] !== null) {
                    $error = 'The selected household no longer exists. Please refresh and pick another.';
                } elseif ((int)$hh_live['barangay_id'] !== (int)$r['barangay_id']) {
                    $error = 'The selected household belongs to a different barangay.';
                } elseif ($is_head) {
                    $head   = (int)($hh_live['head_resident_id'] ?? 0);
                    $vacant = ($head === 0) || ($head === $id) || ((int)$hh_live['head_live'] === 0);
                    if (!$vacant) {
                        $error = 'That household already has a head of the family. '
                               . 'Uncheck "Head of this household" or transfer headship from the current head\'s edit page.';
                    }
                }
            }
        } elseif ($error === '' && $household_mode === 'new') {
            if ($household_no !== '') {
                if (strlen($household_no) > 50) {
                    $error = 'Household number is too long (max 50 characters).';
                } else {
                    $hs = $conn->prepare('SELECT id FROM households WHERE barangay_id = ? AND household_no = ? LIMIT 1');
                    $hs->bind_param('is', $r['barangay_id'], $household_no);
                    $hs->execute();
                    $dup = (bool)$hs->get_result()->fetch_assoc();
                    $hs->close();
                    if ($dup) {
                        $error = 'Household number "' . $household_no . '" already exists in this barangay. '
                               . 'Leave it blank to auto-generate the next number.';
                    }
                }
            } else {
                $new_household_no = household_next_no($conn, (int)$r['barangay_id']);
            }
        }

        if ($error !== '') {
             
        } else {
        $email_val = ($email !== '') ? $email : null;
        $conn->begin_transaction();
        try {
        $stmt=$conn->prepare("UPDATE residents SET photo=?,last_name=?,first_name=?,middle_name=?,suffix=?,
        sex=?,age=?,birth_date=?,civil_status=?,occupation=?,contact_no=?,email=?,address=?,is_pwd=?,
        is_student=?  WHERE id=?");

        $stmt->bind_param('ssssssissssssssi',
            $photo, $last_name, $first_name, $middle_name, $suffix, $sex,
            $age, $birth_date_v, $civil_status, $occupation, $contact_no,
            $email_val, $address, $is_pwd, $is_student, $id);

        if(!$stmt->execute()) { $stmt->close(); throw new RuntimeException('resident profile update failed'); }
        $stmt->close();

        // --- Household binding, atomically with the profile update -----------
        if ($household_mode === 'new') {
            $num = ($household_no !== '') ? $household_no : (string)$new_household_no;
            $new_hh_id = household_create($conn, (int)$r['barangay_id'], $num, $address);
            if ($old_household_id > 0 && $old_household_id !== $new_hh_id) {
                household_release_head($conn, $old_household_id, $id);
            }
            household_assign($conn, $id, $new_hh_id);
            if (!household_claim_head($conn, $new_hh_id, $id)) {
                throw new RuntimeException('could not register the head of the new household');
            }
        } elseif ($household_mode === 'existing') {
            if ($old_household_id > 0 && $old_household_id !== $hh_form_id) {
                // Moving out of the old household: give up its headship.
                household_release_head($conn, $old_household_id, $id);
            }
            household_assign($conn, $id, $hh_form_id);
            if ($is_head) {
                if (!household_claim_head($conn, $hh_form_id, $id)) {
                    throw new RuntimeException('that household already has a living head - uncheck "Head of this household" or transfer headship first');
                }
            } elseif ($old_household_id === $hh_form_id) {
                // Staying put but stepping down as head.
                household_release_head($conn, $hh_form_id, $id);
            }
        } else {
            // No household: unassign and release any headship they held.
            if ($old_household_id > 0) {
                household_release_head($conn, $old_household_id, $id);
            }
            household_assign($conn, $id, null);
        }


            // Persist the optional IP / 4Ps classification (columns come from
            // database/update.sql — skipped entirely when they don't exist).
            if ($ip_col !== null || $fp_col !== null) {
                $setSql = ''; $setTypes = ''; $setVals = [];
                if ($ip_col !== null) { $setSql .= "`$ip_col`=?"; $setTypes .= $ip_int ? 'i' : 's'; $setVals[] = $is_ip; }
                if ($fp_col !== null) { $setSql .= ($setSql !== '' ? ',' : '') . "`$fp_col`=?"; $setTypes .= $fp_int ? 'i' : 's'; $setVals[] = $is_4ps; }
                $setVals[] = $id;
                if ($cs = $conn->prepare("UPDATE residents SET $setSql WHERE id=?")) {
                    $cs->bind_param($setTypes . 'i', ...$setVals);
                    $cs->execute();
                    $cs->close();
                }
            }
             
            if ($account_id > 0 && users_has_email_column($conn)) {
                $ue = $conn->prepare('UPDATE users SET email=? WHERE id=? LIMIT 1');
                if ($ue) {
                    $ue->bind_param('si', $email_val, $account_id);
                    $ue->execute();
                    $ue->close();
                }
            }
            $conn->commit();
            log_access('resident_edited', 'resident', $id);
            redirect(url('pages/residents.php'));
        } catch (Throwable $ex) {
            $conn->rollback();
            $error = 'Could not save the resident record: ' . $ex->getMessage();
        }
        }  
    }

     
    $r['last_name']    = $last_name;
    $r['first_name']   = $first_name;
    $r['middle_name']  = $middle_name;
    $r['suffix']       = $suffix;
    $r['email']        = $email;
    $r['account_email'] = $email;
    $r['sex']          = $sex;
    $r['age']          = $age;
    $r['birth_date']   = $birth_date;
    $r['civil_status'] = $civil_status;
    $r['occupation']   = $occupation;
    $r['contact_no']   = $contact_no;
    $r['address']      = $address;
    $r['household_mode']    = $household_mode;
    $r['household_form_id'] = $hh_form_id;
    $r['household_form_no'] = $household_no;
    $r['is_head_form']      = $is_head;
    $r['is_pwd']       = $is_pwd;
    $r['is_student']   = $is_student;
}

$hh_options = households_options($conn, (int)$r['barangay_id']);
$page_scripts = ['assets/js/resident_age.js'];
include BASE_PATH . '/partials/header.php';?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php
/* Left: who this record is (read-only summary, same card as the resident's My Profile page).
   Right: the editable form. */
$p_photo    = trim((string)($r['photo'] ?? ''));
$p_hasPhoto = ($p_photo !== '' && @file_exists(BASE_PATH . '/' . $p_photo));
$p_initials = '';
foreach (preg_split('/\s+/', trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) as $part) {
    if ($part !== '' && preg_match('/\p{L}/u', $part, $m)) { $p_initials .= strtoupper($m[0]); }
}
$p_initials = substr($p_initials, 0, 2) ?: '?';
$p_tags = [];
if ($r['is_pwd'] === 'Yes')     { $p_tags[] = 'PWD'; }
if ($r['is_student'] === 'Yes') { $p_tags[] = 'Student'; }
if ($ip_on) { $p_tags[] = 'Indigenous People'; }
if ($fp_on) { $p_tags[] = '4Ps Beneficiary'; }
$p_is_head = !empty($r['hh_no']) && (int)($r['hh_head_id'] ?? 0) === $id;
$p_user    = trim((string)($r['account_username'] ?? ''));
$p_added   = trim((string)($r['created_at'] ?? ''));
?>
<div class="profile-layout">
    <aside class="card profile-card" aria-labelledby="profile-name">
        <div class="profile-card__head">
            <?php if ($p_hasPhoto): ?>
                <img class="profile-card__photo" src="<?= e(url($p_photo)) ?>" alt="">
            <?php else: ?>
                <span class="profile-card__photo profile-card__photo--initials" aria-hidden="true"><?= e($p_initials) ?></span>
            <?php endif; ?>
            <h2 class="profile-card__name" id="profile-name"><?= e(resident_name($r)) ?></h2>
            <p class="profile-card__sub">Resident &middot; <?= e($r['barangay_name'] ?? 'Barangay not set') ?></p>
            <?php if ($p_tags || $p_is_head): ?>
            <div class="tags profile-card__tags">
                <?php if ($p_is_head): ?><span class="badge badge--info">Head of the family</span><?php endif; ?>
                <?php foreach ($p_tags as $tag): ?><span class="tag"><?= e($tag) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <dl class="detail-list">
            <div><dt>Resident ID</dt><dd><?= (int)$id ?></dd></div>
            <div><dt>Household</dt><dd><?= !empty($r['hh_no']) ? e($r['hh_no']) : '<span class="muted-meta">Not yet assigned</span>' ?></dd></div>
            <div><dt>Login account</dt><dd><?= $p_user !== '' ? e($p_user) : '<span class="muted-meta">None</span>' ?></dd></div>
            <div><dt>Email</dt><dd><?= trim((string)($r['account_email'] ?? '')) !== '' ? e($r['account_email']) : '<span class="muted-meta">None on file</span>' ?></dd></div>
            <div><dt>Added</dt><dd><?= $p_added !== '' ? e(fmt_date($p_added)) : '<span class="muted-meta">&mdash;</span>' ?></dd></div>
        </dl>
        <p class="profile-card__note"><?= icon('shield', 'ico ico--xs') ?> This summary updates after you save. Changes are logged under your account.</p>
    </aside>

    <div class="profile-edit">
        <div class="card">
            <h2 class="profile-edit__title">Resident Details</h2>
            <p class="profile-edit__sub">Update the record kept by the barangay office.</p>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="form-grid form-grid--4">
    <h2 class="form-section full">Personal</h2>
    <div><label for="last_name">Last Name</label><input id="last_name" name="last_name" value="<?=e($r['last_name'])?>" required pattern="[^\d]*" title="Letters only - no numbers"></div>
    <div><label for="first_name">First Name</label><input id="first_name" name="first_name" value="<?=e($r['first_name'])?>" required pattern="[^\d]*" title="Letters only - no numbers"></div>
    <div><label for="middle_name">Middle Name</label><input id="middle_name" name="middle_name" value="<?=e($r['middle_name']) ?>" pattern="[^\d]*" title="Letters only - no numbers"></div>
    <div><label for="suffix">Suffix</label><input id="suffix" name="suffix" maxlength="20" pattern="[^\d]*" title="Letters only - no numbers" placeholder="e.g. Jr., Sr., III" value="<?=e($r['suffix'] ?? '') ?>"></div>
    <div>
        <label for="sex">Sex</label>
        <select id="sex" name="sex">
            <option value="">Select</option>
            <option value="Male"<?= $r['sex'] === 'Male' ? ' selected' : '' ?>>Male</option>
            <option value="Female"<?= $r['sex'] === 'Female' ? ' selected' : '' ?>>Female</option>
        </select>
    </div>
    <?php $bd_min = date('Y-m-d', strtotime('-120 years')); $bd_max = date('Y-m-d'); ?>
    <div><label for="birth_date">Birth Date</label><input id="birth_date" type="date" name="birth_date" value="<?=e($r['birth_date']) ?>" min="<?= e($bd_min) ?>" max="<?= e($bd_max) ?>"></div>
    <div><label for="age">Age</label><input id="age" type="number" min="0" max="150" name="age" value="<?=e($r['age']) ?>" readonly title="Automatically computed from the birth date"></div>
    <div>
        <label for="civil_status">Civil Status</label>
        <select id="civil_status" name="civil_status">
            <option value="">Select</option>
            <option value="Single"<?= $r['civil_status'] === 'Single' ? ' selected' : '' ?>>Single</option>
            <option value="Married"<?= $r['civil_status'] === 'Married' ? ' selected' : '' ?>>Married</option>
            <option value="Widowed"<?= $r['civil_status'] === 'Widowed' ? ' selected' : '' ?>>Widowed</option>
            <option value="Separated"<?= $r['civil_status'] === 'Separated' ? ' selected' : '' ?>>Separated</option>
        </select>
    </div>
    <h2 class="form-section full">Contact and address</h2>
    <div><label for="contact_no">Contact No.</label><input id="contact_no" name="contact_no" value="<?=e($r['contact_no']) ?>"></div>
    <div><label for="email">Email address</label><input id="email" type="email" name="email" maxlength="255" autocomplete="email" value="<?=e($r['email'] ?? ($r['account_email'] ?? '')) ?>" placeholder="Contact email (optional)"></div>
    <div><label for="address">Purok/Zone</label><select id="address" name="address"><?php $zone_opts = ['Zone 1','Zone 2','Zone 3','Zone 4','Zone 5','Zone 6','Zone 7']; $cur_zone = trim((string)($r['address'] ?? '')); ?><option value=""<?= $cur_zone === '' ? ' selected' : '' ?>>Select</option><?php if ($cur_zone !== '' && !in_array($cur_zone, $zone_opts, true)): ?><option value="<?=e($cur_zone) ?>" selected><?=e($cur_zone) ?></option><?php endif; ?><?php foreach ($zone_opts as $zo): ?><option value="<?= $zo ?>"<?= $cur_zone === $zo ? ' selected' : '' ?>><?= $zo ?></option><?php endforeach; ?></select></div>
    <div><label for="occupation">Occupation</label><input id="occupation" name="occupation" value="<?=e($r['occupation']) ?>"></div>
    <h2 class="form-section full">Household</h2>
    <fieldset class="span-2 check-group check-group--stack">
        <legend class="sr-only">Household</legend>
        <label class="check"><input type="radio" name="household_mode" value="new"<?= $r['household_mode'] === 'new' ? ' checked' : '' ?>> <span>Create a new household</span></label>
        <label class="check"><input type="radio" name="household_mode" value="existing"<?= $r['household_mode'] === 'existing' ? ' checked' : '' ?>> <span>Household of this resident</span></label>
        <label class="check"><input type="radio" name="household_mode" value="none"<?= $r['household_mode'] === 'none' ? ' checked' : '' ?>> <span>No household (unassign)</span></label>
    </fieldset>
    <div class="span-2" id="hh_new_fields"<?= $r['household_mode'] !== 'new' ? ' hidden' : '' ?>>
        <label for="household_no">New Household Number</label>
        <input id="household_no" name="household_no" maxlength="50" value="<?= e($r['household_form_no']) ?>" placeholder="HH-XXXX">
        <p class="field-hint">Leave blank to auto-generate. This resident becomes the <strong>head of the family</strong>.</p>
    </div>
    <div class="span-2" id="hh_existing_fields"<?= $r['household_mode'] !== 'existing' ? ' hidden' : '' ?>>
        <label for="household_id">Household</label>
        <select id="household_id" name="household_id">
            <option value="0">No household (unassign)</option>
            <?php foreach ($hh_options as $it): $mem = (int)$it['members']; ?>
            <option value="<?= (int)$it['id'] ?>"<?= (int)$r['household_form_id'] === (int)$it['id'] ? ' selected' : '' ?>><?= e($it['household_no']) ?> &mdash; <?= e($it['head_name'] ?: 'No head yet') ?> (<?= $mem ?> member<?= $mem === 1 ? '' : 's' ?>)</option>
            <?php endforeach; ?>
        </select>
        <label class="check" style="margin-top:10px"><input type="checkbox" id="is_head" name="is_head" value="1"<?= $r['is_head_form'] ? ' checked' : '' ?>> <span>Head of this household</span></label>
        <p class="field-hint">Tick only when the household has no living head yet.</p>
    </div>
    <h2 class="form-section full">Photo and classification</h2>
    <div class="span-2">
        <label for="photo">Resident Photo</label>
        <input id="photo" type="file" name="photo" accept="image/*">
        <p class="field-hint"><?= $p_hasPhoto ? 'Leave blank to keep the current photo.' : 'No photo on file yet.' ?></p>
    </div>
    <fieldset class="span-2 check-group">
        <legend class="field-label">Classification</legend>
        <label class="check"><input type="checkbox" name="is_pwd" value="Yes"<?= $r['is_pwd'] === 'Yes' ? ' checked' : '' ?>> <span>PWD</span></label>
        <label class="check"><input type="checkbox" name="is_student" value="Yes"<?= $r['is_student'] === 'Yes' ? ' checked' : '' ?>> <span>Student</span></label>
        <?php if ($ip_col !== null): ?>
        <label class="check"><input type="checkbox" name="is_ip" value="Yes"<?= $ip_on ? ' checked' : '' ?>> <span>Indigenous People (IP)</span></label>
        <?php endif; ?>
        <?php if ($fp_col !== null): ?>
        <label class="check"><input type="checkbox" name="is_4ps" value="Yes"<?= $fp_on ? ' checked' : '' ?>> <span>4Ps Beneficiary</span></label>
        <?php endif; ?>
    </fieldset>
</div>
<div class="form-actions"><button class="btn" type="submit">Update</button> <a class="btn btn--ghost" href="<?= e(url('pages/residents.php')) ?>">Cancel</a></div></form>
        </div>
    </div>
</div>
<script>
(function () {
    // Show only the fields for the selected household mode.
    var radios = document.querySelectorAll('input[name="household_mode"]');
    var newBox = document.getElementById('hh_new_fields');
    var exBox  = document.getElementById('hh_existing_fields');
    function sync() {
        var mode = 'none';
        radios.forEach(function (r) { if (r.checked) { mode = r.value; } });
        if (newBox) { newBox.hidden = (mode !== 'new'); }
        if (exBox)  { exBox.hidden  = (mode !== 'existing'); }
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();

    // Names are letters only - strip any digit as it is typed, pasted or dropped in.
    ['last_name', 'first_name', 'middle_name', 'suffix'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) { return; }
        el.addEventListener('input', function () {
            var v = el.value;
            var cleaned = v.replace(/[0-9]/g, '');
            if (cleaned !== v) {
                var at = el.selectionStart;
                var dropped = 0;
                for (var i = 0; i < v.length && i < at; i++) { if (v[i] >= '0' && v[i] <= '9') { dropped++; } }
                el.value = cleaned;
                var pos = Math.max(0, at - dropped);
                try { el.setSelectionRange(pos, pos); } catch (e) {}
            }
        });
    });
})();
</script>
<?php include BASE_PATH . '/partials/footer.php';

