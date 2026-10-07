<?php
require_once __DIR__ . '/../config/config.php';
if (is_logged_in()) { redirect(url('pages/dashboard.php')); }









$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$row   = null;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $lookup = hash('sha256', $token);
    



    $stmt = $conn->prepare(
        'SELECT t.id, t.user_id, t.expires_at, t.used_at, u.username,
                (t.expires_at > NOW()) AS still_valid
           FROM password_reset_tokens t
           JOIN users u ON u.id = t.user_id
          WHERE t.token_hash = ? LIMIT 1'
    );
    $stmt->bind_param('s', $lookup);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

 
$invalid_msg = 'This password reset link is invalid or has already been used.';
$token_error = '';
if ($token === '' || !$row) {
    $token_error = $invalid_msg;
} elseif ($row['used_at'] !== null) {
    $token_error = $invalid_msg;
} elseif ((int)$row['still_valid'] !== 1) {
    $token_error = 'This password reset link has expired. Please ask your administrator for a new one.';
}



if ($token_error !== '') {
    $error = $token_error;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['confirm_password'] ?? '');

    if ($token_error !== '') {
        $error = $token_error;
    } elseif ($p1 === '' || $p2 === '') {
        $error = 'Please complete all required fields.';
    } elseif ($p1 !== $p2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($p1) < 8
              || !preg_match('/[a-z]/', $p1)
              || !preg_match('/[A-Z]/', $p1)
              || !preg_match('/[0-9]/', $p1)) {
        $error = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter and a number.';
    } else {
        $hashed = password_hash($p1, PASSWORD_DEFAULT);

        $stmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->bind_param('si', $hashed, $row['user_id']);

        if ($stmt->execute()) {
            $stmt->close();

             
            $used = $conn->prepare(
                'UPDATE password_reset_tokens SET used_at = NOW()
                  WHERE user_id = ? AND used_at IS NULL'
            );
            $used->bind_param('i', $row['user_id']);
            $used->execute();
            $used->close();

             
            login_clear_failures($conn, $row['username']);

            redirect(url('auth/login.php?reset=1'));
        }
        $stmt->close();
        $error = 'We could not update your password right now. Please try again.';
    }
}

$page_title   = 'Reset password';
$auth_heading = 'Reset password';
$auth_sub     = $token_error === ''
    ? 'Choose a new password for your account.'
    : 'This link cannot be used.';
include BASE_PATH . '/partials/auth_top.php';
?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($token_error === ''): ?>
<form method="post" class="auth__form">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="field">
        <label for="username">Account</label>
        <input id="username" type="text" value="<?= e($row['username']) ?>" readonly>
    </div>

    <div class="field">
        <label for="password">New Password</label>
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
        <label for="confirm_password">Confirm New Password</label>
        <div class="input-affix">
            <input id="confirm_password" type="password" name="confirm_password" required
                   autocomplete="new-password" data-match-to="#password"
                   aria-describedby="confirm-hint" <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
            <?= auth_eye_button('confirm_password') ?>
        </div>
        <p class="pw-status" id="confirm-hint" data-match-msg aria-live="polite"></p>
    </div>

    <button class="btn btn--block" type="submit">Save new password</button>
</form>
<?php endif; ?>

<p class="auth__alt auth__alt--center">
    <a class="auth__link" href="<?= e(url('auth/login.php')) ?>">Back to sign in</a>
</p>
<?php include BASE_PATH . '/partials/auth_bottom.php';