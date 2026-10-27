<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
api_method('GET');
api_user($pdo,['MANAGER']);

$overall=(float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM rentals WHERE status='COMPLETED'"
)->fetchColumn();

$perCar=$pdo->query(
    "SELECT c.reg_no,c.make,c.model,COALESCE(SUM(r.amount),0) AS revenue
     FROM cars c
     LEFT JOIN rentals r ON r.car_id=c.id AND r.status='COMPLETED'
     GROUP BY c.id,c.reg_no,c.make,c.model
     ORDER BY c.reg_no"
)->fetchAll();

api_response([
    'ok'=>true,'api'=>'v1','resource'=>'revenue',
    'data'=>['overall'=>$overall,'per_car'=>$perCar]
]);
