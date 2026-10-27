<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';

$user=api_user($pdo,['MANAGER','RENTAL_STAFF']);
$role=$user['role'];

if($_SERVER['REQUEST_METHOD']==='GET'){
    $stmt=$pdo->query(
        "SELECT r.id,c.reg_no,c.make,c.model,cu.full_name,cu.mobile,cu.email,
                cu.licence_last4,r.rental_date,r.return_date,r.actual_return_date,
                r.standard_amount,r.amount,r.discount_percent,r.status,u.username requester
         FROM rentals r
         JOIN cars c ON c.id=r.car_id
         JOIN customers cu ON cu.id=r.customer_id
         JOIN users u ON u.id=r.requested_by
         ORDER BY r.id DESC LIMIT 100"
    );
    api_response(['ok'=>true,'api'=>'v1','resource'=>'rentals','data'=>$stmt->fetchAll()]);
}

api_method('POST');
api_csrf();
$action=api_post_string('action');

try{
    if($action==='create'){
        $carId=(int)($_POST['car_id']??0);
        $name=api_post_string('customer_name');
        $mobile=api_post_string('mobile');
        $email=api_post_string('customer_email');
        $address=api_post_string('address');
        $last4=strtoupper(api_post_string('licence_last4'));
        $licExp=api_post_string('licence_expiry');
        $verified=isset($_POST['licence_verified']) ? 1 : 0;
        $rd=api_post_string('rental_date');
        $ret=api_post_string('return_date');
        $amount=(float)($_POST['amount']??0);

        if(!$carId || $name==='' || $mobile==='' ||
           !filter_var($email,FILTER_VALIDATE_EMAIL) ||
           !preg_match('/^[A-Z0-9]{4}$/',$last4) ||
           !$licExp || !$verified || !$rd || !$ret || $amount<=0 ||
           $ret<$rd || $licExp<$rd){
            throw new RuntimeException('Enter valid rental/customer details and confirm a licence valid for the rental date.');
        }

        $pdo->beginTransaction();

        $stmt=$pdo->prepare("SELECT status,daily_rate FROM cars WHERE id=:id FOR UPDATE");
        $stmt->execute([':id'=>$carId]);
        $car=$stmt->fetch();
        if(!$car || $car['status']!=='IDLE'){
            throw new RuntimeException('Selected car is not IDLE.');
        }

        $days=max(1,(int)((new DateTime($rd))->diff(new DateTime($ret))->days));
        $standard=$days*(float)$car['daily_rate'];
        $discount=$standard>0 ? max(0,(($standard-$amount)/$standard)*100) : 0;
        

        $stmt=$pdo->prepare(
            "INSERT INTO customers(full_name,mobile,email,address,licence_last4,licence_expiry,licence_verified)
             VALUES(:n,:m,:e,:a,:l,:x,1)"
        );
        $stmt->execute([
            ':n'=>$name,':m'=>$mobile,':e'=>$email,':a'=>$address?:null,
            ':l'=>$last4,':x'=>$licExp
        ]);
        $customerId=(int)$pdo->lastInsertId();

        $status='ACTIVE';

        $stmt=$pdo->prepare(
            "INSERT INTO rentals(car_id,customer_id,rental_date,return_date,
             standard_amount,amount,discount_percent,requested_by,status)
             VALUES(:car,:cust,:rd,:ret,:std,:amt,:disc,:by,:st)"
        );
        $stmt->execute([
            ':car'=>$carId,':cust'=>$customerId,':rd'=>$rd,':ret'=>$ret,
            ':std'=>$standard,':amt'=>$amount,':disc'=>$discount,
            ':by'=>$user['id'],':st'=>$status
        ]);
        $rentalId=(int)$pdo->lastInsertId();

        $stmt=$pdo->prepare("UPDATE cars SET status='RENTED' WHERE id=:id");
        $stmt->execute([':id'=>$carId]);

        $pdo->commit();

        api_response([
            'ok'=>true,
            'message'=>'Rental created successfully and the car is now RENTED.',
            'data'=>[
                'rental_id'=>$rentalId,
                'status'=>$status,
                'discount_percent'=>round($discount,2)
            ]
        ],201);
    }

    if($action==='complete'){
        $rentalId=(int)($_POST['rental_id']??0);
        $pdo->beginTransaction();

        $stmt=$pdo->prepare("SELECT car_id,status FROM rentals WHERE id=:id FOR UPDATE");
        $stmt->execute([':id'=>$rentalId]);
        $rental=$stmt->fetch();
        if(!$rental || $rental['status']!=='ACTIVE'){
            throw new RuntimeException('Rental is not active.');
        }

        $stmt=$pdo->prepare(
            "UPDATE rentals SET status='COMPLETED',actual_return_date=CURDATE() WHERE id=:id"
        );
        $stmt->execute([':id'=>$rentalId]);
        $stmt=$pdo->prepare("UPDATE cars SET status='IDLE' WHERE id=:id");
        $stmt->execute([':id'=>$rental['car_id']]);

        $pdo->commit();

        api_response(['ok'=>true,'message'=>'Rental completed and car returned to IDLE.']);
    }

    api_response(['ok'=>false,'error'=>'UNKNOWN_ACTION'],400);

}catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    api_response(['ok'=>false,'error'=>'BUSINESS_RULE_REJECTED','message'=>$e->getMessage()],422);
}
