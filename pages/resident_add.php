<?php








require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_staff();

$page_title = 'New Resident';
$page_crumb = 'New Resident';
$error = '';
$error_fields = [];

$old = [
    'barangay_id'  => ($_SESSION['role'] === 'admin') ? 0 : (int)($_SESSION['barangay_id'] ?? 0),
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
    'household_no' => '',
    'email'        => '',
    'is_pwd'       => false,
    'is_student'   => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $barangay_id = ($_SESSION['role'] === 'admin')
        ? (int)($_POST['barangay_id'] ?? 0)
        : (int)($_SESSION['barangay_id'] ?? 0);

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
    $household_no = trim($_POST['household_no'] ?? '');
    

    $email = trim($_POST['email'] ?? '');
     
    $is_pwd       = isset($_POST['is_pwd']) ? 'Yes' : 'No';
    $is_student   = isset($_POST['is_student']) ? 'Yes' : 'No';
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
        'household_no' => $household_no,
        'email'        => $email,
        'is_pwd'       => ($is_pwd === 'Yes'),
        'is_student'   => ($is_student === 'Yes'),
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

    if ($error === '' && $email !== '' && users_has_email_column($conn)) {
        $ck = $conn->prepare('SELECT id FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1');
        if ($ck) {
            $ck->bind_param('s', $email);
            $ck->execute();
            $dup = (bool)$ck->get_result()->fetch_assoc();
            $ck->close();
            if ($dup) { $error = 'That email address is already registered. Please use another.'; $error_fields = ['email']; }
        }
    }

    if ($error === '') {
        $stmt = $conn->prepare(
            "INSERT INTO residents
             (barangay_id, last_name, first_name, middle_name, sex, age, birth_date,
              civil_status, occupation, contact_no, address, photo, is_pwd, is_student, household_no)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt === false) { die('Prepare Error: ' . $conn->error); }

        $stmt->bind_param(
            'issssisssssssss',
            $barangay_id,
            $last_name,
            $first_name,
            $middle_name,
            $sex,
            $age,
            $birth_date,
            $civil_status,
            $occupation,
            $contact_no,
            $address,
            $photo,
            $is_pwd,
            $is_student,
            $household_no
        );

        if ($stmt->execute()) {
            $stmt->close();
            flash('ok', 'Resident added successfully.');
            redirect(url('pages/residents.php'));
        }
        $error = 'Failed to save resident: ' . $stmt->error;
        $stmt->close();
    }
}

$page_scripts = ['assets/js/resident_age.js'];

$barangays = $conn->query("SELECT * FROM barangays ORDER BY barangay_name");
include BASE_PATH . '/partials/header.php';
?>



<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert" data-auto-dismiss="5000"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
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
                <input id="address" name="address" value="<?= e($old['address']) ?>">
            </div>

            <div>
                <label for="household_no">Household Number</label>
                <input id="household_no" name="household_no" value="<?= e($old['household_no']) ?>">
            </div>

            <div>
                <label for="photo">Resident Photo (optional)</label>
                <input id="photo" type="file" name="photo" accept="image/*"<?= in_array('photo', $error_fields, true) ? ' class="is-invalid" aria-invalid="true"' : '' ?>>
            </div>

            <div>
                <span class="field-label">Classification</span>
                <label><input type="checkbox" name="is_pwd" value="Yes"<?= $old['is_pwd'] ? ' checked' : '' ?>> Person with Disability (PWD)</label>
                <label><input type="checkbox" name="is_student" value="Yes"<?= $old['is_student'] ? ' checked' : '' ?>> Student</label>
            </div>

        </div>
        <br>
        <div class="toolbar">
            <button type="submit" class="btn">Save Resident</button>
            <a class="btn btn--ghost" href="<?= e(url('pages/residents.php')) ?>">Cancel</a>
        </div>
    </form>
</div>

<?php include BASE_PATH . '/partials/footer.php'; ?>
