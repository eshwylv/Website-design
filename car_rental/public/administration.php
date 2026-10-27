<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/layout.php';

$currentUser=require_role($pdo,['ADMIN']);
$message='';
$temporaryPassword='';
$temporaryFor='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action'] ?? '';

    if($action==='create'){
        $username=trim($_POST['username'] ?? '');
        $email=trim($_POST['email'] ?? '');
        $password=$_POST['password'] ?? '';
        $role=$_POST['role'] ?? '';

        if(!in_array($role,['ADMIN','MANAGER','RENTAL_STAFF'],true) || strlen($username)<3 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<8){
            $message='Enter a username, a password with at least 8 characters, and a role.';
        } else {
            try{
                $stmt=$pdo->prepare("INSERT INTO users(username,email,password_hash,role,status) VALUES(:u,:e,:p,:r,'ACTIVE')");
                $stmt->execute([':u'=>$username,':e'=>$email,':p'=>password_hash($password,PASSWORD_DEFAULT),':r'=>$role]);
                audit_log($pdo,(int)$currentUser['id'],'USER_CREATED',$username,'SUCCESS');
                $message='Account created.';
            }catch(Throwable $e){
                $message='Could not create the account. The username may be taken.';
            }
        }
    }

    if($action==='status'){
        $id=(int)($_POST['user_id'] ?? 0);
        $status=$_POST['status'] ?? '';
        if($id===(int)$currentUser['id']){
            $message='You cannot change your own account status.';
        } elseif(in_array($status,['ACTIVE','LOCKED'],true)){
            $stmt=$pdo->prepare("UPDATE users SET status=:s, failed_attempts=0 WHERE id=:id");
            $stmt->execute([':s'=>$status,':id'=>$id]);
            audit_log($pdo,(int)$currentUser['id'],'USER_STATUS_CHANGED',(string)$id,'SUCCESS');
            $message='Account status changed.';
        }
    }

    if($action==='process_reset'){
        $requestId=(int)($_POST['request_id'] ?? 0);

        $stmt=$pdo->prepare(
            "SELECT r.id,r.user_id,r.status,u.username
             FROM password_reset_requests r
             JOIN users u ON u.id=r.user_id
             WHERE r.id=:id
             FOR UPDATE"
        );

        try{
            $pdo->beginTransaction();
            $stmt->execute([':id'=>$requestId]);
            $request=$stmt->fetch();

            if(!$request || $request['status']!=='PENDING'){
                throw new RuntimeException('This reset request is already closed.');
            }
            if((int)$request['user_id']===(int)$currentUser['id']){
                throw new RuntimeException('Ask another administrator to reset your account.');
            }

            /* Cryptographically random temporary password, shown once only. */
            $temporaryPassword='Tmp!'.bin2hex(random_bytes(6));
            $temporaryFor=$request['username'];

            $update=$pdo->prepare(
                "UPDATE users
                 SET password_hash=:hash,
                     status='ACTIVE',
                     failed_attempts=0,
                     must_change_password=1
                 WHERE id=:uid"
            );
            $update->execute([
                ':hash'=>password_hash($temporaryPassword,PASSWORD_DEFAULT),
                ':uid'=>$request['user_id']
            ]);

            $done=$pdo->prepare(
                "UPDATE password_reset_requests
                 SET status='COMPLETED',processed_by=:admin,processed_at=NOW()
                 WHERE id=:id"
            );
            $done->execute([':admin'=>$currentUser['id'],':id'=>$requestId]);

            audit_log(
                $pdo,
                (int)$currentUser['id'],
                'PASSWORD_RESET_ADMIN_COMPLETED',
                $request['username'],
                'SUCCESS'
            );

            $pdo->commit();
            $message='Password reset. Check the identity of the user before sharing the temporary password.';
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            $temporaryPassword='';
            $temporaryFor='';
            $message=$e->getMessage();
        }
    }

    if($action==='cancel_reset'){
        $requestId=(int)($_POST['request_id'] ?? 0);
        $stmt=$pdo->prepare(
            "UPDATE password_reset_requests
             SET status='CANCELLED',processed_by=:admin,processed_at=NOW()
             WHERE id=:id AND status='PENDING'"
        );
        $stmt->execute([':admin'=>$currentUser['id'],':id'=>$requestId]);
        audit_log($pdo,(int)$currentUser['id'],'PASSWORD_RESET_REQUEST_CANCELLED',(string)$requestId,'SUCCESS');
        $message='Reset request cancelled.';
    }
}

