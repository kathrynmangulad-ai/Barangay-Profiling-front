<?php





















require_once __DIR__ . '/../config/config.php';

$apply = in_array('--apply', $argv ?? [], true);



if (PHP_SAPI !== 'cli') {
    require_admin();
}

function out($msg, $kind = '') {
    $cls = $kind ? " class=\"$kind\"" : '';
    if (PHP_SAPI === 'cli') {
        echo strip_tags($msg) . "\n";
    } else {
        echo "<p$cls>" . e($msg) . "</p>\n";
    }
}

if (PHP_SAPI !== 'cli') {
    echo '<!doctype html><meta charset="utf-8"><title>Assign Roles</title>';
    echo '<div style="font-family:system-ui;max-width:900px;margin:40px auto">';
    echo '<h1>Role assignment</h1>';
}





$sql = 'SELECT u.id, u.username, u.full_name, u.role, u.status, u.barangay_id,
               b.barangay_name
          FROM users u
          LEFT JOIN barangays b ON b.id = u.barangay_id
      ORDER BY u.id';
$res = $conn->query($sql);
if (!$res) { die('Query failed: ' . $conn->error); }

$changes = [];    
$manual  = [];    

while ($u = $res->fetch_assoc()) {
    $bid   = $u['barangay_id'];
    $hasBrgy = !empty($bid);
    $role  = $u['role'];

    

    if ($hasBrgy && $u['barangay_name'] === null) {
        $manual[] = $u['username'] . ' (id ' . $u['id'] . '): barangay_id=' . $bid
                  . ' does not exist in the barangays table.';
        continue;
    }

    if (!$hasBrgy && $role !== 'admin') {
        $changes[$u['id']] = [
            'username' => $u['username'],
            'from' => $role, 'to' => 'admin',
            'why' => 'no barangay assigned -> system administrator',
        ];
    } elseif ($hasBrgy && $role === 'admin') {
        

        $changes[$u['id']] = [
            'username' => $u['username'],
            'from' => 'admin', 'to' => 'secretary',
            'why' => 'has a barangay -> scoped to that barangay',
        ];
    } elseif ($hasBrgy && $role === 'resident') {
        $changes[$u['id']] = [
            'username' => $u['username'],
            'from' => 'resident', 'to' => 'secretary',
            'why' => 'resident accounts must be linked to a residents row first',
        ];
    }
}

out('Accounts scanned: ' . ($res->num_rows ?? 0));
out('Role changes required: ' . count($changes));
out('Rows needing manual attention: ' . count($manual));

foreach ($manual as $m) { out('  MANUAL: ' . $m, 'warn'); }

if (!$changes) {
    out('Nothing to do - every account already holds the correct role.', 'ok');
} else {
    echo PHP_SAPI === 'cli' ? "\n" : '<ul>';
    foreach ($changes as $id => $c) {
        $line = "#{$id} {$c['username']}: {$c['from']} -> {$c['to']} ({$c['why']})";
        if (PHP_SAPI === 'cli') { echo '  ' . $line . "\n"; }
        else { echo '<li><code>' . e($line) . '</code></li>'; }
    }
    echo PHP_SAPI === 'cli' ? "" : '</ul>';

    if ($apply) {
        $stmt = $conn->prepare('UPDATE users SET role=? WHERE id=?');
        $done = 0;
        foreach ($changes as $id => $c) {
            $stmt->bind_param('si', $c['to'], $id);
            $stmt->execute();
            $done += $stmt->affected_rows;
        }
        $stmt->close();
        out("Applied {$done} role update(s).", 'ok');
    } else {
        out('DRY RUN - nothing was written. Re-run with --apply to commit.', 'warn');
    }
}

 
echo PHP_SAPI === 'cli' ? "\nFinal role distribution:\n" : '<h2>Final distribution</h2>';
$r2 = $conn->query('SELECT role, COUNT(*) c FROM users GROUP BY role ORDER BY role');
while ($x = $r2->fetch_assoc()) { out('  ' . $x['role'] . ' = ' . $x['c']); }

if (PHP_SAPI !== 'cli') {
    echo '</div>';
}
