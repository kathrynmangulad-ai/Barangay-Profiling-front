<?php
/* ---------------------------------------------------------------------------
 * Central configuration + bootstrap.
 *
 * Lives in /config. Defines the two anchors every file uses so nothing depends
 * on fragile relative paths:
 *
 *   BASE_PATH  filesystem root of the project (for require/include + file ops)
 *   BASE_URL   web root path of the app        (for links, assets, redirects)
 *   url($p)    helper: BASE_URL . '/' . $p     (build an app URL from any page)
 *
 * Secrets are read from /config/.env (gitignored); see .env.example. Each
 * setting falls back to a sensible local default so a fresh checkout boots.
 * ------------------------------------------------------------------------- */

// Project root is the parent of this /config directory.
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/includes/env.php';
env_load(BASE_PATH . '/config/.env');

// --- Database -------------------------------------------------------------
$host = (string) env('DB_HOST', '127.0.0.1');
$user = (string) env('DB_USER', 'root');
$pass = (string) env('DB_PASS', '');
$db   = (string) env('DB_NAME', 'brgy_system');

// --- Mail (SMTP) ----------------------------------------------------------
define('MAIL_HOST',      (string) env('MAIL_HOST', 'smtp-relay.brevo.com'));
define('MAIL_PORT',      (int)    env('MAIL_PORT', 587));
define('MAIL_USER',      (string) env('MAIL_USER', ''));
define('MAIL_PASS',      (string) env('MAIL_PASS', ''));
define('MAIL_FROM',      (string) env('MAIL_FROM', ''));
define('MAIL_FROM_NAME', (string) env('MAIL_FROM_NAME', 'Barangay System'));

// --- Application ----------------------------------------------------------
define('APP_BASE_URL', (string) env('APP_BASE_URL', 'http://localhost/barangay_system'));

/*
 * BASE_URL is the web path the app is served under (e.g. "/barangay_system"),
 * derived from APP_BASE_URL so links work no matter which subfolder a page
 * lives in. If APP_BASE_URL has no path, BASE_URL is "" (served at domain root).
 */
if (!defined('BASE_URL')) {
    $parsedPath = parse_url(APP_BASE_URL, PHP_URL_PATH);
    $basePath   = is_string($parsedPath) ? rtrim($parsedPath, '/') : '';
    define('BASE_URL', $basePath);
}

if (!function_exists('url')) {
    /** Build an absolute (app-root-relative) URL from a path like 'admin/users.php'. */
    function url($path = '') {
        $path = ltrim((string)$path, '/');
        return BASE_URL . '/' . $path;
    }
}

if (!function_exists('asset')) {
    /** Build a URL to a file under /assets, e.g. asset('css/style.css'). */
    function asset($path) {
        return url('assets/' . ltrim((string)$path, '/'));
    }
}

if (!function_exists('redirect_url')) {
    /** Redirect to an app path (relative to BASE_URL). */
    function redirect_url($path) { redirect(url($path)); }
}

$appTz = (string) env('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set($appTz);

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error . '. Check your /config/.env settings.');
}
$conn->set_charset('utf8mb4');
// Keep MySQL's session clock aligned with the app timezone (+08:00 for Manila).
$conn->query("SET time_zone = '+08:00'");

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: '.$url); exit; }
function is_logged_in() { return isset($_SESSION['user_id']); }

function require_login() {
    if (!is_logged_in()) { redirect(url('auth/login.php')); }

    if (($_SESSION['status'] ?? 'active') !== 'active') {
        auth_terminate_session();
        redirect(url('auth/login.php?blocked=1'));
    }

    global $conn;
    if ($conn instanceof mysqli) {
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT status FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row || $row['status'] !== 'active') {
                auth_terminate_session();
                redirect(url('auth/login.php?blocked=1'));
            }
        }
    }
}

 
function auth_terminate_session() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
}
function require_admin() { require_login(); if ($_SESSION['role'] !== 'admin') die('Access denied.'); }
function current_user_id() { return (int)($_SESSION['user_id'] ?? 0); }
function flash($key, $message = null) {
    if ($message !== null) { $_SESSION['flash'][$key] = $message; return; }
    $m = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $m;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}
function csrf_verify($token = null) {
    if ($token === null) { $token = $_POST['csrf_token'] ?? ''; }
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}
function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die('Invalid or expired security token. Please go back and try again.');
    }
}

function csrf_verify_get() {
    $token = $_GET['token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Invalid or expired security token. Please go back and try again.');
    }
}

define('LOGIN_MAX_PER_IDENTIFIER', 8);
define('LOGIN_MAX_PER_IP', 30);
define('LOGIN_WINDOW_MINUTES', 15);

function client_ip() {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_failure_count($conn, $by, $value) {
    $window = (int)LOGIN_WINDOW_MINUTES;
    if ($by === 'ip') {
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM login_attempts WHERE ip=? AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)");
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM login_attempts WHERE identifier=? AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)");
    }
    if (!$stmt) { return 0; }
    $stmt->bind_param('s', $value);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

function login_is_throttled($conn, $identifier, $ip) {
    if (login_failure_count($conn, 'identifier', $identifier) >= LOGIN_MAX_PER_IDENTIFIER) { return true; }
    return login_failure_count($conn, 'ip', $ip) >= LOGIN_MAX_PER_IP;
}

function login_record_failure($conn, $identifier, $ip) {
    $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, ip, attempted_at) VALUES (?,?,NOW())');
    if ($stmt) {
        $stmt->bind_param('ss', $identifier, $ip);
        $stmt->execute();
        $stmt->close();
    }
     
    $conn->query('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function login_clear_failures($conn, $identifier) {
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier=?');
    if ($stmt) {
        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $stmt->close();
    }
}

 
function auth_status_message($status) {
    if ($status === 'pending') {
        return 'Your account is awaiting administrator approval.';
    }
    return 'Your account is currently inactive. Please contact the administrator.';
}

// Load the RBAC / role helpers (require_staff(), log_access(), role_home(), ...).
// auth.php is guarded by BRGY_AUTH_LOADED and never re-includes this file
// because require_login() is already defined by the time this runs.
require_once BASE_PATH . '/includes/auth.php';
