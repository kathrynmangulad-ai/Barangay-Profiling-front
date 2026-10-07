<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
if (is_logged_in()) { redirect(role_home($_SESSION['role'] ?? '')); }

$error = '';
$old   = ['first_name' => '', 'last_name' => '', 'middle_name' => '', 'username' => '', 'email' => '', 'barangay_id' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $full_name   = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $barangay_id = (int)($_POST['barangay_id'] ?? 0);
    $password    = (string)($_POST['password'] ?? '');
    $confirm     = (string)($_POST['confirm_password'] ?? '');

    $old = ['first_name' => $first_name, 'last_name' => $last_name, 'middle_name' => $middle_name, 'username' => $username, 'email' => $email, 'barangay_id' => $barangay_id];

    if ($first_name === '' || $last_name === '' || $username === '' || $email === '' || $barangay_id <= 0 || $password === '' || $confirm === '') {
        $error = 'Please complete all required fields.';

    } elseif (strlen($first_name) > 100 || strlen($middle_name) > 100 || strlen($last_name) > 255 || strlen($full_name) > 150) {
        $error = 'One of the name fields is too long. Allowed: 100 characters for first and middle name, 255 for last name.';

    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
        $error = 'Username must be 3-100 characters and may only contain letters, numbers, dots, dashes and underscores.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $error = 'Please enter a valid email address.';

    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';

    } elseif (strlen($password) < 8
              || !preg_match('/[a-z]/', $password)
              || !preg_match('/[A-Z]/', $password)
              || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter and a number.';

    } else {
         
        $bs = $conn->prepare('SELECT id FROM barangays WHERE id=? LIMIT 1');
        $bs->bind_param('i', $barangay_id);
        $bs->execute();
        $barangay_ok = (bool)$bs->get_result()->fetch_assoc();
        $bs->close();

        if (!$barangay_ok) {
            $error = 'Please select a valid barangay.';

        } else {
            $dup = $conn->prepare('SELECT id FROM users WHERE username=? AND deleted_at IS NULL LIMIT 1');
            $dup->bind_param('s', $username);
            $dup->execute();
            $taken = (bool)$dup->get_result()->fetch_assoc();
            $dup->close();

            if ($taken) {
                $error = 'That username is already taken. Please choose another.';

            } else {
                 
                $email_taken = false;
                if (users_has_email_column($conn)) {
                    $edup = $conn->prepare('SELECT id FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1');
                    if ($edup) {
                        $edup->bind_param('s', $email);
                        $edup->execute();
                        $email_taken = (bool)$edup->get_result()->fetch_assoc();
                        $edup->close();
                    }
                }
                if ($email_taken) {
                    $error = 'That email address is already registered. Please use another.';
                } else {
                














                $role   = 'resident';
                $status = 'pending';
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                 
                $first = $first_name;
                $last  = $last_name;
                $mid   = $middle_name;

                




                $conn->begin_transaction();
                try {
                    



                    $hasEmailCol = users_has_email_column($conn);
                    if ($hasEmailCol) {
                        $stmt = $conn->prepare(
                            'INSERT INTO users (username, email, password, full_name, role, barangay_id, status)
                             VALUES (?,?,?,?,?,?,?)'
                        );
                        $stmt->bind_param('sssssis', $username, $email, $hashed, $full_name, $role, $barangay_id, $status);
                    } else {
                        $stmt = $conn->prepare(
                            'INSERT INTO users (username, password, full_name, role, barangay_id, status)
                             VALUES (?,?,?,?,?,?)'
                        );
                        $stmt->bind_param('ssssis', $username, $hashed, $full_name, $role, $barangay_id, $status);
                    }
                    $okUser = $stmt->execute();
                    $stmt->close();

                    if (!$okUser) { throw new RuntimeException('user insert failed'); }

                    $uid = (int)$conn->insert_id;

                    

                    $age = 0;
                    $rs  = $conn->prepare(
                        'INSERT INTO residents
                            (barangay_id, last_name, first_name, middle_name, age, is_pwd, is_student, user_id)
                         VALUES (?,?,?,?,?,?,?,?)'
                    );
                    $isPwd = 'No'; $isStu = 'No';
                    $rs->bind_param('issssssi', $barangay_id, $last, $first, $mid, $age, $isPwd, $isStu, $uid);
                    $okRes = $rs->execute();
                    $rs->close();

                    if (!$okRes) { throw new RuntimeException('resident insert failed'); }

                    $conn->commit();

                    







                    if (function_exists('mail_send_registration')) {
                        $brgy_name = '';
                        if ($bq = $conn->prepare('SELECT barangay_name FROM barangays WHERE id=? LIMIT 1')) {
                            $bq->bind_param('i', $barangay_id);
                            $bq->execute();
                            $br = $bq->get_result()->fetch_assoc();
                            $bq->close();
                            $brgy_name = (string)($br['barangay_name'] ?? '');
                        }
                        mail_send_registration($email, $full_name, $username, $brgy_name);
                    }

                    redirect(url('auth/login.php?registered=1'));

                } catch (Throwable $e) {
                    $conn->rollback();
                    log_access('register_failed', 'user', null, 'denied');
                    $error = 'We could not create your account right now. Please try again.';
                }
                }  
            }  
        }  
    }  
}  

