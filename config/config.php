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

/* ---------------------------------------------------------------------------
 * Households
 *
 * Each household is one row in `households`, bound to residents through
 * residents.household_id, and carries exactly one head of the family
 * (households.head_resident_id). Every household count in the app goes through
 * these helpers so the numbers stay consistent.
 *
 * The head pointer is a plain id (not a FK): residents are only ever soft
 * deleted, so a hidden head keeps the pointer and regains headship when
 * restored. A pointer to a deleted/missing resident is treated as "vacant" and
 * may be reassigned.
 * ------------------------------------------------------------------------- */

/** Next household number for a barangay, e.g. "HH-0001". Never reuses a number. */
function household_next_no($conn, $barangay_id) {
    $barangay_id = (int)$barangay_id;
    $max = 0;
    $stmt = $conn->prepare('SELECT household_no FROM households WHERE barangay_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $barangay_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) {
            if (preg_match('/(\d+)$/', trim((string)$row['household_no']), $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        $stmt->close();
    }
    return sprintf('HH-%04d', $max + 1);
}

/** Live households of one barangay for a dropdown: id, no, head name (null = no live head), member count. */
function households_options($conn, $barangay_id) {
    $barangay_id = (int)$barangay_id;
    if ($barangay_id <= 0) { return []; }
    $out = [];
    $sql = "SELECT h.id, h.household_no, h.head_resident_id,
                   CASE WHEN hd.id IS NULL OR hd.deleted_at IS NOT NULL THEN NULL
                        ELSE CONCAT_WS(' ', hd.first_name, hd.last_name) END AS head_name,
                   COALESCE(m.members, 0) AS members
              FROM households h
              LEFT JOIN residents hd ON hd.id = h.head_resident_id
              LEFT JOIN (SELECT household_id, COUNT(*) members
                           FROM residents
                          WHERE deleted_at IS NULL AND household_id IS NOT NULL
                          GROUP BY household_id) m ON m.household_id = h.id
             WHERE h.deleted_at IS NULL AND h.barangay_id = ?
             ORDER BY h.household_no";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $barangay_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) { $out[] = $row; }
        $stmt->close();
    }
    return $out;
}

/** Total number of live households, optionally scoped to one barangay. */
function household_count($conn, $barangay_id = 0) {
    $barangay_id = (int)$barangay_id;
    $sql = 'SELECT COUNT(*) c FROM households WHERE deleted_at IS NULL';
    $types = '';
    $params = [];
    if ($barangay_id > 0) {
        $sql .= ' AND barangay_id = ?';
        $types = 'i';
        $params[] = $barangay_id;
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return 0; }
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

/**
 * Fetch one household (soft-deleted included) with its head's liveness:
 * [id, barangay_id, household_no, head_resident_id, deleted_at, head_live].
 * Returns null when the household does not exist.
 */
function household_fetch($conn, $household_id) {
    $household_id = (int)$household_id;
    if ($household_id <= 0) { return null; }
    $stmt = $conn->prepare(
        'SELECT h.id, h.barangay_id, h.household_no, h.head_resident_id, h.deleted_at,
                CASE WHEN hd.id IS NULL OR hd.deleted_at IS NOT NULL THEN 0 ELSE 1 END AS head_live
           FROM households h
           LEFT JOIN residents hd ON hd.id = h.head_resident_id
          WHERE h.id = ? LIMIT 1'
    );
    if (!$stmt) { return null; }
    $stmt->bind_param('i', $household_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

/**
 * Create a household row. $household_no must be unique per barangay (the DB
 * enforces it too). Returns the new household id; throws on collision.
 */
function household_create($conn, $barangay_id, $household_no, $purok = null) {
    $barangay_id  = (int)$barangay_id;
    $household_no = trim((string)$household_no);
    $purok        = trim((string)$purok);
    $purok        = ($purok !== '') ? $purok : null;
    $stmt = $conn->prepare('INSERT INTO households (barangay_id, household_no, purok) VALUES (?,?,?)');
    if (!$stmt) { throw new RuntimeException('prepare household failed: ' . $conn->error); }
    $stmt->bind_param('iss', $barangay_id, $household_no, $purok);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $errno = (int)$stmt->errno;
        $stmt->close();
        if ($errno === 1062) {
            throw new RuntimeException('Household number "' . $household_no . '" already exists in this barangay.');
        }
        throw new RuntimeException('insert household failed: ' . $err);
    }
    $new_id = (int)$conn->insert_id;
    $stmt->close();
    return $new_id;
}

/** Bind a resident to a household (pass null to leave the resident unassigned). */
function household_assign($conn, $resident_id, $household_id) {
    $resident_id  = (int)$resident_id;
    $household_id = ($household_id === null) ? null : (int)$household_id;
    $stmt = $conn->prepare('UPDATE residents SET household_id = ? WHERE id = ?');
    if (!$stmt) { throw new RuntimeException('prepare household assign failed: ' . $conn->error); }
    $stmt->bind_param('ii', $household_id, $resident_id);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('bind resident to household failed'); }
    $stmt->close();
}

/**
 * Make a resident the head of a household. Succeeds only while the household
 * has no live head yet (unassigned, deleted, or already this resident).
 * Returns true when the resident ends up as head.
 */
function household_claim_head($conn, $household_id, $resident_id) {
    $hh = household_fetch($conn, $household_id);
    if (!$hh || $hh['deleted_at'] !== null) { return false; }
    $current = (int)($hh['head_resident_id'] ?? 0);
    $is_vacant = ($current === 0)
        || ($current === (int)$resident_id)
        || ((int)$hh['head_live'] === 0);
    if (!$is_vacant) { return false; }
    $household_id = (int)$household_id;
    $resident_id  = (int)$resident_id;
    $stmt = $conn->prepare('UPDATE households SET head_resident_id = ? WHERE id = ? AND deleted_at IS NULL');
    if (!$stmt) { return false; }
    $stmt->bind_param('ii', $resident_id, $household_id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

/** Clear headship when the given resident is (still) the household's head. */
function household_release_head($conn, $household_id, $resident_id) {
    $household_id = (int)$household_id;
    $resident_id  = (int)$resident_id;
    if ($household_id <= 0 || $resident_id <= 0) { return; }
    $stmt = $conn->prepare('UPDATE households SET head_resident_id = NULL WHERE id = ? AND head_resident_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $household_id, $resident_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Soft-delete a household that has no live members left, so it stops being
 * counted. Returns true when the household was retired.
 */
function household_retire_if_empty($conn, $household_id) {
    $household_id = (int)$household_id;
    if ($household_id <= 0) { return false; }
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM residents WHERE deleted_at IS NULL AND household_id = ?');
    if (!$stmt) { return false; }
    $stmt->bind_param('i', $household_id);
    $stmt->execute();
    $members = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    if ($members > 0) { return false; }
    $stmt = $conn->prepare('UPDATE households SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    if (!$stmt) { return false; }
    $stmt->bind_param('i', $household_id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

/** Bring a retired household back (used when one of its members is restored). */
function household_revive($conn, $household_id) {
    $household_id = (int)$household_id;
    if ($household_id <= 0) { return; }
    $stmt = $conn->prepare('UPDATE households SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL');
    if ($stmt) {
        $stmt->bind_param('i', $household_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Load the RBAC / role helpers (require_staff(), log_access(), role_home(), ...).
// auth.php is guarded by BRGY_AUTH_LOADED and never re-includes this file
// because require_login() is already defined by the time this runs.
require_once BASE_PATH . '/includes/auth.php';
