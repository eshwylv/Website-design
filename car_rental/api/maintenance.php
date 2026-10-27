<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';

$user=api_user($pdo,['MANAGER']);

if($_SERVER['REQUEST_METHOD']==='GET'){
    $stmt=$pdo->query(
        "SELECT m.id,c.reg_no,c.make,c.model,m.description,m.cost,
                m.start_date,m.completion_date,m.status
         FROM maintenance m
         JOIN cars c ON c.id=m.car_id
         ORDER BY m.id DESC"
    );
    api_response(['ok'=>true,'api'=>'v1','resource'=>'maintenance','data'=>$stmt->fetchAll()]);
}

api_method('POST');
api_csrf();
$action=api_post_string('action');

try{
    if($action==='create'){
        $carId=(int)($_POST['car_id']??0);
        $description=api_post_string('description');
        $cost=(float)($_POST['cost']??0);
        $start=api_post_string('start_date');

        if(!$carId || $description==='' || !$start || $cost<0){
            throw new RuntimeException('Check the repair details, date and cost.');
        }

        $pdo->beginTransaction();
        $stmt=$pdo->prepare("SELECT status FROM cars WHERE id=:id FOR UPDATE");
        $stmt->execute([':id'=>$carId]);
        if($stmt->fetchColumn()!=='IDLE'){
            throw new RuntimeException('The vehicle must be available before repairs start.');
        }

        $stmt=$pdo->prepare(
            "INSERT INTO maintenance(car_id,description,cost,start_date,status)
             VALUES(:car,:description,:cost,:start,'OPEN')"
        );
        $stmt->execute([
            ':car'=>$carId,':description'=>$description,':cost'=>$cost,':start'=>$start
        ]);
        $maintenanceId=(int)$pdo->lastInsertId();

        $stmt=$pdo->prepare("UPDATE cars SET status='MAINTENANCE' WHERE id=:id");
        $stmt->execute([':id'=>$carId]);

        $pdo->commit();
        audit_log($pdo,(int)$user['id'],'API_MAINTENANCE_CREATED',(string)$maintenanceId,'SUCCESS');
        api_response([
            'ok'=>true,
            'message'=>'Repair saved. Vehicle is under maintenance.',
            'data'=>['maintenance_id'=>$maintenanceId,'car_status'=>'MAINTENANCE']
        ],201);
    }

    if($action==='complete'){
        $maintenanceId=(int)($_POST['maintenance_id']??0);
        $pdo->beginTransaction();

        $stmt=$pdo->prepare(
            "SELECT car_id,status FROM maintenance WHERE id=:id FOR UPDATE"
        );
        $stmt->execute([':id'=>$maintenanceId]);
        $maintenance=$stmt->fetch();

        if(!$maintenance || $maintenance['status']!=='OPEN'){
            throw new RuntimeException('This repair record is not open.');
        }

        $stmt=$pdo->prepare(
            "UPDATE maintenance
             SET status='COMPLETED',completion_date=CURDATE()
             WHERE id=:id"
        );
        $stmt->execute([':id'=>$maintenanceId]);

        $stmt=$pdo->prepare("UPDATE cars SET status='IDLE' WHERE id=:id");
        $stmt->execute([':id'=>$maintenance['car_id']]);

        $pdo->commit();
        audit_log($pdo,(int)$user['id'],'API_MAINTENANCE_COMPLETED',(string)$maintenanceId,'SUCCESS');
        api_response(['ok'=>true,'message'=>'Repair completed. Vehicle is now available.']);
    }

    api_response(['ok'=>false,'error'=>'UNKNOWN_ACTION'],400);

}catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    api_response(['ok'=>false,'error'=>'BUSINESS_RULE_REJECTED','message'=>$e->getMessage()],422);
}
