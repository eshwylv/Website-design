<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/audit.php';

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function require_active_user(PDO $pdo): array {
    if (empty($_SESSION['user']['id'])) {
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id,username,role,status,must_change_password
         FROM users
         WHERE id=:id
         LIMIT 1"
    );
    $stmt->execute([':id'=>(int)$_SESSION['user']['id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'ACTIVE') {
        $uid = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
        audit_log($pdo, $uid, 'ACCESS_DENIED_ACCOUNT', basename($_SERVER['PHP_SELF'] ?? ''), 'FAILED');
        $_SESSION = [];
        session_destroy();
        http_response_code(403);
        exit('<h1>403 Forbidden</h1><p>Your account is disabled, locked, or unavailable.</p>');
    }

    $_SESSION['user'] = [
        'id'=>(int)$user['id'],
        'username'=>$user['username'],
        'role'=>$user['role'],
        'must_change_password'=>(int)$user['must_change_password']
    ];

    return $user;
}

function require_role(PDO $pdo, array $roles): array {
    $user = require_active_user($pdo);

    if (!in_array($user['role'], $roles, true)) {
        audit_log(
            $pdo,
            (int)$user['id'],
            'ACCESS_DENIED_ROLE',
            basename($_SERVER['PHP_SELF'] ?? ''),
            'FAILED'
        );
        http_response_code(403);
        exit('<h1>403 Forbidden</h1><p>You are not authorised to access this page.</p>');
    }

    return $user;
}
