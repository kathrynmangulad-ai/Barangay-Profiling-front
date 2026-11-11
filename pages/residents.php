<?php
require_once __DIR__ . '/../config/config.php'; require_staff(); $page_title='Residents';
$q=trim($_GET['q']??''); $f_brgy=(int)($_GET['barangay']??0); $f_sort=trim($_GET['sort']??'name_az');
$view_deleted=(($_GET['view']??'')==='deleted');
$allowed_sorts=['name_az','name_za','newest','oldest'];
if(!in_array($f_sort,$allowed_sorts,true)){ $f_sort='name_az'; }
// The main list shows live residents; the Deleted view shows soft-deleted ones.
$deleted_cond = $view_deleted ? 'r.deleted_at IS NOT NULL' : 'r.deleted_at IS NULL';
// IP / 4Ps flags were added outside the base schema — detect whichever
// column name exists (same candidates as the dashboard) so the table shows
// the very data the dashboard counts and never breaks on a missing column.
$ip_col=null; $fp_col=null;
foreach(['is_ip','is_indigenous','indigenous'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $ip_col=$cand; $qc->free(); break; } $qc->free(); } }
foreach(['is_4ps','is_fourps','fourps','pantawid'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $fp_col=$cand; $qc->free(); break; } $qc->free(); } }
$sql="SELECT r.*,b.barangay_name,u.email AS account_email, hh.household_no AS household_label, hh.head_resident_id AS household_head_id FROM residents r JOIN barangays b ON b.id=r.barangay_id LEFT JOIN users u ON u.id=r.user_id LEFT JOIN households hh ON hh.id=r.household_id AND hh.deleted_at IS NULL WHERE $deleted_cond"; $params=[];$types='';
if($_SESSION['role']!=='admin'){ $sql.=" AND r.barangay_id=?";$types.='i';$params[]=$_SESSION['barangay_id']; }
elseif($f_brgy>0){ $sql.=" AND r.barangay_id=?";$types.='i';$params[]=$f_brgy; }
if($q!==''){ $sql.=" AND (r.last_name LIKE ? OR r.first_name LIKE ? OR r.middle_name LIKE ?)";
$types.='sss';$like="%$q%";$params[]=$like;$params[]=$like;$params[]=$like; }
 
if($f_sort==='name_za'){ $sql.=" ORDER BY r.last_name DESC,r.first_name DESC,r.middle_name DESC"; }
elseif($f_sort==='newest'){ $sql.=" ORDER BY r.created_at DESC,r.id DESC"; }
elseif($f_sort==='oldest'){ $sql.=" ORDER BY r.created_at ASC,r.id ASC"; }
else{ $sql.=" ORDER BY r.last_name ASC,r.first_name ASC,r.middle_name ASC"; }
$stmt=$conn->prepare($sql);
if($types)$stmt->bind_param($types,...$params);$stmt->execute();
$rows=$stmt->get_result();
$total_rows=(int)$rows->num_rows;
 
$brgys=$conn->query('SELECT id,barangay_name FROM barangays ORDER BY barangay_name ASC');
$brgy_label='All Barangays';
if($f_brgy>0){ $ln=$conn->prepare('SELECT barangay_name FROM barangays WHERE id=? LIMIT 1'); if($ln){ $ln->bind_param('i',$f_brgy); $ln->execute(); $lr=$ln->get_result()->fetch_assoc(); $ln->close(); if($lr){ $brgy_label=$lr['barangay_name']; } } }
elseif($_SESSION['role']!=='admin'){ $brgy_label=(string)($_SESSION['barangay_name'] ?? 'My Barangay'); }
if($_SESSION['role']!=='admin'){
     
    $own=(int)($_SESSION['barangay_id']??0);
    $on=$conn->prepare('SELECT barangay_name FROM barangays WHERE id=? LIMIT 1');
    if($on){ $on->bind_param('i',$own); $on->execute(); $or=$on->get_result()->fetch_assoc(); $on->close(); if($or){ $brgy_label=$or['barangay_name']; } }
}
// Households are counted from the households table (one row per household) and
// scoped exactly like the residents list, so a secretary only ever sees their
// own barangay's totals.
$hhSql = 'SELECT COUNT(*) AS total FROM households WHERE deleted_at IS NULL';
$hhTypes = '';
$hhParams = [];
if ($_SESSION['role'] !== 'admin') {
    $hhSql .= ' AND barangay_id = ?';
    $hhTypes = 'i';
    $hhParams[] = (int)$_SESSION['barangay_id'];
} elseif ($f_brgy > 0) {
    $hhSql .= ' AND barangay_id = ?';
    $hhTypes = 'i';
    $hhParams[] = $f_brgy;
}
$household_query = $conn->prepare($hhSql);
if ($hhTypes !== '') { $household_query->bind_param($hhTypes, ...$hhParams); }
$household_query->execute();
$total_households = $household_query->get_result()->fetch_assoc()['total'];
$household_query->close();

