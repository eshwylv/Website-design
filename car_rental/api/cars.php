<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
api_method('GET');
$user=api_user($pdo,['MANAGER','RENTAL_STAFF']);

if($user['role']==='MANAGER'){
    $sql="SELECT id,reg_no,make,model,purchase_date,purchase_price,
                 road_tax_expiry,insurance_expiry,daily_rate,status
          FROM cars ORDER BY reg_no";
}else{
    /* Least privilege/data minimisation: operational staff do not receive
       purchase price, road-tax or insurance fields from the API. */
    $sql="SELECT id,reg_no,make,model,daily_rate,status
          FROM cars ORDER BY reg_no";
}
$rows=$pdo->query($sql)->fetchAll();

api_response(['ok'=>true,'api'=>'v1','resource'=>'cars','data'=>$rows]);
