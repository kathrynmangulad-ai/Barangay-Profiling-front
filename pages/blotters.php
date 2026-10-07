<?php
require_once __DIR__ . '/../config/config.php'; require_staff(); $page_title='Blotter Records';
$f_q=trim($_GET['q']??''); $f_brgy=(int)($_GET['barangay']??0);
$sql='SELECT bl.*,b.barangay_name FROM blotter_records bl JOIN barangays b ON b.id=bl.barangay_id WHERE 1';
$types='';$params=[];
if($_SESSION['role']!=='admin'){$sql.=' AND bl.barangay_id=?';
$types.='i';$params[]=$_SESSION['barangay_id'];}
if($f_q!==''){ $sql.=' AND (bl.reporting_person LIKE ? OR bl.incident_type LIKE ? OR bl.blotter_no LIKE ? OR bl.place_of_incident LIKE ? OR DATE_FORMAT(bl.incident_datetime,"%Y-%m-%d %H:%i") LIKE ? OR DATE_FORMAT(bl.incident_datetime,"%M %d, %Y") LIKE ? OR DATE_FORMAT(bl.report_datetime,"%Y-%m-%d %H:%i") LIKE ?)';
$types.='sssssss';$like="%$f_q%";for($i=0;$i<7;$i++){$params[]=$like;} }
if($f_brgy>0 && $_SESSION['role']==='admin'){ $sql.=' AND bl.barangay_id=?'; $types.='i'; $params[]=$f_brgy; }
$sql.=' ORDER BY bl.id DESC';
$s=$conn->prepare($sql);if($types)
$s->bind_param($types,...$params);
$s->execute();$rows=$s->get_result();
$brgys=$conn->query('SELECT id,barangay_name FROM barangays ORDER BY barangay_name');
include BASE_PATH . '/partials/header.php';?>

<div class="toolbar">
    <form method="get">
        <input type="hidden" name="q" value="<?=e($f_q)?>">

        <?php if($_SESSION['role']==='admin'): ?>
        <label class="sr-only" for="b_brgy">Barangay</label>
        <select id="b_brgy" name="barangay">
            <option value="0">All barangays</option>
            <?php while($bb=$brgys->fetch_assoc()): ?>
            <option value="<?=(int)$bb['id']?>"<?=$f_brgy===(int)$bb['id']?' selected':''?>><?=e($bb['barangay_name'])?></option>
            <?php endwhile; ?>
        </select>
        <?php endif; ?>
        <button class="btn" type="submit">Search</button>
        <a class="btn btn--ghost" href="<?= e(url('pages/blotters.php')) ?>">Reset</a>
    </form>
    <a class="btn push-right" href="<?= e(url('pages/blotter_add.php')) ?>">+ Add new</a>
</div>
    <div class="card table-wrap">
        <table><tr><th>No.</th><th>Reporting Person</th>
        <th>Incident</th><th>Date/Time</th><th>Barangay</th>
        <th>Action</th></tr><?php while($r=$rows->fetch_assoc()): ?>
        <tr><td><?=e($r['blotter_no'])?></td><td><?=e($r['reporting_person'])?></td>
        <td><?=e($r['incident_type'])?></td><td><?=e($r['incident_datetime'])?></td>
        <td><?=e($r['barangay_name'])?></td>
        <td><a class="btn" href="<?= e(url('pages/incident_report.php?id=' . $r['id'])) ?>">Receipt</a></td></tr>
        <?php endwhile; ?></table></div><?php include BASE_PATH . '/partials/footer.php';