$barangays = $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name ASC');

$page_title   = 'Create account';
$auth_heading = 'Create account';
$auth_sub     = 'Register a resident account. It will be reviewed by your barangay secretary before you can sign in.';
include BASE_PATH . '/partials/auth_top.php';
?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert" data-auto-dismiss="5000"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="auth__form auth__form--wide">
    <?= csrf_field() ?>

    <div class="field">
        <label for="first_name">First Name</label>
        <input id="first_name" type="text" name="first_name" required maxlength="100"
               autocomplete="given-name" value="<?= e($old['first_name']) ?>"
               <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>

    </div>

    <div class="field">
        <label for="last_name">Last Name</label>
        <input id="last_name" type="text" name="last_name" required maxlength="255"
               autocomplete="family-name" value="<?= e($old['last_name']) ?>"
               <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
    </div>

    <div class="field">
        <label for="middle_name">Middle Name</label>
        <input id="middle_name" type="text" name="middle_name" maxlength="100"
               autocomplete="additional-name" value="<?= e($old['middle_name']) ?>"
               <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
    </div>

    <div class="field">
        <label for="username">Username</label>
        <input id="username" type="text" name="username" required minlength="3" maxlength="100"
               autocomplete="username" value="<?= e($old['username']) ?>"
               aria-describedby="username-hint" <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
        <p class="field-hint" id="username-hint">3-100 characters: letters, numbers, dots, dashes or underscores.</p>
    </div>

    <div class="field">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" required maxlength="255"
               autocomplete="email" value="<?= e($old['email']) ?>"
               aria-describedby="email-hint" <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
        <p class="field-hint" id="email-hint">Used for password recovery. Must be unique.</p>
    </div>

    <div class="field">
        <label for="barangay_id">Barangay</label>
        <select id="barangay_id" name="barangay_id" required
                aria-describedby="barangay-hint" <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
            <option value="">Select your barangay</option>
            <?php while ($b = $barangays->fetch_assoc()): ?>
                <option value="<?= (int)$b['id'] ?>"<?= (int)$old['barangay_id'] === (int)$b['id'] ? ' selected' : '' ?>>
                    <?= e($b['barangay_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <p class="field-hint" id="barangay-hint">Your administrator will verify this before approving your account.</p>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <div class="input-affix">
            <input id="password" type="password" name="password" required minlength="8"
                   autocomplete="new-password" data-strength aria-describedby="pw-hints"
                   <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
            <?= auth_eye_button('password') ?>
        </div>
        <div class="pw-meter" aria-hidden="true"><span class="pw-meter__bar" data-strength-bar></span></div>
        <p class="pw-label" data-strength-label aria-live="polite">Password strength: —</p>
        <ul class="pw-reqs" id="pw-hints">
            <li data-req="len">At least 8 characters</li>
            <li data-req="lower">One lowercase letter</li>
            <li data-req="upper">One uppercase letter</li>
            <li data-req="num">One number</li>
        </ul>
    </div>

    <div class="field">
        <label for="confirm_password">Confirm Password</label>
        <div class="input-affix">
            <input id="confirm_password" type="password" name="confirm_password" required
                   autocomplete="new-password" data-match-to="#password"
                   aria-describedby="confirm-hint" <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
            <?= auth_eye_button('confirm_password') ?>
        </div>
        <p class="pw-status" id="confirm-hint" data-match-msg aria-live="polite"></p>
    </div>

    <button class="btn btn--block" type="submit">Create Account</button>
</form>

<p class="auth__alt">Already have an account? <a class="auth__link" href="<?= e(url('auth/login.php')) ?>">Sign in</a></p>
<?php include BASE_PATH . '/partials/auth_bottom.php';