<?php
require_once __DIR__ . '/../config/config.php'; require_staff(); $page_title='Blotter Records';
$f_q=trim($_GET['q']??''); $f_brgy=(int)($_GET['barangay']??0); $f_year=(int)($_GET['year']??0);
/* Years present in blotter records (for the Year dropdown). Scoped to the
 * viewer's barangay for non-admins so the list never leaks other barangays. */
$years=[];
if($_SESSION['role']!=='admin'){ if($ys=$conn->prepare('SELECT DISTINCT YEAR(incident_datetime) y FROM blotter_records WHERE barangay_id=? ORDER BY y DESC')){ $ys->bind_param('i',$_SESSION['barangay_id']); $ys->execute(); $yr=$ys->get_result(); while($rw=$yr->fetch_assoc()){ if($rw['y']!==null){ $years[]=(int)$rw['y']; } } $ys->close(); } }
else { if($yq=$conn->query('SELECT DISTINCT YEAR(incident_datetime) y FROM blotter_records ORDER BY y DESC')){ while($rw=$yq->fetch_assoc()){ if($rw['y']!==null){ $years[]=(int)$rw['y']; } } $yq->free(); } }
$sql='SELECT bl.*,b.barangay_name FROM blotter_records bl JOIN barangays b ON b.id=bl.barangay_id WHERE 1';
$types='';$params=[];
if($_SESSION['role']!=='admin'){$sql.=' AND bl.barangay_id=?';
$types.='i';$params[]=$_SESSION['barangay_id'];}
if($f_q!==''){ $sql.=' AND (bl.reporting_person LIKE ? OR bl.incident_type LIKE ? OR bl.blotter_no LIKE ? OR bl.place_of_incident LIKE ? OR DATE_FORMAT(bl.incident_datetime,"%Y-%m-%d %H:%i") LIKE ? OR DATE_FORMAT(bl.incident_datetime,"%M %d, %Y") LIKE ? OR DATE_FORMAT(bl.report_datetime,"%Y-%m-%d %H:%i") LIKE ?)';
$types.='sssssss';$like="%$f_q%";for($i=0;$i<7;$i++){$params[]=$like;} }
if($f_brgy>0 && $_SESSION['role']==='admin'){ $sql.=' AND bl.barangay_id=?'; $types.='i'; $params[]=$f_brgy; }
if($f_year>0){ $sql.=' AND YEAR(bl.incident_datetime)=?'; $types.='i'; $params[]=$f_year; }
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
        <label class="sr-only" for="b_year">Year</label>
        <select id="b_year" name="year" onchange="this.form.submit()">
            <option value="0">All years</option>
            <?php foreach($years as $yy): ?>
            <option value="<?=(int)$yy?>"<?=$f_year===$yy?' selected':''?>><?=(int)$yy?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filter</button>
        <?php if($_SESSION['role']==='admin'): ?>
        <a class="btn btn--ghost" href="<?= e(url('pages/blotters.php')) ?>">Reset</a>
        <?php endif; ?>
    </form>
    <a class="btn push-right" href="<?= e(url('pages/blotter_add.php')) ?>">+ Add new</a>
</div>
    <p class="muted-meta" role="status">Total: <?= number_format($rows->num_rows) ?> record<?= $rows->num_rows === 1 ? '' : 's' ?></p>
    <div class="card table-wrap">
        <table><tr><th>No.</th><th>Reporting Person</th>
        <th>Incident</th><th>Date/Time</th><th>Barangay</th>
        <th>Action</th></tr><?php while($r=$rows->fetch_assoc()): ?>
        <tr><td><?=e($r['blotter_no'])?></td><td><?=e($r['reporting_person'])?></td>
        <td><?=e($r['incident_type'])?></td><td><?=e($r['incident_datetime'])?></td>
        <td><?=e($r['barangay_name'])?></td>
        <td><a class="btn" href="<?= e(url('pages/incident_report.php?id=' . $r['id'])) ?>">Receipt</a></td></tr>
        <?php endwhile; ?></table></div><?php include BASE_PATH . '/partials/footer.php';
