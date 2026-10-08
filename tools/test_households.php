<?php
/**
 * Household model smoke test (CLI).
 *
 * Exercises the household helpers end to end: number generation, binding a
 * resident to a household, the single-head rule, retire/revive, counts and the
 * legacy household_no backfill (including its re-run guard). Every row it
 * created is removed again, so the live database is left untouched.
 *
 * Usage:
 *   C:\xampp\php\php.exe tools\test_households.php
 * Exit code 0 = all checks passed.
 */

require_once __DIR__ . '/../config/config.php';

$fail = 0;
function check($label, $cond) {
    global $fail;
    echo ($cond ? 'PASS' : 'FAIL') . ' - ' . $label . "\n";
    if (!$cond) { $fail = 1; }
}

$row = $conn->query('SELECT id FROM barangays ORDER BY id LIMIT 1')->fetch_assoc();
$bid = (int)($row['id'] ?? 0);
check('a barangay exists', $bid > 0);

$marker = 'HHTEST' . substr(uniqid(), -6);

/** Remove everything this test created (FKs are ON DELETE SET NULL, so order is safe). */
function hh_test_cleanup($conn, $marker) {
    $like = $marker . '%';
    $d = $conn->prepare('DELETE FROM residents WHERE last_name = ?');
    $d->bind_param('s', $marker);
    $d->execute();
    $d->close();
    $d = $conn->prepare('DELETE FROM households WHERE household_no LIKE ?');
    $d->bind_param('s', $like);
    $d->execute();
    $d->close();
}

hh_test_cleanup($conn, $marker); // leftovers from a previous crashed run

