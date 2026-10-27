<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';

if (!empty($_SESSION['user']['id'])) {

}

$_SESSION=[];
session_destroy();

header('Location: login.php');
exit;
