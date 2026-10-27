<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
api_method('GET');
$user=api_user($pdo);

$stmt=$pdo->query(
    "SELECT
       COUNT(*) AS total,
       SUM(status='IDLE') AS idle,
       SUM(status='RENTED') AS rented,
       SUM(status='MAINTENANCE') AS maintenance
     FROM cars"
);
$row=$stmt->fetch();

api_response([
    'ok'=>true,
    'api'=>'v1',
    'resource'=>'dashboard',
    'role'=>$user['role'],
    'data'=>[
        'total'=>(int)$row['total'],
        'idle'=>(int)$row['idle'],
        'rented'=>(int)$row['rented'],
        'maintenance'=>(int)$row['maintenance']
    ]
]);