$users=$pdo->query("SELECT id,username,email,role,status,failed_attempts,created_at FROM users ORDER BY id")->fetchAll();
$resetRequests=$pdo->query(
    "SELECT r.id,r.status,r.requested_at,u.username,u.email,u.role
     FROM password_reset_requests r
     JOIN users u ON u.id=r.user_id
     ORDER BY CASE WHEN r.status='PENDING' THEN 0 ELSE 1 END,r.id DESC
     LIMIT 50"
)->fetchAll();

render_header('Administration','administration.php');
if($message) echo '<div class="notice">'.h($message).'</div>';
if($temporaryPassword){
    echo '<div class="notice warning"><strong>Temporary password — shown once</strong><br>User: '.h($temporaryFor).'<br>Temporary password: <code>'.h($temporaryPassword).'</code><br><small>The user must change this password after login.</small></div>';
}
?>
<div class="panel">
<h3>Create Staff Account</h3>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="create">
<div class="formgrid">
<label>Username<input name="username" required></label>
<label>Email<input type="email" name="email" required></label>
<label>Password<input type="password" name="password" minlength="8" required></label>
<label>Role
<select name="role" required>
<option value="ADMIN">System Administrator</option>
<option value="MANAGER">Manager</option>
<option value="RENTAL_STAFF">Rental Staff</option>
</select>
</label>
</div><br>
<button type="submit">Create User</button>
</form>
</div>

<div class="panel">
<h3>Password Reset Requests</h3>
<p>View password reset requests here. Check the identity of the staff member before resetting the password.</p>
<div class="tablewrap">
<table>
<tr><th>Request</th><th>User</th><th>Email</th><th>Role</th><th>Requested</th><th>Status</th><th>Action</th></tr>
<?php foreach($resetRequests as $r): ?>
<tr>
<td>PR-<?=str_pad((string)$r['id'],4,'0',STR_PAD_LEFT)?></td>
<td><?=h($r['username'])?></td>
<td><?=h($r['email'])?></td>
<td><?=h(role_label($r['role']))?></td>
<td><?=h($r['requested_at'])?></td>
<td><span class="badge"><?=h($r['status'])?></span></td>
<td>
<?php if($r['status']==='PENDING'): ?>
<div class="admin-actions">
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="process_reset">
<input type="hidden" name="request_id" value="<?=$r['id']?>">
<button type="submit">Reset Password</button>
</form>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="cancel_reset">
<input type="hidden" name="request_id" value="<?=$r['id']?>">
<button class="btn secondary" type="submit">Cancel</button>
</form>
</div>
<?php else: ?>Processed<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

<div class="panel">
<h3>Staff Accounts</h3>
<div class="tablewrap">
<table>
<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Failed Attempts</th><th>Action</th></tr>
<?php foreach($users as $u): ?>
<tr>
<td><?=h($u['username'])?></td>
<td><?=h($u['email'])?></td>
<td><?=h(role_label($u['role']))?></td>
<td><span class="badge"><?=h($u['status'])?></span></td>
<td><?=$u['failed_attempts']?></td>
<td>
<?php if((int)$u['id'] !== (int)$currentUser['id']): ?>
<div class="admin-actions">
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="status">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<input type="hidden" name="status" value="<?=$u['status']==='ACTIVE'?'LOCKED':'ACTIVE'?>">
<button class="btn secondary" type="submit"><?=$u['status']==='ACTIVE'?'Lock':'Unlock'?></button>
</form>


</div>
<?php else: ?>Current account<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

<section class="panel">
<h2>Audit Logs</h2>
<p>Recent login and account activity.</p>
<?php
$auditRows=$pdo->query(
 "SELECT a.created_at,u.username,a.event_type,a.target,a.result,a.ip_address
  FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
  ORDER BY a.id DESC LIMIT 100"
)->fetchAll();
?>
<div class="table-wrap">
<table>
<thead><tr><th>Date/Time</th><th>User</th><th>Event</th><th>Target</th><th>Result</th><th>IP</th></tr></thead>
<tbody>
<?php foreach($auditRows as $a): ?>
<tr>
<td><?=h((string)$a['created_at'])?></td>
<td><?=h((string)($a['username']??'System'))?></td>
<td><?=h((string)$a['event_type'])?></td>
<td><?=h((string)($a['target']??''))?></td>
<td><?=h((string)$a['result'])?></td>
<td><?=h((string)($a['ip_address']??''))?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<?php render_footer(); ?>
