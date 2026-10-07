<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
if (is_logged_in()) { redirect(url('pages/dashboard.php')); }









$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!users_has_email_column($conn)) {
         
        error_log('[forgot_password] skipped: users.email column missing.');
        $error = 'Password recovery is currently unavailable. Please contact the administrator.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, full_name, email, status FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1"
        );
        if (!$stmt) {
            error_log('[forgot_password] prepare failed.');
            $error = 'We could not process your request right now. Please try again.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $found = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$found) {
                $error = 'No account found with that email address.';
            } elseif (($found['status'] ?? '') !== 'active') {
                 
                $error = auth_status_message($found['status'] ?? '');
            } elseif (trim((string)($found['email'] ?? '')) === '') {
                $error = 'No account found with that email address.';
            } elseif (!mail_is_configured()) {
                 
                error_log('[forgot_password] skipped: SMTP not configured (see config.php MAIL_*).');
                $error = 'Email service is not configured. Please contact the administrator.';
            } else {
                

                $plain = reset_issue_token($conn, (int)$found['id'], null);
                if ($plain === '') {
                    error_log('[forgot_password] token issuance failed.');
                    $error = 'We could not create a reset link right now. Please try again.';
                } else {
                    $url    = mail_build_reset_url($plain);
                    $mailed = mail_send_reset((string)$found['email'], (string)($found['full_name'] ?? ''), $url);
                    if (!$mailed) {
                         
                        $error = 'We could not send the reset email. Please check the address and try again.';
                    } else {
                        $success = 'Password-reset link sent to ' . $email . '. Please check your email (including spam) and follow the link within 1 hour.';
                    }
                }
            }
        }
    }
}

$page_title   = 'Forgot password';
$auth_heading = 'Forgot password';
$auth_sub     = 'Enter your account email to receive a password-reset link.';
include BASE_PATH . '/partials/auth_top.php';
?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php elseif ($success !== ''): ?>
    <div class="alert alert-success" role="status">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<form method="post" class="auth__form">
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" required autofocus
               autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"
               <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
    </div>

    <button class="btn btn--block" type="submit">Send reset link</button>
</form>

<p class="auth__alt auth__alt--center">
    <a class="auth__link" href="<?= e(url('auth/login.php')) ?>">Back to sign in</a>
</p>

<p class="auth__foot">
    Enter the email address on your account. If it matches an active account,
    a reset link will be sent. Reset links expire 1 hour after they are sent and can be used only once.
</p>
<?php include BASE_PATH . '/partials/auth_bottom.php';