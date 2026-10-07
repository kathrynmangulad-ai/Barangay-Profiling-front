<?php
require_once __DIR__ . '/../config/config.php'; require_admin(); $page_title='Barangays';
if($_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); $name=trim($_POST['barangay_name']); 
if($name){$s=$conn->prepare('INSERT INTO barangays(barangay_name) VALUES(?)');
$s->bind_param('s',$name);$s->execute();}redirect(url('admin/barangays.php')); }
$q=trim($_GET['q']??'');
// Per-barangay totals. Soft-deleted residents (deleted_at NOT NULL) are
// excluded from every aggregate so the numbers match the Residents page.
$select='SELECT b.*,
    COUNT(CASE WHEN r.deleted_at IS NULL THEN r.id END) residents,
    COUNT(DISTINCT CASE WHEN r.deleted_at IS NULL AND r.household_no IS NOT NULL AND r.household_no <> \'\' THEN r.household_no END) households,
    COUNT(CASE WHEN r.deleted_at IS NULL AND r.is_student IN (\'Yes\',1,\'1\') THEN r.id END) students
   FROM barangays b
   LEFT JOIN residents r ON r.barangay_id=b.id';
if($q!==''){ $s=$conn->prepare($select.' WHERE b.barangay_name LIKE ? GROUP BY b.id ORDER BY b.barangay_name');
$like="%$q%"; $s->bind_param('s',$like); $s->execute(); $rows=$s->get_result();
} else {
$rows=$conn->query($select.' GROUP BY b.id ORDER BY b.barangay_name');
}
 include BASE_PATH . '/partials/header.php';?>
        <div class="card table-wrap">
            <table><tr>
                <th>ID</th>
                <th>Barangay</th>
                <th>Residents</th>
                <th>Households</th>
                <th>Students</th></tr>
                <?php while($r=$rows->fetch_assoc()): ?>
                <tr><td><?=$r['id']?></td><td><?=e($r['barangay_name'])?></td>
                <td><?=(int)$r['residents']?></td>
                <td><?=(int)$r['households']?></td>
                <td><?=(int)$r['students']?></td></tr><?php endwhile; ?>
                </table></div><?php include BASE_PATH . '/partials/footer.php';
