<?php
require_once __DIR__ . '/../config/config.php'; require_staff(); $page_title='New Blotter';
if($_SERVER['REQUEST_METHOD']==='POST'){ require_csrf();
    $bid=$_SESSION['role']==='admin'?(int)
    $_POST['barangay_id']:(int)$_SESSION['barangay_id'];
    $stmt=$conn->prepare('INSERT INTO blotter_records
    (barangay_id,blotter_no,reporting_person,reporting_address,
    incident_type,report_datetime,incident_datetime,place_of_incident,
    suspect_data,victim_data,narrative,recorded_by) 
    VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $recorded=$_SESSION['full_name'];
    $stmt->bind_param(
        'isssssssssss',
        $bid,
        $_POST['blotter_no'],
        $_POST['reporting_person'],
        $_POST['reporting_address'],
        $_POST['incident_type'],
        $_POST['report_datetime'],
        $_POST['incident_datetime'],
        $_POST['place_of_incident'],
        $_POST['suspect_data'],
        $_POST['victim_data'],
        $_POST['narrative'],$recorded);
        $stmt->execute();$id=$conn->insert_id;redirect(url('pages/incident_report.php?id='.$id));}
$next=1;$x=$conn->query("SELECT COALESCE(MAX(CAST(blotter_no AS UNSIGNED)),0)+1 n FROM blotter_records");
if($x)$next=$x->fetch_assoc()['n'];
include BASE_PATH . '/partials/header.php';?>

<div class="card"><form method="post"><?= csrf_field() ?>
<div class="form-grid"><?php 
if($_SESSION['role']==='admin'): ?>
<div><label>Barangay</label>
<select name="barangay_id">
    <?php $bs=$conn->query('SELECT * FROM barangays ORDER BY barangay_name');
    while($b=$bs->fetch_assoc()): ?>
    <option value="<?=$b['id']?>"><?=e($b['barangay_name'])?></option>
    <?php endwhile; ?></select></div><?php endif; ?>
    <div><label>Blotter Entry Number</label>
    <input name="blotter_no" value="<?=e($next)?>" required></div>
    <div><label>Reporting Person</label><input name="reporting_person" required></div>
    <div><label>Address of Reporting Person</label><input name="reporting_address"></div>
    <div><label>Type of Incident</label><input name="incident_type" required></div>
    <div><label>Date/Time of Report</label><input type="datetime-local"
     name="report_datetime" value="<?=date('Y-m-d')?>"></div>
     <div><label>Date/Time of Incident</label><input type="datetime-local" 
     name="incident_datetime"></div><div><label>Place of Incident</label>
     <input name="place_of_incident"></div><div class="full">
        <label>Suspect Data</label><textarea name="suspect_data"></textarea>
        </div><div class="full"><label>Victim Data</label><textarea name="victim_data"></textarea>
        </div><div class="full"><label>Narrative of Incident</label>
        <textarea name="narrative" rows="5"></textarea></div></div><br>
        <button class="btn">Save and View Receipt</button>
        <a class="btn btn--ghost" href="<?= e(url('pages/blotters.php')) ?>">Cancel</a></form></div>
        <?php include BASE_PATH . '/partials/footer.php';