$pwd_query = $conn->query("
    SELECT COUNT(*) AS total
    FROM residents
    WHERE deleted_at IS NULL AND is_pwd = 1
");$total_pwd = $pwd_query->fetch_assoc()['total'];
$student_query = $conn->query("
    SELECT COUNT(*) AS total
    FROM residents
    WHERE deleted_at IS NULL AND is_student = 1
");
$total_students = $student_query->fetch_assoc()['total'];

// Count of soft-deleted residents, scoped to the secretary's barangay.
if($_SESSION['role']!=='admin'){
    $dc=$conn->prepare("SELECT COUNT(*) c FROM residents WHERE deleted_at IS NOT NULL AND barangay_id=?");
    $dc->bind_param('i',$_SESSION['barangay_id']);
    $dc->execute();
    $deleted_count=(int)$dc->get_result()->fetch_assoc()['c'];
    $dc->close();
}else{
    $dcq=$conn->query("SELECT COUNT(*) c FROM residents WHERE deleted_at IS NOT NULL");
    $deleted_count=$dcq?(int)$dcq->fetch_assoc()['c']:0;
}

  
$cell = function ($v) {
    $s = trim((string)($v ?? ''));
    return $s === '' ? '-' : e($s);
};

include BASE_PATH . '/partials/header.php';?>
<div class="toolbar">
    <form method="get">
        <input type="hidden" name="q" value="<?=e($q)?>">

        <?php if($_SESSION['role']==='admin'): ?>
        <label class="sr-only" for="res_brgy">Filter by Barangay</label>
        <select id="res_brgy" name="barangay" title="Filter by Barangay">
            <option value="0">All Barangays</option>
            <?php while($bb=$brgys->fetch_assoc()): ?>
            <option value="<?=(int)$bb['id']?>"<?=$f_brgy===(int)$bb['id']?' selected':''?>><?=e($bb['barangay_name'])?></option>
            <?php endwhile; ?>
        </select>
        <?php endif; ?>
        <label class="sr-only" for="res_sort">Sort</label>
        <select id="res_sort" name="sort" title="Sort">
            <option value="name_az"<?=$f_sort==='name_az'?' selected':''?>>Sort: Name A–Z</option>
            <option value="name_za"<?=$f_sort==='name_za'?' selected':''?>>Sort: Name Z–A</option>
            <option value="newest"<?=$f_sort==='newest'?' selected':''?>>Sort: Newest Registered</option>
            <option value="oldest"<?=$f_sort==='oldest'?' selected':''?>>Sort: Oldest Registered</option>
        </select>
        <?php if($view_deleted): ?><input type="hidden" name="view" value="deleted"><?php endif; ?>
        <button class="btn" type="submit">Filter</button>
        <a class="btn btn--ghost" href="<?= e(url('pages/residents.php' . ($view_deleted ? '?view=deleted' : ''))) ?>">Reset</a>
        <?php if($view_deleted): ?>
        <a class="btn btn--ghost" href="<?= e(url('pages/residents.php')) ?>">&larr; Back to residents</a>
        <?php else: ?>
        <a class="btn btn--ghost" href="<?= e(url('pages/residents.php?view=deleted')) ?>">Deleted (<?= (int)$deleted_count ?>)</a>
        <?php endif; ?>
    </form>
</div>
<?php if(isset($_GET['restored'])): ?>
<div class="alert alert-success" role="status">Resident restored - they are back in the active list.</div>
<?php endif; ?>
<p class="muted-meta" role="status">
    <?php if($view_deleted): ?>Showing <?=number_format($total_rows)?> deleted resident<?= $total_rows===1?'':'s' ?> — Barangay: <?=e($brgy_label)?>
    <?php else: ?>Showing <?=number_format($total_rows)?> resident<?= $total_rows===1?'':'s' ?> — Barangay: <?=e($brgy_label)?><?php endif; ?>
</p>
<?php
/* One "Classification" column instead of four Yes/No columns: a tag appears
   only for the groups a resident belongs to. */
$is_yes = function ($v) { return in_array(strtolower(trim((string)($v ?? ''))), ['yes', '1', 'y', 'true'], true); };
$show_purok = (($_SESSION['role'] ?? '') !== 'admin');
?>
<div class="card table-wrap" style="overflow-x:auto">
    <table class="table-residents" style="table-layout:auto;width:100%">
    <thead><tr>
        <th>Resident</th>
        <th>Barangay</th>
        <?php if($show_purok): ?><th>Purok/Zone</th><?php endif; ?>
        <th>Birth Date</th>
        <th>Civil Status</th>
        <th>Classification</th>
        <th>Household</th>
        <th>Contact</th>
        <th class="col-actions">Action</th>
    </tr></thead>
    <tbody>
    <?php if ($total_rows === 0) {
        $res_cols = 8 + ($show_purok ? 1 : 0);
        if ($q !== '' || $f_brgy > 0) {
            echo empty_row($res_cols, 'search', 'No residents match', 'Try a different search, or reset the filters.');
        } elseif ($view_deleted) {
            echo empty_row($res_cols, 'users', 'No deleted residents', 'Residents you delete are kept here and can be restored.');
        } else {
            echo empty_row($res_cols, 'users', 'No residents yet', 'Add the first one to start the list.',
                '<a class="btn" href="' . e(url('pages/resident_add.php')) . '">' . icon('user-plus') . ' New Resident</a>');
        }
    } ?>
    <?php while($r=$rows->fetch_assoc()):
        $photo = trim((string)($r['photo'] ?? ''));
        $hasPhoto = ($photo !== '' && @file_exists(BASE_PATH . '/' . $photo));
        $initials = '';
        foreach (preg_split('/\s+/', trim($r['last_name'] . ' ' . $r['first_name'])) as $part) {
            if ($part !== '' && preg_match('/\p{L}/u', $part, $m)) { $initials .= strtoupper($m[0]); }
        }
        $initials = substr($initials, 0, 2);
        if ($initials === '') { $initials = '?'; }

        // Sex is displayed as a small male/female badge over the photo
        // instead of its own column.
        $sex_lc  = strtolower(trim((string)($r['sex'] ?? '')));
        $sex_key = ($sex_lc === 'male' || $sex_lc === 'female') ? $sex_lc : '';

        $occupation = trim((string)($r['occupation'] ?? ''));
        $age        = trim((string)($r['age'] ?? ''));
        $contact    = trim((string)($r['contact_no'] ?? ''));
        $email      = trim((string)($r['email'] ?? ($r['account_email'] ?? '')));

        $tags = [];
        if ($is_yes($r['is_pwd'] ?? null))     { $tags[] = ['PWD', 'Person with Disability']; }
        if ($is_yes($r['is_student'] ?? null)) { $tags[] = ['Student', 'Student']; }
        if ($ip_col !== null && $is_yes($r[$ip_col] ?? null)) { $tags[] = ['IP', 'Indigenous People']; }
        if ($fp_col !== null && $is_yes($r[$fp_col] ?? null)) { $tags[] = ['4Ps', '4Ps Beneficiary']; }
    ?>
    <tr>
    <td>
        <div class="cell-person">
            <span class="photo-chip">
            <?php if ($hasPhoto): ?>
                <img class="avatar avatar--sm" src="<?= e(url($photo)) ?>" alt="">
            <?php else: ?>
                <span class="avatar avatar--sm" aria-hidden="true"><?= e($initials) ?></span>
            <?php endif; ?>
            <?php if ($sex_key !== ''): ?>
                <span class="photo-chip__sex photo-chip__sex--<?= $sex_key ?>" title="<?= e(ucfirst($sex_lc)) ?>" aria-label="<?= e(ucfirst($sex_lc)) ?>"><?= icon($sex_key) ?></span>
            <?php endif; ?>
            </span>
            <span class="cell-stack">
                <span class="cell-main"><?= e(resident_name($r)) ?></span>
                <?php if ($occupation !== ''): ?><span class="cell-sub"><?= e($occupation) ?></span><?php endif; ?>
            </span>
        </div>
    </td>
    <td><?= $cell($r['barangay_name']) ?></td>
    <?php if($show_purok): ?><td><?= $cell($r['address'] ?? null) ?></td><?php endif; ?>
    <td>
        <span class="cell-stack">
            <span><?= e(fmt_date($r['birth_date'])) ?></span>
            <?php if ($age !== ''): ?><span class="cell-sub"><?= e($age) ?> yrs old</span><?php endif; ?>
        </span>
    </td>
    <td><?= $cell($r['civil_status']) ?></td>
    <td>
        <?php if ($tags): ?>
            <span class="tags"><?php foreach ($tags as [$tag, $tag_title]): ?><span class="tag" title="<?= e($tag_title) ?>"><?= e($tag) ?></span><?php endforeach; ?></span>
        <?php else: ?>
            <span class="muted-meta" title="Not PWD, student, IP or 4Ps">&mdash;</span>
        <?php endif; ?>
    </td>
    <td><?php
        $hhLabel = trim((string)($r['household_label'] ?? ($r['household_no'] ?? '')));
        if ($hhLabel !== '') {
            echo e($hhLabel);
            if ((int)($r['household_head_id'] ?? 0) === (int)$r['id']) {
                echo ' <span class="badge badge--info">Head</span>';
            }
        } elseif ((int)($r['household_id'] ?? 0) > 0) {
            echo '<span class="muted-meta">Hidden</span>';
        } else {
            echo '<span class="muted-meta">Unassigned</span>';
        }
    ?></td>
    <td>
        <?php if ($contact === '' && $email === ''): ?>
            <span class="muted-meta">&mdash;</span>
        <?php else: ?>
        <span class="cell-stack">
            <?php if ($contact !== ''): ?><span><?= e($contact) ?></span><?php endif; ?>
            <?php if ($email !== ''): ?><span class="cell-sub"><?= e($email) ?></span><?php endif; ?>
        </span>
        <?php endif; ?>
    </td>
    <td class="col-actions">
    <?php if($view_deleted): ?>
    <a href="<?= e(url('actions/resident_restore.php?id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>"
       class="btn-approve"
       data-confirm="Restore this resident? They will return to the active list."
       data-confirm-title="Restore resident">
       Restore
    </a>
    <?php else: ?>
    <a href="<?= e(url('pages/resident_edit.php?id=' . (int)$r['id'])) ?>" class="btn-edit">Edit</a>

    <a href="<?= e(url('actions/resident_delete.php?id=' . (int)$r['id'] . '&token=' . csrf_token())) ?>"
       class="btn-delete"
       data-confirm="Delete this resident? They will be moved to the Deleted list and can be restored later."
       data-confirm-title="Delete resident">
       Delete
    </a>
    <?php endif; ?>
</td></tr><?php endwhile; ?>
    </tbody></table></div><?php include BASE_PATH . '/partials/footer.php';
