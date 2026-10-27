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
        $message = 'Current password is wrong.';
        audit_log($pdo, (int)$currentUser['id'], 'PASSWORD_CHANGE_FAILED', $currentUser['username'], 'FAILED');
    } elseif (strlen($new) < 8) {
        $message = 'Use at least 8 characters for the new password.';
    } elseif ($new !== $confirm) {
        $message = 'The new passwords do not match.';
    } elseif (password_verify($new, $row['password_hash'])) {
        $message = 'Choose a different password.';
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
        audit_log($pdo, (int)$currentUser['id'], 'PASSWORD_CHANGED', $currentUser['username'], 'SUCCESS');
        $message = 'Password changed.';
        $success = true;
    }
}

render_header('Change Password','change_password.php');
if ((int)($currentUser['must_change_password'] ?? 0) === 1) {
    echo '<div class="notice warning">Change your temporary password before continuing.</div>';
}
if ($message) {
    echo '<div class="notice">'.h($message).'</div>';
}
?>
<div class="panel narrow-panel password-panel">
<h3>Change Your Password</h3>
<p>Enter your current password. Choose a new password with at least 8 characters.</p>
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
<div class="password-actions">
<button type="submit">Change Password</button>
<?php if($success): ?>
<a class="btn secondary" href="dashboard.php">Return to Dashboard</a>
<?php endif; ?>
</div>
</form>
</div>
<?php render_footer(); ?>
