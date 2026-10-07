<?php
require_once __DIR__ . '/../config/config.php'; require_once __DIR__ . '/../includes/mailer.php'; require_staff(); $id=(int)($_GET['id']??0);$page_title='Edit Resident';
$s=$conn->prepare('SELECT r.*, u.email AS account_email, u.id AS account_id FROM residents r LEFT JOIN users u ON u.id = r.user_id WHERE r.id=? AND r.deleted_at IS NULL LIMIT 1');
$s->bind_param('i',$id);$s->execute();
$r=$s->get_result()->fetch_assoc();$s->close();if(!$r)die('Resident not found.');

 
require_record_access($r['barangay_id'], null, 'resident', $id);

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
        $email        = trim($_POST['email'] ?? '');
        $sex          = trim($_POST['sex'] ?? '');
        $age          = (int)($_POST['age'] ?? 0);
        $birth_date   = trim($_POST['birth_date'] ?? '');
        $civil_status = trim($_POST['civil_status'] ?? '');
        $occupation   = trim($_POST['occupation'] ?? '');
        $contact_no   = trim($_POST['contact_no'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $household_no = trim($_POST['household_no'] ?? '');
         
        $is_pwd       = isset($_POST['is_pwd']) ? 'Yes' : 'No';
        $is_student   = isset($_POST['is_student']) ? 'Yes' : 'No';

        $birth_date_v = ($birth_date !== '') ? $birth_date : null;

        

        $account_id = (int)($r['account_id'] ?? 0);
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
            $error = 'Please enter a valid email address.';
        } elseif ($email !== '' && $account_id > 0 && users_has_email_column($conn)) {
            $ck = $conn->prepare('SELECT id FROM users WHERE email=? AND id<>? AND deleted_at IS NULL LIMIT 1');
            if ($ck) {
                $ck->bind_param('si', $email, $account_id);
                $ck->execute();
                $dup = (bool)$ck->get_result()->fetch_assoc();
                $ck->close();
                if ($dup) { $error = 'That email address is already registered. Please use another.'; }
            }
        }

        if ($error !== '') {
             
        } else {
        $stmt=$conn->prepare("UPDATE residents SET photo=?,last_name=?,first_name=?,middle_name=?,
        sex=?,age=?,birth_date=?,civil_status=?,occupation=?,contact_no=?,address=?,is_pwd=?,
        is_student=? ,household_no=?  WHERE id=?");

        



        $stmt->bind_param('sssssissssssssi',
            $photo, $last_name, $first_name, $middle_name, $sex,
            $age, $birth_date_v, $civil_status, $occupation, $contact_no,
            $address, $is_pwd, $is_student, $household_no, $id);

        if($stmt->execute()) {
            $stmt->close();
             
            if ($account_id > 0 && users_has_email_column($conn)) {
                $ue = $conn->prepare('UPDATE users SET email=? WHERE id=? LIMIT 1');
                if ($ue) {
                    $ue->bind_param('si', $email, $account_id);
                    $ue->execute();
                    $ue->close();
                }
            }
            log_access('resident_edited', 'resident', $id);
            redirect(url('pages/residents.php'));
        }
        $stmt->close();
        $error = 'Could not save the resident record. Please try again.';
        }  
    }

     
    $r['last_name']    = $last_name;
    $r['first_name']   = $first_name;
    $r['middle_name']  = $middle_name;
    $r['account_email'] = $email;
    $r['sex']          = $sex;
    $r['age']          = $age;
    $r['birth_date']   = $birth_date;
    $r['civil_status'] = $civil_status;
    $r['occupation']   = $occupation;
    $r['contact_no']   = $contact_no;
    $r['address']      = $address;
    $r['household_no'] = $household_no;
    $r['is_pwd']       = $is_pwd;
    $r['is_student']   = $is_student;
}

include BASE_PATH . '/partials/header.php';?>
<h2>Edit Resident</h2>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="card">
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="form-grid">
    <div><label for="last_name">Last Name</label><input id="last_name" name="last_name" value="<?=e($r['last_name'])?>" required></div>
    <div><label for="first_name">First Name</label><input id="first_name" name="first_name" value="<?=e($r['first_name'])?>" required></div>
    <div><label for="middle_name">Middle Name</label><input id="middle_name" name="middle_name" value="<?=e($r['middle_name']) ?>"></div>
    <div><label for="email">Email address</label><input id="email" type="email" name="email" maxlength="255" autocomplete="email" value="<?=e($r['account_email'] ?? '') ?>" placeholder="Used for password recovery"></div>
    <div>
        <label for="sex">Sex</label>
        <select id="sex" name="sex">
            <option value="">Select</option>
            <option value="Male"<?= $r['sex'] === 'Male' ? ' selected' : '' ?>>Male</option>
            <option value="Female"<?= $r['sex'] === 'Female' ? ' selected' : '' ?>>Female</option>
        </select>
    </div>
    <div><label for="age">Age</label><input id="age" type="number" min="0" max="150" name="age" value="<?=e($r['age']) ?>"></div>
    <div><label for="birth_date">Birth Date</label><input id="birth_date" type="date" name="birth_date" value="<?=e($r['birth_date']) ?>"></div>
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
    <div><label for="occupation">Occupation</label><input id="occupation" name="occupation" value="<?=e($r['occupation']) ?>"></div>
    <div><label for="contact_no">Contact No.</label><input id="contact_no" name="contact_no" value="<?=e($r['contact_no']) ?>"></div>
    <div><label for="address">Purok/Zone</label><input id="address" name="address" value="<?=e($r['address']) ?>"></div>
    <div><label for="household_no">Household Number</label><input id="household_no" type="text" name="household_no" value="<?=e($r['household_no']) ?>"></div>

    <div>
        <label for="photo">Resident Photo</label>
        <input id="photo" type="file" name="photo" accept="image/*">
        <?php if (!empty($r['photo']) && @file_exists(BASE_PATH . '/' . $r['photo'])): ?>
            <p class="field-hint">Current photo on file. Leave blank to keep it.</p>
            <img src="<?= e(url($r['photo'])) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:10px;display:block;margin-top:8px">
        <?php else: ?>
            <p class="field-hint">No photo on file yet.</p>
        <?php endif; ?>
    </div>

    <div>
        <span class="field-label">Classification</span>
        <label><input type="checkbox" name="is_pwd" value="Yes"<?= $r['is_pwd'] === 'Yes' ? ' checked' : '' ?>> PWD</label>
        <label><input type="checkbox" name="is_student" value="Yes"<?= $r['is_student'] === 'Yes' ? ' checked' : '' ?>> Student</label>
    </div>

</div><br><button class="btn" type="submit">Update</button></form></div><?php include BASE_PATH . '/partials/footer.php';

