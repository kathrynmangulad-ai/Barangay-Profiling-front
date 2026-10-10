<?php
require_once __DIR__ . '/../config/config.php'; require_admin(); $page_title='Barangays';
if($_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); $name=trim($_POST['barangay_name']); 
if($name){$s=$conn->prepare('INSERT INTO barangays(barangay_name) VALUES(?)');
$s->bind_param('s',$name);$s->execute();}redirect(url('admin/barangays.php')); }
$q=trim($_GET['q']??'');
// Per-barangay totals. Soft-deleted residents (deleted_at NOT NULL) are
// excluded from every aggregate so the numbers match the Residents page.
// Seniors = age 60+, same rule as the dashboard. IP / 4Ps columns are
// detected (they vary by install); when missing the cells show '-'.
$ip_col=null; $fp_col=null;
foreach(['is_ip','is_indigenous','indigenous'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $ip_col=$cand; $qc->free(); break; } $qc->free(); } }
foreach(['is_4ps','is_fourps','fourps','pantawid'] as $cand){ $qc=$conn->query("SHOW COLUMNS FROM residents LIKE '$cand'"); if($qc){ if($qc->num_rows>0){ $fp_col=$cand; $qc->free(); break; } $qc->free(); } }
$has_ip=($ip_col!==null); $has_fp=($fp_col!==null);
$ipAgg=$has_ip?"COUNT(CASE WHEN r.deleted_at IS NULL AND r.`$ip_col` IN ('Yes',1,'1') THEN r.id END) ip_total":"0 ip_total";
$fpAgg=$has_fp?"COUNT(CASE WHEN r.deleted_at IS NULL AND r.`$fp_col` IN ('Yes',1,'1') THEN r.id END) fourps_total":"0 fourps_total";
$select='SELECT b.*,
    COUNT(CASE WHEN r.deleted_at IS NULL THEN r.id END) residents,
    COUNT(CASE WHEN r.deleted_at IS NULL AND r.is_student IN (\'Yes\',1,\'1\') THEN r.id END) students,
    COUNT(CASE WHEN r.deleted_at IS NULL AND r.is_pwd IN (\'Yes\',1,\'1\') THEN r.id END) pwd,
    COUNT(CASE WHEN r.deleted_at IS NULL AND r.age >= 60 THEN r.id END) seniors, '
    .$fpAgg.', '.$ipAgg.', COALESCE(hh.households, 0) households
    FROM barangays b
   LEFT JOIN residents r ON r.barangay_id=b.id
   LEFT JOIN (SELECT barangay_id, COUNT(*) households
                FROM households WHERE deleted_at IS NULL GROUP BY barangay_id) hh ON hh.barangay_id=b.id';
if($q!==''){ $s=$conn->prepare($select.' WHERE b.barangay_name LIKE ? GROUP BY b.id ORDER BY b.barangay_name');
$like="%$q%"; $s->bind_param('s',$like); $s->execute(); $rows=$s->get_result();
} else {
$rows=$conn->query($select.' GROUP BY b.id ORDER BY b.barangay_name');
}
$tot=['brgy'=>0,'residents'=>0,'households'=>0,'students'=>0,'pwd'=>0,'seniors'=>0,'fourps'=>0,'ip'=>0];
 include BASE_PATH . '/partials/header.php';?>
        <p class="muted-meta" role="status">Total: <?= number_format($rows->num_rows) ?> barangay<?= $rows->num_rows === 1 ? '' : 's' ?></p>
        <div class="card table-wrap">
            <table><tr>
                <th>ID</th>
                <th>Barangay</th>
                <th>Residents</th>
                <th>Households</th>
                <th>Students</th>
                <th>PWD</th>
                <th>Seniors</th>
                <th>4Ps</th>
                <th>IP</th></tr>
                <?php while($r=$rows->fetch_assoc()):
                $tot['brgy']++; $tot['residents']+=(int)$r['residents']; $tot['households']+=(int)$r['households'];
                $tot['students']+=(int)$r['students']; $tot['pwd']+=(int)$r['pwd']; $tot['seniors']+=(int)$r['seniors'];
                $tot['fourps']+=(int)$r['fourps_total']; $tot['ip']+=(int)$r['ip_total']; ?>
                <tr><td><?=$r['id']?></td><td><?=e($r['barangay_name'])?></td>
                <td><?=(int)$r['residents']?></td>
                <td><?=(int)$r['households']?></td>
                <td><?=(int)$r['students']?></td>
                <td><?=(int)$r['pwd']?></td>
                <td><?=(int)$r['seniors']?></td>
                <td><?=($has_fp?(int)$r['fourps_total']:'-')?></td>
                <td><?=($has_ip?(int)$r['ip_total']:'-')?></td></tr><?php endwhile; ?>
                <tr style="font-weight:700;background:rgba(0,0,0,.04)">
                    <td>&mdash;</td><td>Total (<?= number_format($tot['brgy']) ?>)</td>
                    <td><?= number_format($tot['residents']) ?></td>
                    <td><?= number_format($tot['households']) ?></td>
                    <td><?= number_format($tot['students']) ?></td>
                    <td><?= number_format($tot['pwd']) ?></td>
                    <td><?= number_format($tot['seniors']) ?></td>
                    <td><?= $has_fp ? number_format($tot['fourps']) : '-' ?></td>
                    <td><?= $has_ip ? number_format($tot['ip']) : '-' ?></td>
                </tr>
                </table></div><?php include BASE_PATH . '/partials/footer.php';
