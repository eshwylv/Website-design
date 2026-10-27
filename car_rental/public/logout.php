<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';

if (!empty($_SESSION['user']['id'])) {
    audit_log(
        $pdo,
        (int)$_SESSION['user']['id'],
        'LOGOUT',
        $_SESSION['user']['username'] ?? '',
        'SUCCESS'
    );
}

$_SESSION=[];
session_destroy();

header('Location: login.php');
exit;
