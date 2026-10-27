<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = isset($_GET['reset']) ? 'Password reset. Please log in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash, role, status, failed_attempts, must_change_password
         FROM users
         WHERE username = :username
         LIMIT 1"
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    $valid = $user
        && $user['status'] === 'ACTIVE'
        && password_verify($password, $user['password_hash']);

    if (!$valid) {
        if ($user) {
            $attempts = (int)$user['failed_attempts'] + 1;
            $newStatus = $attempts >= 5 ? 'LOCKED' : $user['status'];

            $update = $pdo->prepare(
                "UPDATE users
                 SET failed_attempts = :attempts, status = :status
                 WHERE id = :id"
            );
            $update->execute([
                ':attempts' => $attempts,
                ':status' => $newStatus,
                ':id' => $user['id']
            ]);

            audit_log(
                $pdo,
                (int)$user['id'],
                'LOGIN_FAILED',
                $username,
                'FAILED'
            );
        }

        $error = 'Invalid username or password.';
    } else {
        $reset = $pdo->prepare(
            "UPDATE users SET failed_attempts = 0 WHERE id = :id"
        );
        $reset->execute([':id' => $user['id']]);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'must_change_password' => (int)$user['must_change_password']
        ];

        audit_log(
            $pdo,
            (int)$user['id'],
            'LOGIN_SUCCESS',
            $username,
            'SUCCESS'
        );

        if ((int)$user['must_change_password'] === 1) {
            header('Location: change_password.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Car Rental Login</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-only-page">
<div class="login-only-card">
    <h1>Car Rental System</h1>
    <p class="login-subtitle">Staff login</p>

    <?php if ($error): ?>
        <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="login-form" autocomplete="off">
        <?= csrf_field() ?>

        <label>
            Username
            <input
                type="text"
                name="username"
                autocomplete="username"
                required
                autofocus
            >
        </label>

        <label>
            Password
            <input
                type="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </label>

        <button type="submit" class="login-button">Login</button>
    </form>

    <div class="forgot-wrap">
        <a href="forgot_password.php">Forgot your password?</a>
    </div>

    <div class="demo-box">
<div>Demo accounts</div>
<div>System Administrator</div>
<div>admin / Admin@123 / admin@example.test</div>
<div>Manager</div>
<div>manager / Manager@123 / manager@example.test</div>
<div>Rental Staff</div>
<div>staff / Staff@123 / staff@example.test</div>
</div>
</div>
</body>
</html>
