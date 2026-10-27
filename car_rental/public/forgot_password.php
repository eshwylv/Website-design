<?php
declare(strict_types=1);

require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/session.php';
require_once __DIR__.'/../includes/csrf.php';
require_once __DIR__.'/../includes/audit.php';

$message = '';
$requestRef = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare(
        "SELECT id,username,email
         FROM users
         WHERE username=:username AND email=:email AND status IN ('ACTIVE','LOCKED')
         LIMIT 1"
    );
    $stmt->execute([':username'=>$username, ':email'=>$email]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->beginTransaction();
        try {
            /* Avoid duplicate open requests for the same account. */
            $existing = $pdo->prepare(
                "SELECT id FROM password_reset_requests
                 WHERE user_id=:uid AND status='PENDING'
                 ORDER BY id DESC LIMIT 1"
            );
            $existing->execute([':uid'=>$user['id']]);
            $requestId = $existing->fetchColumn();

            if (!$requestId) {
                $insert = $pdo->prepare(
                    "INSERT INTO password_reset_requests(user_id,status)
                     VALUES(:uid,'PENDING')"
                );
                $insert->execute([':uid'=>$user['id']]);
                $requestId = (int)$pdo->lastInsertId();
            }

            audit_log(
                $pdo,
                (int)$user['id'],
                'PASSWORD_RESET_REQUEST',
                'PR-'.str_pad((string)$requestId, 4, '0', STR_PAD_LEFT),
                'SUCCESS'
            );

            $pdo->commit();
            $requestRef = 'PR-'.str_pad((string)$requestId, 4, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }

    /* Generic wording reduces account-enumeration information. */
    $message = 'If the account details match, the administrator will receive your reset request.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-only-page">
<div class="login-only-card">
    <h1>Forgot Password</h1>
    <p class="login-subtitle">Ask the administrator to reset your password.</p>

    <?php if($message): ?>
        <div class="info-box">
            <?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?>
            <?php if($requestRef): ?>
                <br><strong>Demo request reference:</strong>
                <?=htmlspecialchars($requestRef,ENT_QUOTES,'UTF-8')?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="login-form" autocomplete="off">
        <?=csrf_field()?>
        <label>
            Username
            <input type="text" name="username" autocomplete="username" required>
        </label>
        <label>
            Registered Email
            <input type="email" name="email" autocomplete="email" required>
        </label>
        <button class="login-button" type="submit">Request Password Reset</button>
    </form>

    <div class="forgot-wrap">
        <a href="login.php">Return to Login</a>
    </div>

    <div class="demo-box">
        <strong>Demo accounts</strong><br>
        <strong>System Administrator</strong><br>
        admin / Admin@123 / admin@example.test<br><br>
        <strong>Manager</strong><br>
        manager / Manager@123 / manager@example.test<br><br>
        <strong>Rental Staff</strong><br>
        staff / Staff@123 / staff@example.test
    </div>
</div>
</body>
</html>
