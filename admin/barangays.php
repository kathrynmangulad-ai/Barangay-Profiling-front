<?php
require_once __DIR__ . '/../config/config.php'; require_admin(); $page_title='Barangays';
// Punong Barangay (certificate signatory) - column added by database/update.sql.
$has_captain=false;
if($cc=$conn->query("SHOW COLUMNS FROM barangays LIKE 'punong_barangay'")){ $has_captain=$cc->num_rows>0; $cc->free(); }
if($_SERVER['REQUEST_METHOD']==='POST'){ require_csrf();
    if(($_POST['action']??'')==='set_captain'){
        $bid=(int)($_POST['barangay_id']??0);
        $captain=trim(preg_replace('/\s+/',' ',(string)($_POST['punong_barangay']??'')));
        $captain=trim(preg_replace('/^hon\.?\s+/i','',$captain));
        if($has_captain && $bid>0 && mb_strlen($captain)<=150){
            $val=($captain==='')?null:$captain;
            $s=$conn->prepare('UPDATE barangays SET punong_barangay=? WHERE id=?');
            $s->bind_param('si',$val,$bid); $s->execute(); $s->close();
            log_access('barangay_captain_set','barangay',$bid);
            flash('ok', $captain==='' ? 'Punong Barangay cleared.' : 'Punong Barangay saved.');
        }
        $back=trim((string)($_POST['q']??''));
        redirect(url('admin/barangays.php'.($back!==''?'?q='.rawurlencode($back):'')));
    }
    $name=trim((string)($_POST['barangay_name']??''));
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
                <?php if($has_captain): ?><th>Punong Barangay</th><?php endif; ?>
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
                <?php if($has_captain): ?>
                <td>
                    <form method="post" class="captain-form" style="display:flex;gap:6px;align-items:center;margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_captain">
                        <input type="hidden" name="barangay_id" value="<?=(int)$r['id']?>">
                        <input type="hidden" name="q" value="<?=e($q)?>">
                        <label class="sr-only" for="captain-<?=(int)$r['id']?>">Punong Barangay of <?=e($r['barangay_name'])?></label>
                        <input id="captain-<?=(int)$r['id']?>" name="punong_barangay" maxlength="150" placeholder="e.g. Juan A. Dela Cruz"
                               value="<?=e($r['punong_barangay'] ?? '')?>" style="min-width:210px;padding:7px 10px">
                        <button class="btn btn--ghost" type="submit" style="padding:7px 12px">Save</button>
                    </form>
                </td>
                <?php endif; ?>
                <td><?=(int)$r['residents']?></td>
                <td><?=(int)$r['households']?></td>
                <td><?=(int)$r['students']?></td>
                <td><?=(int)$r['pwd']?></td>
                <td><?=(int)$r['seniors']?></td>
                <td><?=($has_fp?(int)$r['fourps_total']:'-')?></td>
                <td><?=($has_ip?(int)$r['ip_total']:'-')?></td></tr><?php endwhile; ?>
                <tr style="font-weight:700;background:rgba(0,0,0,.04)">
                    <td>&mdash;</td><td>Total (<?= number_format($tot['brgy']) ?>)</td>
                    <?php if($has_captain): ?><td></td><?php endif; ?>
                    <td><?= number_format($tot['residents']) ?></td>
                    <td><?= number_format($tot['households']) ?></td>
                    <td><?= number_format($tot['students']) ?></td>
                    <td><?= number_format($tot['pwd']) ?></td>
                    <td><?= number_format($tot['seniors']) ?></td>
                    <td><?= $has_fp ? number_format($tot['fourps']) : '-' ?></td>
                    <td><?= $has_ip ? number_format($tot['ip']) : '-' ?></td>
                </tr>
                </table></div><?php include BASE_PATH . '/partials/footer.php';
