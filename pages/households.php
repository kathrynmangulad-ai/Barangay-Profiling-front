<?php
require_once __DIR__ . '/../config/config.php'; require_staff(); $page_title='Households';
$q=trim($_GET['q']??''); $f_brgy=(int)($_GET['barangay']??0);
$view_id=(int)($_GET['id']??0);

// --- Detail mode: one household + its live members ---------------------------
if ($view_id > 0) {
    $hs=$conn->prepare('SELECT h.id, h.household_no, h.purok, h.head_resident_id, h.created_at, h.barangay_id,
                               b.barangay_name
                          FROM households h JOIN barangays b ON b.id=h.barangay_id
                         WHERE h.id=? AND h.deleted_at IS NULL LIMIT 1');
    if($hs){ $hs->bind_param('i',$view_id); $hs->execute(); $hh=$hs->get_result()->fetch_assoc(); $hs->close(); }
    if (empty($hh)) { http_response_code(404); $page_title='Household not found'; include BASE_PATH.'/partials/header.php'; echo '<div class="alert alert-danger" role="alert">Household not found.</div><p><a class="btn btn--ghost" href="'.e(url('pages/households.php')).'">&larr; Back to households</a></p>'; include BASE_PATH.'/partials/footer.php'; return; }
    // Secretaries may only open households in their own barangay.
    if ($_SESSION['role']!=='admin' && (int)$hh['barangay_id']!==(int)($_SESSION['barangay_id']??0)) {
        http_response_code(403); $page_title='Forbidden'; include BASE_PATH.'/partials/header.php';
        echo '<div class="alert alert-danger" role="alert">You do not have access to this household.</div><p><a class="btn btn--ghost" href="'.e(url('pages/households.php')).'">&larr; Back to households</a></p>';
        include BASE_PATH.'/partials/footer.php'; return;
    }
    $page_title='Household '.(string)$hh['household_no'];
    $ms=$conn->prepare("SELECT r.id, r.first_name, r.last_name, r.middle_name, r.age, r.sex, r.civil_status, r.contact_no, r.created_at
                          FROM residents r
                         WHERE r.household_id=? AND r.deleted_at IS NULL
                         ORDER BY (r.id = ?) DESC, r.last_name ASC, r.first_name ASC");
    $members=[]; $head_name='-';
    if($ms){ $hid=(int)$hh['id']; $headId=(int)($hh['head_resident_id']??0); $ms->bind_param('ii',$hid,$headId); $ms->execute(); $members=$ms->get_result()->fetch_all(MYSQLI_ASSOC); $ms->close(); }
    foreach($members as $m){ if((int)$m['id']===(int)($hh['head_resident_id']??0)){ $head_name=trim($m['first_name'].' '.$m['last_name']); break; } }
    $cell = function ($v) { $s = trim((string)($v ?? '')); return $s === '' ? '-' : e($s); };
    include BASE_PATH . '/partials/header.php';?>
    <div class="toolbar">
        <a class="btn btn--ghost" href="<?= e(url('pages/households.php')) ?>">&larr; Back to households</a>
    </div>
    <div class="card" style="margin-bottom:16px">
        <h2 style="margin:0 0 8px"><?= e((string)$hh['household_no']) ?></h2>
        <p class="muted-meta" style="margin:0">
            Barangay: <?= e((string)$hh['barangay_name']) ?> &middot;
            Purok / Zone: <?= $cell($hh['purok']) ?> &middot;
            Head: <?= $head_name==='-' ? '<span class="badge badge--pending">No head yet</span>' : e($head_name) ?> &middot;
            Members: <?= (int)count($members) ?>
        </p>
    </div>
    <div class="card table-wrap" style="overflow-x:auto">
        <table style="table-layout:auto;width:100%">
        <thead><tr>
            <th>Name</th><th>Age</th><th>Sex</th><th>Civil Status</th><th>Contact</th><th>Role</th><th>Action</th>
        </tr></thead>
        <tbody>
        <?php if(!$members): ?>
            <tr><td colspan="7" class="muted-meta">No live members in this household.</td></tr>
        <?php else: foreach($members as $m):
            $isHead=((int)$m['id']===(int)($hh['head_resident_id']??0));
            $nm=trim($m['last_name'].', '.$m['first_name'].' '.$m['middle_name'],', ');
        ?>
            <tr>
                <td><?= e($nm) ?></td>
                <td style="white-space:nowrap"><?= $cell($m['age']) ?></td>
                <td><?= $cell($m['sex']) ?></td>
                <td><?= $cell($m['civil_status']) ?></td>
                <td style="white-space:nowrap"><?= $cell($m['contact_no']) ?></td>
                <td><?= $isHead ? '<span class="badge badge--info">Head</span>' : '<span class="muted-meta">Member</span>' ?></td>
                <td style="white-space:nowrap"><a class="btn-edit" href="<?= e(url('pages/resident_edit.php?id='.(int)$m['id'])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table>
    </div>
    <?php include BASE_PATH . '/partials/footer.php'; return;
}

// One row per live household with its head of the family and live member count.
$sql="SELECT h.id, h.household_no, h.purok, h.head_resident_id, h.created_at,
             b.barangay_name,
             hd.first_name AS head_first, hd.last_name AS head_last, hd.deleted_at AS head_deleted,
             COALESCE(m.members,0) AS members
        FROM households h
        JOIN barangays b ON b.id=h.barangay_id
        LEFT JOIN residents hd ON hd.id=h.head_resident_id
        LEFT JOIN (SELECT household_id, COUNT(*) members
                     FROM residents
                    WHERE deleted_at IS NULL AND household_id IS NOT NULL
                    GROUP BY household_id) m ON m.household_id=h.id
       WHERE h.deleted_at IS NULL";
$params=[];$types='';
if($_SESSION['role']!=='admin'){ $sql.=" AND h.barangay_id=?";$types.='i';$params[]=$_SESSION['barangay_id']; }
elseif($f_brgy>0){ $sql.=" AND h.barangay_id=?";$types.='i';$params[]=$f_brgy; }
if($q!==''){
    // Match household no., purok, or any live member name (head included).
    $sql.=" AND (h.household_no LIKE ? OR h.purok LIKE ?"
        ." OR EXISTS (SELECT 1 FROM residents m WHERE m.household_id=h.id AND m.deleted_at IS NULL"
        ." AND (m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?)))";
    $like="%$q%";$types.='sssss';$params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;
}

$sql.=" ORDER BY b.barangay_name ASC, h.household_no ASC";
$stmt=$conn->prepare($sql);
if($types)$stmt->bind_param($types,...$params);
$stmt->execute();
$rows=$stmt->get_result();
$total_rows=(int)$rows->num_rows;

$brgys=$conn->query('SELECT id,barangay_name FROM barangays ORDER BY barangay_name ASC');
$brgy_label='All Barangays';
if($f_brgy>0){ $ln=$conn->prepare('SELECT barangay_name FROM barangays WHERE id=? LIMIT 1'); if($ln){ $ln->bind_param('i',$f_brgy); $ln->execute(); $lr=$ln->get_result()->fetch_assoc(); $ln->close(); if($lr){ $brgy_label=$lr['barangay_name']; } } }
elseif($_SESSION['role']!=='admin'){
    $brgy_label=(string)($_SESSION['barangay_name'] ?? 'My Barangay');
    $own=(int)($_SESSION['barangay_id']??0);
    $on=$conn->prepare('SELECT barangay_name FROM barangays WHERE id=? LIMIT 1');
    if($on){ $on->bind_param('i',$own); $on->execute(); $or=$on->get_result()->fetch_assoc(); $on->close(); if($or){ $brgy_label=$or['barangay_name']; } }
}

$cell = function ($v) {
    $s = trim((string)($v ?? ''));
    return $s === '' ? '-' : e($s);
};

include BASE_PATH . '/partials/header.php';?>
<div class="toolbar">
    <form method="get">
        <label class="sr-only" for="hh_q">Search households</label>
        <input id="hh_q" type="search" name="q" value="<?=e($q)?>" placeholder="Search household no., head, or member">
        <?php if($_SESSION['role']==='admin'): ?>
        <label class="sr-only" for="hh_brgy">Filter by Barangay</label>
        <select id="hh_brgy" name="barangay" title="Filter by Barangay">
            <option value="0">All Barangays</option>
            <?php while($bb=$brgys->fetch_assoc()): ?>
            <option value="<?=(int)$bb['id']?>"<?=$f_brgy===(int)$bb['id']?' selected':''?>><?=e($bb['barangay_name'])?></option>
            <?php endwhile; ?>
        </select>
        <?php endif; ?>
        <button class="btn" type="submit">Search</button>
        <a class="btn btn--ghost" href="<?= e(url('pages/households.php')) ?>">Reset</a>
        <a class="btn btn--ghost" href="<?= e(url('pages/residents.php')) ?>">&larr; Back to residents</a>
    </form>
</div>
<p class="muted-meta" role="status">
    Showing <?=number_format($total_rows)?> household<?= $total_rows===1?'':'s' ?> — Barangay: <?=e($brgy_label)?>
</p>
<div class="card table-wrap" style="overflow-x:auto">
    <table style="table-layout:auto;width:100%">
    <thead><tr>
        <th>Household No.</th>
        <th>Barangay</th>
        <th>Head of the Family</th>
        <th style="white-space:nowrap">Members</th>
        <th>Purok / Zone</th>
        <th style="white-space:nowrap">Created</th>
        <th style="white-space:nowrap">Action</th>
    </tr></thead>
    <tbody>
    <?php $headless = 0; while($r=$rows->fetch_assoc()):
        // A head is present when the pointer is set, the resident row still
        // exists (LEFT JOIN produced names) and that resident is not deleted.
        $hasHead = ((int)($r['head_resident_id'] ?? 0) > 0)
                && ($r['head_first'] !== null || $r['head_last'] !== null)
                && ($r['head_deleted'] === null);
        if (!$hasHead) { $headless++; }
        $head_name = trim((string)($r['head_first'] ?? '') . ' ' . (string)($r['head_last'] ?? ''));
    ?>
    <tr>
        <td style="white-space:nowrap"><strong><?= e($r['household_no']) ?></strong></td>
        <td><?= e($r['barangay_name']) ?></td>
        <td>
            <?php if ($hasHead): ?>
                <?= e($head_name) ?>
            <?php else: ?>
                <span class="badge badge--pending">No head yet</span>
            <?php endif; ?>
        </td>
        <td style="white-space:nowrap"><?= (int)$r['members'] ?></td>
        <td><?= $cell($r['purok']) ?></td>
        <td style="white-space:nowrap"><?= $r['created_at'] ? date('M j, Y', strtotime($r['created_at'])) : '-' ?></td>
        <td style="white-space:nowrap"><a class="btn-edit" href="<?= e(url('pages/households.php?id='.(int)$r['id'])) ?>">View members</a></td>
    </tr><?php endwhile; ?>
    </tbody></table>
</div>
<?php if ($headless > 0): ?>
<div class="alert" role="status">
    <?= (int)$headless ?> household<?= $headless === 1 ? '' : 's' ?> still ha<?= $headless === 1 ? 's' : 've' ?> no head of the family.
    Open the resident's edit page and tick <strong>Head of this household</strong> to assign one.
</div>
<?php endif; ?>
<?php include BASE_PATH . '/partials/footer.php'; ?>