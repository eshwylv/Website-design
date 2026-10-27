<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/layout.php';

$currentUser = require_active_user($pdo);
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=:id LIMIT 1");
    $stmt->execute([':id'=>(int)$currentUser['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $message = 'Current password is incorrect.';

    } elseif (strlen($new) < 8) {
        $message = 'New password must contain at least 8 characters.';
    } elseif ($new !== $confirm) {
        $message = 'New password and confirmation do not match.';
    } elseif (password_verify($new, $row['password_hash'])) {
        $message = 'New password must be different from the current password.';
    } else {
        $update = $pdo->prepare(
            "UPDATE users
             SET password_hash=:hash,
                 failed_attempts=0,
                 must_change_password=0
             WHERE id=:id"
        );
        $update->execute([
            ':hash'=>password_hash($new, PASSWORD_DEFAULT),
            ':id'=>(int)$currentUser['id']
        ]);
        $_SESSION['user']['must_change_password'] = 0;
        session_regenerate_id(true);

        $message = 'Password changed successfully.';
        $success = true;
    }
}

render_header('Change Password','change_password.php');
if ((int)($currentUser['must_change_password'] ?? 0) === 1) {
    echo '<div class="notice warning"><strong>Password change required.</strong> The administrator issued a temporary password. Change it before continuing normal work.</div>';
}
if ($message) {
    echo '<div class="notice">'.h($message).'</div>';
}
?>
<div class="panel narrow-panel">
<h3>Change Your Password</h3>
<p>Use your current password and choose a new password of at least 8 characters.</p>
<form method="post">
<?=csrf_field()?>
<div class="formgrid single">
<label>Current Password
<input type="password" name="current_password" autocomplete="current-password" required>
</label>
<label>New Password
<input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
</label>
<label>Confirm New Password
<input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
</label>
</div>
<br>
<button type="submit">Change Password</button>
<?php if($success): ?>
<a class="btn secondary" href="dashboard.php">Return to Dashboard</a>
<?php endif; ?>
</form>
</div>
<?php render_footer(); ?>
