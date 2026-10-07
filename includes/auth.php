<?php


















if (!defined('BRGY_AUTH_LOADED')) {

     
    if (!function_exists('require_login')) {
        require_once __DIR__ . '/../config/config.php';
    }

    define('BRGY_AUTH_LOADED', true);

    



     
    function rbac_roles(): array
    {
        return ['resident', 'secretary', 'admin'];
    }

    



    function current_user(): array
    {
        static $user = null;
        if ($user !== null) {
            return $user;
        }

        $user = [
            'id'          => (int)($_SESSION['user_id'] ?? 0),
            'username'    => (string)($_SESSION['username'] ?? ''),
            'full_name'   => (string)($_SESSION['full_name'] ?? ''),
            'role'        => (string)($_SESSION['role'] ?? ''),
            'barangay_id' => null,
            'status'      => (string)($_SESSION['status'] ?? ''),
            'resident_id' => null,
        ];

        if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
            return $user;
        }

        $conn = $GLOBALS['conn'] ?? null;
        if (!($conn instanceof mysqli)) {
            return $user;
        }

        $stmt = $conn->prepare(
            'SELECT u.id, u.username, u.full_name, u.role, u.barangay_id, u.status,
                    r.id AS resident_id
               FROM users u
               LEFT JOIN residents r ON r.user_id = u.id
              WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return $user;
        }
        $uid = (int)$_SESSION['user_id'];
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $user = [
                'id'          => (int)$row['id'],
                'username'    => (string)$row['username'],
                'full_name'   => (string)$row['full_name'],
                'role'        => (string)$row['role'],
                'barangay_id' => ($row['barangay_id'] !== null) ? (int)$row['barangay_id'] : null,
                'status'      => (string)$row['status'],
                'resident_id' => ($row['resident_id'] !== null) ? (int)$row['resident_id'] : null,
            ];

            

            $_SESSION['role']        = $user['role'];
            $_SESSION['barangay_id'] = $user['barangay_id'];
            $_SESSION['status']      = $user['status'];
            $_SESSION['full_name']   = $user['full_name'];
            $_SESSION['username']    = $user['username'];
        }

        return $user;
    }

    function current_role(): string
    {
        return (string)(current_user()['role'] ?? '');
    }

    function current_barangay_id(): ?int
    {
        $v = current_user()['barangay_id'] ?? null;
        return ($v === null) ? null : (int)$v;
    }

     
    function current_resident_id(): ?int
    {
        $v = current_user()['resident_id'] ?? null;
        return ($v === null) ? null : (int)$v;
    }

    function is_admin(): bool     { return current_role() === 'admin'; }
    function is_secretary(): bool { return current_role() === 'secretary'; }
    function is_resident(): bool  { return current_role() === 'resident'; }

    function has_role(string $role): bool
    {
        return current_role() === $role;
    }

     
    function role_label(?string $role = null): string
    {
        $role = $role ?? current_role();
        return [
            'admin'     => 'System Administrator',
            'secretary' => 'Barangay Secretary',
            'resident'  => 'Resident',
        ][$role] ?? ucfirst((string)$role);
    }








     
    function require_role(array $allowed): void
    {
        require_login();

        $user  = current_user();
        $roles = array_map('strval', $allowed);

        

        if ($user['role'] === 'resident' && $user['resident_id'] === null) {
            log_access('resident_unlinked', 'page', null, 'denied');
            deny_access('Your resident account is not yet linked to a resident record. Please contact your barangay secretary.');
        }

        if (!in_array($user['role'], $roles, true)) {
            log_access('role_denied:' . implode('|', $roles), 'page', null, 'denied');
            deny_access();
        }
    }

    function require_staff(): void     { require_role(['admin', 'secretary']); }
    function require_secretary(): void { require_role(['secretary']); }
    function require_resident(): void  { require_role(['resident']); }

     
    function deny_access(string $message = 'You do not have permission to view this page.'): void
    {
        http_response_code(403);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Access denied</title>'
           . '<link rel="stylesheet" href="' . e(asset('css/style.css')) . '"></head>'
           . '<body><div class="page" style="max-width:640px;margin:60px auto">'
           . '<div class="card"><h2>Access denied</h2><p>' . e($message) . '</p>'
           . '<p><a class="btn" href="' . e(url('pages/dashboard.php')) . '">Back to dashboard</a></p>'
           . '</div></div></body></html>';
        exit;
    }

    



    





    function can_access_barangay($barangay_id): bool
    {
        $user = current_user();

        if ($user['role'] === 'admin') {
            return true;
        }
        if ($user['barangay_id'] === null) {
            return false;                
        }
        return (int)$user['barangay_id'] === (int)$barangay_id;
    }

    

    function require_barangay_access($barangay_id, string $resource = 'record', ?int $resource_id = null): void
    {
        if (can_access_barangay($barangay_id)) {
            return;
        }
        log_access('barangay_denied', $resource, $resource_id, 'denied');
        deny_access();
    }

    





    function can_access_record($barangay_id, ?int $resident_id = null): bool
    {
        $user = current_user();

        if ($user['role'] === 'admin') {
            return true;
        }
        if ($user['role'] === 'resident') {
            return $user['resident_id'] !== null
                && $resident_id !== null
                && (int)$user['resident_id'] === (int)$resident_id;
        }
        return can_access_barangay($barangay_id);
    }

    function require_record_access($barangay_id, ?int $resident_id = null, string $resource = 'record', ?int $resource_id = null): void
    {
        if (can_access_record($barangay_id, $resident_id)) {
            return;
        }
        log_access('record_denied', $resource, $resource_id, 'denied');
        deny_access();
    }













     
    function scope_barangay(string $alias = '', string $join = ' AND '): array
    {
        if (is_admin()) {
            return ['', []];
        }
        $prefix = ($alias !== '') ? $alias . '.' : '';
        $bid    = current_barangay_id();

        if ($bid === null) {
            

            return [$join . '1=0', []];
        }
        return [$join . $prefix . 'barangay_id = ?', [$bid]];
    }

     
    function scope_residents(string $alias = '', string $join = ' AND '): array
    {
        if (is_admin()) {
            return ['', []];
        }
        $prefix = ($alias !== '') ? $alias . '.' : '';

        if (is_resident()) {
            $rid = current_resident_id();
            if ($rid === null) {
                return [$join . '1=0', []];
            }
            return [$join . $prefix . 'user_id = ?', [(int)$_SESSION['user_id']]];
        }

        $bid = current_barangay_id();
        if ($bid === null) {
            return [$join . '1=0', []];
        }
        return [$join . $prefix . 'barangay_id = ?', [$bid]];
    }

     
    function scope_requests(string $alias = '', string $join = ' AND '): array
    {
        if (is_admin()) {
            return ['', []];
        }
        $prefix = ($alias !== '') ? $alias . '.' : '';

        if (is_resident()) {
            $rid = current_resident_id();
            if ($rid === null) {
                return [$join . '1=0', []];
            }
            return [$join . $prefix . 'resident_id = ?', [$rid]];
        }

        $bid = current_barangay_id();
        if ($bid === null) {
            return [$join . '1=0', []];
        }
        return [$join . $prefix . 'barangay_id = ?', [$bid]];
    }

     
    function role_home(string $role): string
    {
        $path = [
            'admin'     => 'pages/dashboard.php',
            'secretary' => 'pages/dashboard.php',
            'resident'  => 'resident/resident_dashboard.php',
        ][$role] ?? 'pages/dashboard.php';
        return url($path);
    }






    



    function log_access(string $action, ?string $resource = null, ?int $resource_id = null, string $result = 'allowed'): void
    {
        $conn = $GLOBALS['conn'] ?? null;
        if (!($conn instanceof mysqli)) {
            return;
        }

        $uid  = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id']     : null;
        $user = isset($_SESSION['username']) ? (string)$_SESSION['username'] : null;
        $role = isset($_SESSION['role'])      ? (string)$_SESSION['role']     : null;
        $ip   = function_exists('client_ip') ? client_ip() : null;

        try {
            $stmt = $conn->prepare(
                'INSERT INTO access_log (user_id, username, role, action, resource, resource_id, result, ip)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            if (!$stmt) { return; }
            $stmt->bind_param('isssissi', $uid, $user, $role, $action, $resource, $resource_id, $result, $ip);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
             
        }
    }

     
    function log_document_decision(int $request_id, ?string $from, string $to, ?string $note = null): void
    {
        $conn = $GLOBALS['conn'] ?? null;
        if (!($conn instanceof mysqli)) {
            return;
        }

        try {
            $stmt = $conn->prepare(
                'INSERT INTO document_status_history (request_id, from_status, to_status, actor_id, actor_name, note)
                 VALUES (?,?,?,?,?,?)'
            );
            if (!$stmt) { return; }
            $actorId   = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id']       : null;
            $actorName = isset($_SESSION['full_name']) ? (string)$_SESSION['full_name'] : null;
            $stmt->bind_param('ississ', $request_id, $from, $to, $actorId, $actorName, $note);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
             
        }
    }

    






    function transition_document(int $request_id, string $to, array $allowed_from, ?string $note = null): bool
    {
        $conn = $GLOBALS['conn'] ?? null;
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $from = null;
        foreach ($allowed_from as $f) {
            $s = $conn->prepare('SELECT status FROM document_requests WHERE id=? AND status=? LIMIT 1');
            if (!$s) { return false; }
            $fid = (int)$request_id;
            

            $fstatus = (string)$f;
            $s->bind_param('is', $fid, $fstatus);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $s->close();
            if ($row) { $from = (string)$row['status']; break; }
        }
        if ($from === null) {
            return false;
        }

        $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        $u = $conn->prepare(
            'UPDATE document_requests
                SET status = ?, processed_by = ?, processed_at = NOW(), decision_note = ?
              WHERE id = ? AND status = ?'
        );
        if (!$u) { return false; }
        $fid = (int)$request_id;
        


        $u->bind_param('sisis', $to, $actorId, $note, $fid, $from);
        $ok = $u->execute() && $u->affected_rows === 1;
        $u->close();

        if ($ok) {
            log_document_decision($request_id, $from, $to, $note);
            log_access('document_' . strtolower($to), 'document_request', $request_id);
        }
        return $ok;
    }

}
?>