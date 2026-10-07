<?php
require_once __DIR__ . '/../config/config.php';
if (is_logged_in()) { redirect(role_home($_SESSION['role'] ?? '')); }

$error   = '';
$blocked = isset($_GET['blocked']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $u  = trim($_POST['username'] ?? '');
    $p  = (string)($_POST['password'] ?? '');
    $ip = client_ip();

     
    if ($u === '' || $p === '') {
        $error = 'Please complete all required fields.';

     
    } elseif (login_is_throttled($conn, $u, $ip)) {
        $error = 'Too many failed sign-in attempts. Please wait a few minutes and try again.';

    } else {
        $stmt = $conn->prepare(
            'SELECT id,username,password,full_name,role,barangay_id,status
             FROM users WHERE username=? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($p, $user['password'])) {
            


            login_record_failure($conn, $u, $ip);
            $error = 'Invalid username or password.';

        } elseif ($user['status'] !== 'active') {
            

            login_clear_failures($conn, $u);
            $error = auth_status_message($user['status']);

        } else {
            login_clear_failures($conn, $u);
             
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['full_name']   = $user['full_name'];
            $_SESSION['role']        = $user['role'];
            $_SESSION['barangay_id'] = $user['barangay_id'];
            $_SESSION['status']      = $user['status'];
             
            redirect(role_home($user['role']));
        }
    }
}

$page_title   = 'Sign in';
$auth_heading = 'Sign in';
$auth_sub     = 'Enter your account credentials to continue.';
include BASE_PATH . '/partials/auth_top.php';
?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert" data-auto-dismiss="5000"><?= e($error) ?></div>
<?php elseif (isset($_GET['registered'])): ?>
    <div class="alert alert-success" role="status" data-auto-dismiss="5000">Your account has been created and is awaiting administrator approval. You can sign in once it has been approved.</div>
<?php elseif (isset($_GET['reset'])): ?>
    <div class="alert alert-success" role="status" data-auto-dismiss="5000">Your password has been changed. Please sign in with your new password.</div>
<?php elseif ($blocked): ?>
    <div class="alert alert-danger" role="alert" data-auto-dismiss="5000">Your account is currently inactive. Please contact the administrator.</div>
<?php endif; ?>

<form method="post" class="auth__form">
    <?= csrf_field() ?>

    <div class="field">
        <label for="username">Username</label>
        <input id="username" type="text" name="username" required autofocus
               autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"
               <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <div class="input-affix">
            <input id="password" type="password" name="password" required
                   autocomplete="current-password"
                   <?= $error !== '' ? 'aria-invalid="true"' : '' ?>>
            <button class="affix-btn" type="button" data-password-toggle="#password"
                    aria-label="Show password" aria-pressed="false" title="Show password">
                <svg class="affix-btn__ico affix-btn__ico--show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="affix-btn__ico affix-btn__ico--hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                    <line x1="2" x2="22" y1="2" y2="22"></line>
                </svg>
            </button>
        </div>
    </div>

    <button class="btn btn--block" type="submit">Sign in</button>

    <div class="auth__row">
        <a class="auth__link" href="<?= e(url('auth/forgot_password.php')) ?>">Forgot password?</a>
    </div>
</form>

<p class="auth__alt">Don't have an account? <a class="auth__link" href="<?= e(url('auth/register.php')) ?>">Register</a></p>
<p class="auth__foot">Authorized personnel only. Access is restricted to registered barangay accounts.</p>
<?php include BASE_PATH . '/partials/auth_bottom.php';
