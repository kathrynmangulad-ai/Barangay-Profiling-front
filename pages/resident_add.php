<?php








require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_staff();

$page_title = 'New Resident';
$page_crumb = 'New Resident';
$error = '';
$error_fields = [];

// IP / 4Ps classification columns (added outside the base schema) — detect
// whichever names exist (same candidates as the dashboard/list) so the form
// and the save stay in step with the DB and never break on a missing column.
$ip_col=null; $ip_int=false; $fp_col=null; $fp_int=false;
foreach(['is_ip','is_indigenous','indigenous'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $cf=$qc->fetch_assoc(); $ip_col=$cand; $ip_int=(bool)preg_match('/int/i',(string)$cf['Type']); $qc->free(); break; } $qc->free(); } }
foreach(['is_4ps','is_fourps','fourps','pantawid'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $cf=$qc->fetch_assoc(); $fp_col=$cand; $fp_int=(bool)preg_match('/int/i',(string)$cf['Type']); $qc->free(); break; } $qc->free(); } }

$old = [
    'barangay_id'  => ($_SESSION['role'] === 'admin') ? 0 : (int)($_SESSION['barangay_id'] ?? 0),
    'username'     => '',
    'last_name'    => '',
    'first_name'   => '',
    'middle_name'  => '',
    'sex'          => '',
    'age'          => '',
    'birth_date'   => '',
    'civil_status' => '',
    'occupation'   => '',
    'contact_no'   => '',
    'address'      => '',
    'household_mode' => 'new',
    'household_id'   => 0,
    'household_no'   => '',
    'is_head'      => false,
    'email'        => '',
    'is_pwd'       => false,
    'is_student'   => false,
    'is_ip'        => false,
    'is_4ps'       => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $barangay_id = ($_SESSION['role'] === 'admin')
        ? (int)($_POST['barangay_id'] ?? 0)
        : (int)($_SESSION['barangay_id'] ?? 0);

    $username     = trim($_POST['username'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $sex          = trim($_POST['sex'] ?? '');
    // Note: age is never taken from user input - it is computed from the birth date below.
    $birth_date   = trim($_POST['birth_date'] ?? '');
    $civil_status = trim($_POST['civil_status'] ?? '');
    $occupation   = trim($_POST['occupation'] ?? '');
    $contact_no   = trim($_POST['contact_no'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    // Household binding: new (create + this resident becomes head), existing
    // (join; headship only when the household has no living head), none.
    $household_mode = trim($_POST['household_mode'] ?? 'new');
    $hh_form_id     = (int)($_POST['household_id'] ?? 0);
    $household_no   = trim($_POST['household_no'] ?? ''); // only used when mode = new
    $is_head        = isset($_POST['is_head']);           // only used when mode = existing
    if (!in_array($household_mode, ['new', 'existing', 'none'], true)) { $household_mode = 'new'; }
    

    $email = trim($_POST['email'] ?? '');
     
    $is_pwd       = isset($_POST['is_pwd']) ? 'Yes' : 'No';
    $is_student   = isset($_POST['is_student']) ? 'Yes' : 'No';
    $is_ip_on     = isset($_POST['is_ip']);
    $is_4ps_on    = isset($_POST['is_4ps']);
    // Stored value matches the column type: enum/varchar get Yes/No, int gets 1/0.
    $is_ip        = $is_ip_on ? ($ip_int ? 1 : 'Yes') : ($ip_int ? 0 : 'No');
    $is_4ps       = $is_4ps_on ? ($fp_int ? 1 : 'Yes') : ($fp_int ? 0 : 'No');
    $birth_date   = ($birth_date !== '') ? $birth_date : null;

    // Validate + compute age from the birth date. A valid date must be exactly
    // YYYY-MM-DD, not in the future, and within the last 120 years. Anything
    // else (e.g. a 5-digit year like "20001") is rejected.
    $age  = 0;
    if ($birth_date !== null) {
        $bd     = DateTime::createFromFormat('!Y-m-d', $birth_date);
        $today  = new DateTime('today');
        $oldest = (new DateTime('today'))->modify('-120 years');
        $valid  = ($bd instanceof DateTime)
            && $bd->format('Y-m-d') === $birth_date   // round-trips exactly (rejects bad input)
            && $bd <= $today                           // not in the future
            && $bd >= $oldest;                         // within 120 years
        if ($valid) {
            $age = (int)$today->diff($bd)->y;
        } else {
            $error = 'Please enter a valid birth date (year must be realistic and not in the future).';
            $error_fields[] = 'birth_date';
            $birth_date = null;
        }
    }

     
    $old = [
        'barangay_id'  => $barangay_id,
        'username'     => $username,
        'last_name'    => $last_name,
        'first_name'   => $first_name,
        'middle_name'  => $middle_name,
        'sex'          => $sex,
        'age'          => (string)$age,
        'birth_date'   => (string)($birth_date ?? ''),
        'civil_status' => $civil_status,
        'occupation'   => $occupation,
        'contact_no'   => $contact_no,
        'address'      => $address,
        'household_mode' => $household_mode,
        'household_id'   => $hh_form_id,
        'household_no'   => $household_no,
        'is_head'      => $is_head,
        'email'        => $email,
        'is_pwd'       => ($is_pwd === 'Yes'),
        'is_student'   => ($is_student === 'Yes'),
        'is_ip'        => $is_ip_on,
        'is_4ps'       => $is_4ps_on,
    ];

    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = BASE_PATH . '/uploads/residents/';
        if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

        $extension = strtolower(pathinfo((string)$_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($extension, $allowed, true)) {
            $error = 'Invalid photo type. Allowed formats: JPG, JPEG, PNG, GIF.';
            $error_fields = ['photo'];
        } elseif ((int)$_FILES['photo']['size'] > 10 * 1024 * 1024) {
            $error = 'Photo is too large. Maximum size is 10MB.';
            $error_fields = ['photo'];
        } else {
            $new_filename = uniqid('resident_', true) . '.' . $extension;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_filename)) {
                $photo = 'uploads/residents/' . $new_filename;
            }
        }
    }

    if ($error === '' && ($barangay_id <= 0 || $last_name === '' || $first_name === '')) {
        $error = 'Please provide the barangay, last name and first name.';
        if ($barangay_id <= 0) { $error_fields[] = 'barangay_id'; }
        if ($last_name === '') { $error_fields[] = 'last_name'; }
        if ($first_name === '') { $error_fields[] = 'first_name'; }
    }

    if ($error === '' && $email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
        $error = 'Please enter a valid email address.';
        $error_fields = ['email'];
    }

    /* --- Household binding -------------------------------------------------
     * Every resident is bound to at most one household at registration:
     *   new      -> create a household (auto number when blank) and register
     *              this resident as its head;
     *   existing -> join a household in the SAME barangay; headship is only
     *              granted while that household has no living head;
     *   none     -> leave unassigned (the secretary assigns later via Edit).
     * The final headship check also runs inside the transaction below.
     */
    $new_household_no = null;
    if ($error === '' && $household_mode === 'existing') {
        if ($hh_form_id <= 0) {
            $error = 'Please choose the household to join.';
            $error_fields[] = 'household_id';
        } else {
            $hs = $conn->prepare('SELECT barangay_id, head_resident_id, deleted_at FROM households WHERE id = ? LIMIT 1');
            $hs->bind_param('i', $hh_form_id);
            $hs->execute();
            $hh_row = $hs->get_result()->fetch_assoc();
            $hs->close();
            if (!$hh_row || $hh_row['deleted_at'] !== null) {
                $error = 'The selected household no longer exists. Please refresh and pick another.';
                $error_fields[] = 'household_id';
            } elseif ((int)$hh_row['barangay_id'] !== (int)$barangay_id) {
                $error = 'The selected household belongs to a different barangay.';
                $error_fields[] = 'household_id';
            } elseif ($is_head) {
                // Friendly pre-check for the single-head rule (re-checked in the transaction).
                $hh_live = household_fetch($conn, $hh_form_id);
                $head = (int)($hh_row['head_resident_id'] ?? 0);
                $vacant = ($head === 0) || !$hh_live || (int)$hh_live['head_live'] === 0;
                if (!$vacant) {
                    $error = 'That household already has a head of the family. '
                           . 'Uncheck "Head of this household", or transfer headship from the resident\'s edit page.';
                    $error_fields[] = 'is_head';
                }
            }
        }
    } elseif ($error === '' && $household_mode === 'new') {
        if ($household_no !== '') {
            if (strlen($household_no) > 50) {
                $error = 'Household number is too long (max 50 characters).';
                $error_fields[] = 'household_no';
            } else {
                $hs = $conn->prepare('SELECT id FROM households WHERE barangay_id = ? AND household_no = ? LIMIT 1');
                $hs->bind_param('is', $barangay_id, $household_no);
                $hs->execute();
                $dup = (bool)$hs->get_result()->fetch_assoc();
                $hs->close();
                if ($dup) {
                    $error = 'Household number "' . $household_no . '" already exists in this barangay. '
                           . 'Leave it blank to auto-generate the next number.';
                    $error_fields[] = 'household_no';
                }
            }
        } else {
            $new_household_no = household_next_no($conn, $barangay_id);
        }
    }

    // Email is stored on the resident profile as a contact detail (Option A).
    $email_val = ($email !== '') ? $email : null;

    // Account creation is OPTIONAL: only when a username is provided do we also
    // create a linked resident LOGIN account. Blank username = profile only.
    $make_account = ($username !== '');

    if ($error === '' && $make_account) {
        if (strlen($username) > 100) {
            $error = 'Username is too long (max 100 characters).';
            $error_fields[] = 'username';
        } else {
            // Username must be unique among non-deleted accounts.
            $uq = $conn->prepare('SELECT id FROM users WHERE username=? AND deleted_at IS NULL LIMIT 1');
            $uq->bind_param('s', $username);
            $uq->execute();
            if ($uq->get_result()->fetch_assoc()) {
                $error = 'That username is already taken. Please choose another.';
                $error_fields[] = 'username';
            }
            $uq->close();
        }
        // If an email was supplied, it must also be unique among accounts.
        if ($error === '' && $email_val !== null && users_has_email_column($conn)) {
            $eq = $conn->prepare('SELECT id FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1');
            $eq->bind_param('s', $email_val);
            $eq->execute();
            if ($eq->get_result()->fetch_assoc()) {
                $error = 'That email address is already registered to an account. Please use another.';
                $error_fields[] = 'email';
            }
            $eq->close();
        }
    }

    if ($error === '') {
        // Insert the resident profile, and (optionally) a linked login account,
        // atomically so we never end up with half a record.
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO residents
                 (barangay_id, last_name, first_name, middle_name, sex, age, birth_date,
                  civil_status, occupation, contact_no, email, address, photo, is_pwd, is_student)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if ($stmt === false) { throw new RuntimeException('prepare residents failed: ' . $conn->error); }
            $stmt->bind_param(
                'issssisssssssss',
                $barangay_id, $last_name, $first_name, $middle_name, $sex, $age, $birth_date,
                $civil_status, $occupation, $contact_no, $email_val, $address, $photo,
                $is_pwd, $is_student
            );
            if (!$stmt->execute()) { throw new RuntimeException('insert resident failed: ' . $stmt->error); }
            $resident_id = (int)$conn->insert_id;
            $stmt->close();

            // Persist the optional IP / 4Ps classification (columns come from
            // database/update.sql — skipped entirely when they don't exist).
            if ($ip_col !== null || $fp_col !== null) {
                $setSql = ''; $setTypes = ''; $setVals = [];
                if ($ip_col !== null) { $setSql .= "`$ip_col`=?"; $setTypes .= $ip_int ? 'i' : 's'; $setVals[] = $is_ip; }
                if ($fp_col !== null) { $setSql .= ($setSql !== '' ? ',' : '') . "`$fp_col`=?"; $setTypes .= $fp_int ? 'i' : 's'; $setVals[] = $is_4ps; }
                $setVals[] = $resident_id;
                $cs = $conn->prepare("UPDATE residents SET $setSql WHERE id=?");
                if ($cs === false) { throw new RuntimeException('prepare classification failed: ' . $conn->error); }
                $cs->bind_param($setTypes . 'i', ...$setVals);
                if (!$cs->execute()) { throw new RuntimeException('save classification failed: ' . $cs->error); }
                $cs->close();
            }
            // Bind the resident to their household inside the same transaction:
            // a new household gets this resident as its head; an existing one is
            // joined as a plain member unless headship was requested (single-head
            // rule re-checked here so two people can never claim the same post).
            if ($household_mode === 'new') {
                $num = ($household_no !== '') ? $household_no : (string)$new_household_no;
                $new_hh_id = household_create($conn, $barangay_id, $num, $address);
                household_assign($conn, $resident_id, $new_hh_id);
                if (!household_claim_head($conn, $new_hh_id, $resident_id)) {
                    throw new RuntimeException('could not register the head of the new household');
                }
            } elseif ($household_mode === 'existing') {
                household_assign($conn, $resident_id, $hh_form_id);
                if ($is_head && !household_claim_head($conn, $hh_form_id, $resident_id)) {
                    throw new RuntimeException('that household already has a living head - uncheck "Head of this household" or transfer headship first');
                }
            }

            if ($make_account) {
                // Auto-generate a password; it is shown once to the secretary.
                $generated_password = bin2hex(random_bytes(5)); // 10-char hex
                $hash = password_hash($generated_password, PASSWORD_DEFAULT);
                $role = 'resident';
                $status = 'active';

                $us = $conn->prepare(
                    "INSERT INTO users (username, email, password, full_name, role, barangay_id, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                if ($us === false) { throw new RuntimeException('prepare users failed: ' . $conn->error); }
                $full_name = trim($first_name . ' ' . $last_name);
                $us->bind_param('sssssis', $username, $email_val, $hash, $full_name, $role, $barangay_id, $status);
                if (!$us->execute()) { throw new RuntimeException('insert user failed: ' . $us->error); }
                $new_user_id = (int)$conn->insert_id;
                $us->close();

                // Link the profile to the account.
                $lk = $conn->prepare('UPDATE residents SET user_id=? WHERE id=?');
                $lk->bind_param('ii', $new_user_id, $resident_id);
                if (!$lk->execute()) { throw new RuntimeException('link user failed: ' . $lk->error); }
                $lk->close();

                log_access('user_create', 'user', $new_user_id);
            }

            $conn->commit();
            log_access('resident_added', 'resident', $resident_id);

            if ($make_account) {
                // Try to email the credentials to the resident; fall back to
                // showing them on screen if there's no email or the send fails.
                $emailed = false;
                if ($email_val !== null) {
                    $full_name = trim($first_name . ' ' . $last_name);
                    $emailed = mail_send_credentials($email_val, $full_name, $username, $generated_password);
                }

                if ($emailed) {
                    flash('ok', 'Resident added and a login account was created. '
                        . 'The username and temporary password were emailed to ' . $email_val . '.');
                } else {
                    // No email on file, email not configured, or send failed:
                    // show the credentials once so the secretary can share them.
                    $why = ($email_val === null)
                        ? 'No email on file, so'
                        : 'The email could not be sent, so';
                    flash('ok', 'Resident added and a login account was created. ' . $why
                        . ' please share these manually - Username: ' . $username
                        . '  |  Temporary password: ' . $generated_password . ' (shown once).');
                }
            } else {
                flash('ok', 'Resident added successfully.');
            }
            redirect(url('pages/residents.php'));
        } catch (Throwable $ex) {
            $conn->rollback();
            $error = 'Failed to save resident: ' . $ex->getMessage();
        }
    }
}

$page_scripts = ['assets/js/resident_age.js'];

$barangays = $conn->query("SELECT * FROM barangays ORDER BY barangay_name");

// Household choices for the "Join an existing household" mode. Admins see every
// barangay's households (grouped in the select); a secretary sees only theirs.
// On a fresh (GET) form, pre-fill the next household number for the secretary.
$hh_groups = [];
if ($_SESSION['role'] === 'admin') {
    $bs = $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name');
    while ($bs && $b = $bs->fetch_assoc()) {
        $hh_groups[(int)$b['id']] = ['name' => $b['barangay_name'], 'items' => households_options($conn, (int)$b['id'])];
    }
} else {
    $ownBid  = (int)($_SESSION['barangay_id'] ?? 0);
    $ownName = (string)($_SESSION['barangay_name'] ?? 'My Barangay');
    if ($hn = $conn->prepare('SELECT barangay_name FROM barangays WHERE id = ? LIMIT 1')) {
        $hn->bind_param('i', $ownBid);
        $hn->execute();
        $hr = $hn->get_result()->fetch_assoc();
        $hn->close();
        if ($hr) { $ownName = $hr['barangay_name']; }
    }
    if ($ownBid > 0) {
        $hh_groups[$ownBid] = ['name' => $ownName, 'items' => households_options($conn, $ownBid)];
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $old['household_no'] === '') {
        $old['household_no'] = household_next_no($conn, $ownBid);
    }
}

include BASE_PATH . '/partials/header.php';
?>



<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert" data-auto-dismiss="5000"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" id="residentForm" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">

            <?php if ($_SESSION['role'] === 'admin'): ?>
                <div>
                    <label for="barangay_id">Barangay</label>
                    <select id="barangay_id" name="barangay_id" required<?= in_array('barangay_id', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                        <option value="">Select barangay</option>
                        <?php while ($b = $barangays->fetch_assoc()): ?>
                            <option value="<?= (int)$b['id'] ?>"<?= (int)$old['barangay_id'] === (int)$b['id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div>
                <label for="username">Login Username (optional)</label>
                <input id="username" name="username" value="<?= e($old['username']) ?>" maxlength="100" autocomplete="off" placeholder="Leave blank for a profile-only record"<?= in_array('username', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                <p class="field-hint">Fill this in to also create a login account for the resident. A temporary password is generated and shown once after saving.</p>
            </div>

            <div>
                <label for="last_name">Last Name</label>
                <input id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required<?= in_array('last_name', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
            </div>

            <div>
                <label for="first_name">First Name</label>
                <input id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required<?= in_array('first_name', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
            </div>

            <div>
                <label for="middle_name">Middle Name</label>
                <input id="middle_name" name="middle_name" value="<?= e($old['middle_name']) ?>">
            </div>

            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="<?= e($old['email']) ?>" maxlength="255" autocomplete="email" placeholder="Used for password recovery (optional for walk-ins)"<?= in_array('email', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
            </div>

            <div>
                <label for="sex">Sex</label>
                <select id="sex" name="sex">
                    <option value=""<?= $old['sex'] === '' ? ' selected' : '' ?>>Select</option>
                    <option value="Male"<?= $old['sex'] === 'Male' ? ' selected' : '' ?>>Male</option>
                    <option value="Female"<?= $old['sex'] === 'Female' ? ' selected' : '' ?>>Female</option>
                </select>
            </div>

            <div>
                <label for="age">Age</label>
                <input id="age" type="number" min="0" max="150" name="age" value="<?= e($old['age']) ?>" readonly title="Automatically computed from the birth date">
            </div>

            <div>
                <label for="birth_date">Birth Date</label>
                <?php
                // Bound the date picker to a realistic range so a 5-digit year
                // (e.g. "20001") can't be entered. Oldest allowed: 120 years ago;
                // latest: today.
                $bd_min = date('Y-m-d', strtotime('-120 years'));
                $bd_max = date('Y-m-d');
                ?>
                <input id="birth_date" type="date" name="birth_date" value="<?= e($old['birth_date']) ?>"
                       min="<?= e($bd_min) ?>" max="<?= e($bd_max) ?>"<?= in_array('birth_date', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
            </div>

            <div>
                <label for="civil_status">Civil Status</label>
                <select id="civil_status" name="civil_status">
                    <option value=""<?= $old['civil_status'] === '' ? ' selected' : '' ?>>Select</option>
                    <option value="Single"<?= $old['civil_status'] === 'Single' ? ' selected' : '' ?>>Single</option>
                    <option value="Married"<?= $old['civil_status'] === 'Married' ? ' selected' : '' ?>>Married</option>
                    <option value="Widowed"<?= $old['civil_status'] === 'Widowed' ? ' selected' : '' ?>>Widowed</option>
                    <option value="Separated"<?= $old['civil_status'] === 'Separated' ? ' selected' : '' ?>>Separated</option>
                </select>
            </div>

            <div>
                <label for="occupation">Occupation</label>
                <input id="occupation" name="occupation" value="<?= e($old['occupation']) ?>">
            </div>

            <div>
                <label for="contact_no">Contact No.</label>
                <input id="contact_no" name="contact_no" value="<?= e($old['contact_no']) ?>">
            </div>

            <div>
                <label for="address">Purok / Zone</label>
                <select id="address" name="address">
                    <option value=""<?= $old['address'] === '' ? ' selected' : '' ?>>Select</option>
                    <?php $zone_opts = ['Zone 1','Zone 2','Zone 3','Zone 4','Zone 5','Zone 6','Zone 7']; ?>
                    <?php if ($old['address'] !== '' && !in_array($old['address'], $zone_opts, true)): ?>
                    <option value="<?= e($old['address']) ?>" selected><?= e($old['address']) ?></option>
                    <?php endif; ?>
                    <?php foreach ($zone_opts as $zo): ?>
                    <option value="<?= $zo ?>"<?= $old['address'] === $zo ? ' selected' : '' ?>><?= $zo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <span class="field-label">Household</span>
                <div style="display:grid;gap:6px">
                    <label><input type="radio" name="household_mode" value="new"<?= $old['household_mode'] === 'new' ? ' checked' : '' ?>> Create a new household</label>
                    <label><input type="radio" name="household_mode" value="existing"<?= $old['household_mode'] === 'existing' ? ' checked' : '' ?>> Join an existing household</label>
                    <label><input type="radio" name="household_mode" value="none"<?= $old['household_mode'] === 'none' ? ' checked' : '' ?>> No household yet (assign later)</label>
                </div>
            </div>

            <div id="hh_new_fields"<?= $old['household_mode'] !== 'new' ? ' hidden' : '' ?>>
                <label for="household_no">New Household Number</label>
                <input id="household_no" name="household_no" maxlength="50" value="<?= e($old['household_no']) ?>" placeholder="HH-XXXX"<?= in_array('household_no', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                <p class="field-hint">Leave blank to auto-generate the next number for this barangay. This resident will be recorded as the <strong>head of the family</strong> of the new household.</p>
            </div>

            <div id="hh_existing_fields"<?= $old['household_mode'] !== 'existing' ? ' hidden' : '' ?>>
                <label for="household_id">Household</label>
                <select id="household_id" name="household_id"<?= in_array('household_id', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
                    <option value="0">Select household</option>
                    <?php $use_groups = (count($hh_groups) > 1); foreach ($hh_groups as $g): if (!$g['items']) { continue; } ?>
                        <?php if ($use_groups): ?><optgroup label="<?= e($g['name']) ?>"><?php endif; ?>
                        <?php foreach ($g['items'] as $it): $mem = (int)$it['members']; ?>
                        <option value="<?= (int)$it['id'] ?>"<?= (int)$old['household_id'] === (int)$it['id'] ? ' selected' : '' ?>><?= e($it['household_no']) ?> &mdash; <?= e($it['head_name'] ?: 'No head yet') ?> (<?= $mem ?> member<?= $mem === 1 ? '' : 's' ?>)</option>
                        <?php endforeach; ?>
                        <?php if ($use_groups): ?></optgroup><?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <label style="margin-top:6px;display:inline-flex;gap:6px;align-items:center">
                    <input type="checkbox" id="is_head" name="is_head" value="1"<?= $old['is_head'] ? ' checked' : '' ?><?= in_array('is_head', $error_fields, true) ? ' class="is-invalid"' : '' ?>>
                    Head of this household
                </label>
                <p class="field-hint">Tick this only when the household has no living head yet (the list marks those as "No head yet").</p>
            </div>

            <div>
                <label for="photo">Resident Photo (optional)</label>
                <input id="photo" type="file" name="photo" accept="image/*"<?= in_array('photo', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
            </div>

            <div>
                <span class="field-label">Classification</span>
                <label><input type="checkbox" name="is_pwd" value="Yes"<?= $old['is_pwd'] ? ' checked' : '' ?>> Person with Disability (PWD)</label>
                <label><input type="checkbox" name="is_student" value="Yes"<?= $old['is_student'] ? ' checked' : '' ?>> Student</label>
                <?php if ($ip_col !== null): ?>
                <label><input type="checkbox" name="is_ip" value="Yes"<?= $old['is_ip'] ? ' checked' : '' ?>> Indigenous People (IP)</label>
                <?php endif; ?>
                <?php if ($fp_col !== null): ?>
                <label><input type="checkbox" name="is_4ps" value="Yes"<?= $old['is_4ps'] ? ' checked' : '' ?>> 4Ps Beneficiary</label>
                <?php endif; ?>
            </div>

        </div>
        <br>
        <div class="toolbar">
            <button type="submit" class="btn">Save Resident</button>
            <a class="btn btn--ghost" href="<?= e(url('pages/residents.php')) ?>">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    // Show only the fields for the selected household mode.
    var radios = document.querySelectorAll('input[name="household_mode"]');
    var newBox = document.getElementById('hh_new_fields');
    var exBox  = document.getElementById('hh_existing_fields');
    function sync() {
        var mode = 'new';
        radios.forEach(function (r) { if (r.checked) { mode = r.value; } });
        if (newBox) { newBox.hidden = (mode !== 'new'); }
        if (exBox)  { exBox.hidden  = (mode !== 'existing'); }
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>

<?php include BASE_PATH . '/partials/footer.php'; ?>
