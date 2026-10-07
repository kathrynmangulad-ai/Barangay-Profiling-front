<?php
require_once __DIR__ . '/../config/config.php'; require_admin(); $id=(int)($_GET['id']??0);$page_title='Edit User';
$s=$conn->prepare('SELECT * FROM users WHERE id=? AND deleted_at IS NULL');
$s->bind_param('i',$id);$s->execute();
$r=$s->get_result()->fetch_assoc();$s->close();if(!$r)die('User not found.');

$error='';
$barangays=$conn->query('SELECT id,barangay_name FROM barangays ORDER BY barangay_name');

if($_SERVER['REQUEST_METHOD']==='POST')
{
    require_csrf();

    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    

    $role = in_array($_POST['role'] ?? '', ['admin','secretary','resident'], true) ? $_POST['role'] : $r['role'];
    $new_password = (string)($_POST['password'] ?? '');
    $barangay_id = ($role === 'admin') ? null : (int)($_POST['barangay_id'] ?? 0);

    if($username === '' || $full_name === ''){
        $error='Username and full name are required.';
    }elseif($new_password !== '' && strlen($new_password) < 8){
        $error='Password must be at least 8 characters, or leave it blank to keep the current one.';
    }else{
        if($new_password !== ''){
            

            $up = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt=$conn->prepare('UPDATE users SET username=?,full_name=?,role=?,barangay_id=?,password=? WHERE id=?');
            $stmt->bind_param('sssisi',$username,$full_name,$role,$barangay_id,$up,$id);
        }else{
             
            $stmt=$conn->prepare('UPDATE users SET username=?,full_name=?,role=?,barangay_id=? WHERE id=?');
            $stmt->bind_param('ssssi',$username,$full_name,$role,$barangay_id,$id);
        }
        if($stmt->execute()){
            $stmt->close();
            log_access('user_edited','user',$id);
            redirect(url('admin/users.php'));
        }
        $stmt->close();
        $error='Could not save the account.';
    }

     
    $r['username']=$username; $r['full_name']=$full_name; $r['role']=$role; $r['barangay_id']=$barangay_id;
}

include BASE_PATH . '/partials/header.php';?>
<h2>Edit User</h2>
<?php if($error!==''){ ?><div class="alert alert-danger"><?= e($error) ?></div><?php } ?>
<div class="card"><form method="post"><?= csrf_field() ?><div class="form-grid">

    <div><label for="username">Username</label><input id="username" name="username" value="<?=e($r['username'])?>" required></div>
    <div><label for="full_name">Full Name</label><input id="full_name" name="full_name" value="<?=e($r['full_name'])?>" required></div>

    <div>
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="admin"<?= $r['role']==='admin' ? ' selected' : '' ?>>Admin</option>
            <option value="secretary"<?= $r['role']==='secretary' ? ' selected' : '' ?>>Secretary</option>
            <option value="resident"<?= $r['role']==='resident' ? ' selected' : '' ?>>Resident</option>
        </select>
    </div>

    <div>
        <label for="barangay_id">Barangay</label>
        <select id="barangay_id" name="barangay_id">
            <?php while($b=$barangays->fetch_assoc()): ?>
                <option value="<?= (int)$b['id'] ?>"<?= (int)$r['barangay_id']===(int)$b['id'] ? ' selected' : '' ?>>
                    <?= e($b['barangay_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div>
        <label for="password">New Password</label>
        <input id="password" type="password" name="password" minlength="8" autocomplete="new-password">
        <p class="field-hint">Leave blank to keep the current password.</p>
    </div>

</div><br><button class="btn" type="submit">Update</button></form></div><?php include BASE_PATH . '/partials/footer.php';
