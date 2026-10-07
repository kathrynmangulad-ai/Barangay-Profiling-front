<?php










require_once __DIR__ . '/../config/config.php';

$mode       = $argv[1] ?? '';
$fixtureUser = 'rbac_resident';

$RBAC_LOG = getenv('RBAC_LOG') ?: '';
function outln($s) {
    global $RBAC_LOG;
    echo $s;
    if ($RBAC_LOG !== '') { file_put_contents($RBAC_LOG, $s, FILE_APPEND); }
}

 
if ($mode === 'fixture' || $mode === 'teardown') {

    if ($mode === 'teardown') {
        $d = $conn->prepare('DELETE FROM residents WHERE user_id IN (SELECT id FROM users WHERE username=?)');
        $d->bind_param('s', $fixtureUser);
        $d->execute(); $d->close();

        $e = $conn->prepare('DELETE FROM users WHERE username=?');
        $e->bind_param('s', $fixtureUser);
        $e->execute();
        $del = $e->affected_rows; $e->close();

        outln("TEARDOWN removed_users=$del\n");
        exit;
    }

    $hash = password_hash('RbacTest!123', PASSWORD_DEFAULT);

    $s = $conn->prepare('SELECT id FROM users WHERE username=?');
    $s->bind_param('s', $fixtureUser);
    $s->execute();
    $existing = $s->get_result()->fetch_assoc(); $s->close();
    $uid = $existing ? (int)$existing['id'] : 0;

    if (!$uid) {
        $i = $conn->prepare("INSERT INTO users (username,password,full_name,role,barangay_id,status)
                             VALUES (?,?,?,'resident',1,'active')");
        $name = 'RBAC Tester';
        $i->bind_param('sss', $fixtureUser, $hash, $name);
        $i->execute(); $i->close();
        $uid = (int)$conn->insert_id;
    }

    $fxLn = 'RBAC'; $fxFn = 'Tester';
    $c = $conn->prepare('SELECT id FROM residents WHERE last_name=? AND first_name=?');
    $c->bind_param('ss', $fxLn, $fxFn);
    $c->execute();
    $row = $c->get_result()->fetch_assoc(); $c->close();
    $rid = $row ? (int)$row['id'] : 0;

    if (!$rid) {
        $ln = 'RBAC'; $fn = 'Tester';
        $r = $conn->prepare('INSERT INTO residents (barangay_id,last_name,first_name,age,user_id)
                             VALUES (1,?,?,30,?)');
        $r->bind_param('ssi', $ln, $fn, $uid);
        $r->execute(); $r->close();
        $rid = (int)$conn->insert_id;
    } else {
        $u = $conn->prepare('UPDATE residents SET user_id=? WHERE id=?');
        $u->bind_param('ii', $uid, $rid);
        $u->execute(); $u->close();
    }

    outln("FIXTURE user_id=$uid resident_id=$rid\n");
    exit;
}
 
if ($mode === 'transition') {
    


    $docId = (int)($argv[2] ?? 0);
    if ($docId <= 0) { outln("USAGE: php test_rbac.php transition <doc_id>\n"); exit(1); }

    $_SESSION['user_id']     = 1;
    $_SESSION['username']    = 'admin';
    $_SESSION['full_name']   = 'Admin';
    $_SESSION['role']        = 'admin';
    $_SESSION['barangay_id'] = null;
    $_SESSION['status']      = 'active';

    $bq = $conn->prepare('SELECT status FROM document_requests WHERE id=?');
    $bq->bind_param('i', $docId);
    $bq->execute();
    $brow = $bq->get_result()->fetch_assoc();
    $bq->close();
    outln("start_status=" . var_export($brow['status'] ?? null, true) . "\n");

     
    $bad = transition_document($docId, 'Pending', ['Released'], 'illegal');
    outln("illegal transition refused = " . var_export($bad === false, true) . "\n");

     
    $ok = transition_document($docId, 'Processing', ['Pending'], 'approved by test');
    outln("Pending->Processing applied = " . var_export($ok === true, true) . "\n");

    $aq = $conn->prepare('SELECT status, processed_by, decision_note FROM document_requests WHERE id=?');
    $aq->bind_param('i', $docId);
    $aq->execute();
    $arow = $aq->get_result()->fetch_assoc();
    $aq->close();
    outln("end_status=" . var_export($arow['status'] ?? null, true)
        . " processed_by=" . var_export($arow['processed_by'] ?? null, true)
        . " note=" . var_export($arow['decision_note'] ?? null, true) . "\n");

    $hq = $conn->prepare('SELECT from_status, to_status, actor_name FROM document_status_history
                           WHERE request_id=? ORDER BY id DESC LIMIT 1');
    $hq->bind_param('i', $docId);
    $hq->execute();
    $hrow = $hq->get_result()->fetch_assoc();
    $hq->close();
    outln("history=" . ($hrow ? $hrow['from_status'] . '->' . $hrow['to_status'] . ' by ' . $hrow['actor_name'] : 'NONE') . "\n");

    $lq = $conn->prepare('SELECT action, result FROM access_log ORDER BY id DESC LIMIT 1');
    $lq->execute();
    $lrow = $lq->get_result()->fetch_assoc();
    $lq->close();
    outln("access_log=" . ($lrow ? $lrow['action'] . '/' . $lrow['result'] : 'NONE') . "\n");
    exit;
}


 
$wanted = in_array($mode, ['admin', 'secretary', 'resident'], true) ? $mode : '';

$wantStatus = 'active';
$q = $conn->prepare('SELECT id, full_name, role, barangay_id FROM users
                      WHERE role=? AND status=? ORDER BY id LIMIT 1');
$q->bind_param('ss', $wanted, $wantStatus);
$q->execute();
$me = $q->get_result()->fetch_assoc(); $q->close();

if (!$me) { outln("NO USER FOUND FOR ROLE '$wanted'\n"); exit(1); }

 
$_SESSION['user_id']     = (int)$me['id'];
$_SESSION['username']    = $me['full_name'];
$_SESSION['full_name']   = $me['full_name'];
$_SESSION['role']        = $me['role'];
$_SESSION['barangay_id'] = $me['barangay_id'];
$_SESSION['status']      = 'active';

$u    = current_user();
$pass = 0;
$fail = 0;

function check($label, $got, $want) {
    global $pass, $fail;
    $ok = ($got === $want);
    $ok ? $pass++ : $fail++;
    outln(sprintf("  [%s] %-40s got=%s want=%s\n", $ok ? 'PASS' : 'FAIL', $label,
           var_export($got, true), var_export($want, true)));
}

outln("ROLE: {$u['role']}  user_id={$u['id']}  barangay_id="
   . var_export($u['barangay_id'], true) . "  resident_id="
   . var_export($u['resident_id'], true) . "\n");

[$sb, $sbP] = scope_barangay('t');
[$sr, $srP] = scope_residents('r');
[$sq, $sqP] = scope_requests('q');

outln(" scope_barangay : [$sb] " . json_encode($sbP) . "\n");
outln(" scope_residents: [$sr] " . json_encode($srP) . "\n");
outln(" scope_requests : [$sq] " . json_encode($sqP) . "\n");

$ownBid = (int)$me['barangay_id'];

switch ($wanted) {
    case 'admin':
        check('scope_barangay empty',      $sb, '');
        check('scope_residents empty',      $sr, '');
        check('scope_requests empty',       $sq, '');
        check('can_access_barangay(null)',  can_access_barangay(null), true);
        check('can_access_barangay(999)',   can_access_barangay(999), true);
        check('is_admin',                   is_admin(), true);
        break;

    case 'secretary':
        check('scope_barangay filtered',    $sb, ' AND t.barangay_id = ?');
        check('scope_barangay param',       $sbP, [$ownBid]);
        check('scope_residents filtered',   $sr, ' AND r.barangay_id = ?');
        check('scope_requests filtered',    $sq, ' AND q.barangay_id = ?');
        check('own barangay allowed',       can_access_barangay($ownBid), true);
        check('OTHER barangay refused',     can_access_barangay(999), false);
        check('other barangay record',      can_access_record(999, null), false);
        check('is_admin false',             is_admin(), false);
        break;

    case 'resident':
        check('scope_barangay filtered',    $sb, ' AND t.barangay_id = ?');
        check('scope_residents by user_id', $sr, ' AND r.user_id = ?');
        check('scope_requests by resid_id', $sq, ' AND q.resident_id = ?');
        check('own record allowed',         can_access_record($ownBid, $u['resident_id']), true);
        check('other resident refused',     can_access_record($ownBid, 99999), false);
        check('null-resident refused',      can_access_record($ownBid, null), false);
        check('role_home',                  role_home('resident'), 'resident_dashboard.php');
        break;
}

outln(" RESULT: pass=$pass fail=$fail\n");
exit($fail > 0 ? 1 : 0);