try {
    /* 1. Numbering -------------------------------------------------------- */
    $next = household_next_no($conn, $bid);
    check('next number looks like HH-0001 (' . $next . ')', (bool)preg_match('/^HH-\d{4}$/', $next));

    /* 2. Create a household ------------------------------------------------ */
    $hh1 = household_create($conn, $bid, $marker . '-A', 'Purok Test');
    check('household created', $hh1 > 0);

    $dupThrew = false;
    try { household_create($conn, $bid, $marker . '-A'); }
    catch (RuntimeException $e) { $dupThrew = true; }
    check('duplicate household number is rejected', $dupThrew);

    /* 3. Bind members and the head ------------------------------------------ */
    $s = $conn->prepare('INSERT INTO residents (barangay_id, last_name, first_name, age) VALUES (?,?,?,?)');
    $role1 = 'Head'; $age = 40;
    $s->bind_param('issi', $bid, $marker, $role1, $age);
    $s->execute(); $id1 = (int)$conn->insert_id; $s->close();
    $s = $conn->prepare('INSERT INTO residents (barangay_id, last_name, first_name, age) VALUES (?,?,?,?)');
    $role2 = 'Member'; $age2 = 33;
    $s->bind_param('issi', $bid, $marker, $role2, $age2);
    $s->execute(); $id2 = (int)$conn->insert_id; $s->close();
    check('two test residents created', $id1 > 0 && $id2 > 0);

    household_assign($conn, $id1, $hh1);
    household_assign($conn, $id2, $hh1);
    $bound = (int)$conn->query("SELECT COUNT(*) c FROM residents WHERE household_id = $hh1 AND deleted_at IS NULL")->fetch_assoc()['c'];
    check('both residents bound to the household', $bound === 2);

    check('head claim succeeds for the first resident', household_claim_head($conn, $hh1, $id1));
    $f = household_fetch($conn, $hh1);
    check('household points at the head', (int)$f['head_resident_id'] === $id1 && (int)$f['head_live'] === 1);
    check('second resident cannot claim the same headship', household_claim_head($conn, $hh1, $id2) === false);
    $f = household_fetch($conn, $hh1);
    check('head unchanged after failed claim', (int)$f['head_resident_id'] === $id1);

    /* 4. Counts ------------------------------------------------------------- */
    $c0 = household_count($conn, $bid);
    $cAll0 = household_count($conn, 0);

    /* 5. Retire when the last member goes, revive on restore ---------------- */
    $conn->query("UPDATE residents SET deleted_at = NOW() WHERE id IN ($id1,$id2)");
    check('household retires when no live members remain', household_retire_if_empty($conn, $hh1) === true);
    check('retired household no longer counted', household_count($conn, $bid) === $c0 - 1);
    check('retired household no longer counted globally', household_count($conn, 0) === $cAll0 - 1);

    household_revive($conn, $hh1);
    check('revive brings the household back', household_count($conn, $bid) === $c0);

    /* 6. Release headship ---------------------------------------------------- */
    household_release_head($conn, $hh1, $id1);
    $f = household_fetch($conn, $hh1);
    check('release clears the head pointer', (int)$f['head_resident_id'] === 0);

    /* 7. Legacy backfill (same SQL as database/update.sql) ------------------- */
    $legacy = $marker . '-BL';
    $s = $conn->prepare('INSERT INTO residents (barangay_id, last_name, first_name, age, household_no) VALUES (?,?,?,?,?)');
    $role3 = 'Legacy'; $age3 = 35;
    $s->bind_param('issis', $bid, $marker, $role3, $age3, $legacy);
    $s->execute(); $id3 = (int)$conn->insert_id; $s->close();
    $esc = $conn->real_escape_string($legacy);

    $conn->query("INSERT IGNORE INTO households (barangay_id, household_no, purok, created_at)
                  SELECT r.barangay_id, TRIM(r.household_no), MIN(r.address), MIN(r.created_at)
                    FROM residents r
                   WHERE r.deleted_at IS NULL AND r.household_no IS NOT NULL AND TRIM(r.household_no) <> ''
                   GROUP BY r.barangay_id, TRIM(r.household_no)");
    check('backfill created a household for the legacy number',
        (bool)$conn->query("SELECT id FROM households WHERE barangay_id = $bid AND household_no = '$esc' LIMIT 1")->fetch_assoc());

    $linkSql = "UPDATE residents r
                    JOIN households h ON h.barangay_id = r.barangay_id AND h.household_no = TRIM(r.household_no)
                     SET r.household_id = h.id
                   WHERE r.deleted_at IS NULL AND r.household_id IS NULL
                     AND r.household_no IS NOT NULL AND TRIM(r.household_no) <> ''
                     AND NOT EXISTS (SELECT 1 FROM (
                             SELECT household_id FROM residents
                              WHERE deleted_at IS NULL AND household_id IS NOT NULL
                         ) m WHERE m.household_id = h.id)";
    $conn->query($linkSql);
    $linked = (int)$conn->query("SELECT household_id c FROM residents WHERE id = $id3")->fetch_assoc()['c'];
    check('backfill linked the resident to the household', $linked > 0);

    $conn->query('SET SESSION group_concat_max_len = 1000000');
    $conn->query("UPDATE households h
                    JOIN (SELECT household_id,
                                 SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY (age >= 18) DESC, age DESC, created_at ASC, id ASC), ',', 1) AS head_id
                            FROM residents WHERE deleted_at IS NULL AND household_id IS NOT NULL
                           GROUP BY household_id) m ON m.household_id = h.id
                     SET h.head_resident_id = m.head_id
                   WHERE h.head_resident_id IS NULL");
    $f = $conn->query("SELECT head_resident_id FROM households
                        WHERE barangay_id = $bid AND household_no = '$esc' LIMIT 1")->fetch_assoc();
    check('backfill picked a head for the household', (int)($f['head_resident_id'] ?? 0) === $id3);

    /* 8. Re-run guard: never re-links a resident that was unassigned later --- */
    $conn->query("UPDATE residents SET household_id = NULL WHERE id = $id3");
    $hhBl = (int)$conn->query("SELECT id FROM households WHERE barangay_id = $bid AND household_no = '$esc'")->fetch_assoc()['id'];
    $s = $conn->prepare('INSERT INTO residents (barangay_id, last_name, first_name, age) VALUES (?,?,?,?)');
    $role4 = 'Anchor'; $age4 = 28;
    $s->bind_param('issi', $bid, $marker, $role4, $age4);
    $s->execute(); $id4 = (int)$conn->insert_id; $s->close();
    household_assign($conn, $id4, $hhBl); // household now has a member again
    $conn->query($linkSql);
    $still = (int)$conn->query("SELECT household_id c FROM residents WHERE id = $id3")->fetch_assoc()['c'];
    check('re-run guard does not re-link an unassigned resident', $still === 0);
} catch (Throwable $e) {
    check('no unexpected exception: ' . $e->getMessage(), false);
} finally {
    hh_test_cleanup($conn, $marker);
    $escM = $conn->real_escape_string($marker);
    $left = (int)$conn->query("SELECT COUNT(*) c FROM residents WHERE last_name = '$escM'")->fetch_assoc()['c'];
    $left += (int)$conn->query("SELECT COUNT(*) c FROM households WHERE household_no LIKE '$escM%'")->fetch_assoc()['c'];
    check('cleanup removed every test row', $left === 0);
}

echo $fail === 0 ? "\nALL HOUSEHOLD CHECKS PASSED\n" : "\nSOME CHECKS FAILED\n";
exit($fail);
