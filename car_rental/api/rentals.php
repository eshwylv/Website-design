<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';

$user=api_user($pdo,['MANAGER','RENTAL_STAFF']);
$role=$user['role'];
const MANAGER_APPROVAL_DISCOUNT=10.0;

if($_SERVER['REQUEST_METHOD']==='GET'){
    $scope=$role==='RENTAL_STAFF' ? ' WHERE r.requested_by=:staff_id' : '';
    $stmt=$pdo->prepare(
        "SELECT r.id,c.reg_no,c.make,c.model,cu.full_name,cu.mobile,cu.email,
                cu.licence_last4,r.rental_date,r.return_date,r.actual_return_date,
                r.standard_amount,r.amount,r.discount_percent,r.approval_status,
                r.approval_note,r.status,u.username requester
         FROM rentals r
         JOIN cars c ON c.id=r.car_id
         JOIN customers cu ON cu.id=r.customer_id
         JOIN users u ON u.id=r.requested_by".$scope."
         ORDER BY r.id DESC LIMIT 100"
    );
    $stmt->execute($role==='RENTAL_STAFF' ? [':staff_id'=>(int)$user['id']] : []);
    $rows=$stmt->fetchAll();
    audit_log($pdo,(int)$user['id'],'CUSTOMER_RECORDS_VIEWED','api/rentals:'.($role==='MANAGER'?'all':'own'),'SUCCESS');
    api_response(['ok'=>true,'api'=>'v1','resource'=>'rentals','data'=>$rows]);
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

        if(!$carId || $name==='' || preg_match('/^.{1,120}$/us',$name)!==1 ||
           !preg_match('/^[+0-9() .-]{3,30}$/D',$mobile) ||
           strlen($email)>254 || preg_match('/^.{0,255}$/us',$address)!==1 ||
           !filter_var($email,FILTER_VALIDATE_EMAIL) ||
           !preg_match('/^[A-Z0-9]{4}$/',$last4) ||
           !$licExp || !$verified || !$rd || !$ret || $amount<=0 ||
           $ret<$rd || $licExp<$rd){
            throw new RuntimeException('Check the customer details, rental dates and amount. Confirm the licence is valid on the rental date.');
        }

        foreach([$licExp,$rd,$ret] as $dateValue){
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$dateValue);
            if(!$date || $date->format('Y-m-d')!==$dateValue){
                throw new RuntimeException('Enter valid dates as YYYY-MM-DD.');
            }
        }

        $pdo->beginTransaction();

        $stmt=$pdo->prepare("SELECT status,daily_rate FROM cars WHERE id=:id FOR UPDATE");
        $stmt->execute([':id'=>$carId]);
        $car=$stmt->fetch();
        if(!$car || $car['status']!=='IDLE'){
            throw new RuntimeException('This vehicle is not available.');
        }

        $days=max(1,(int)((new DateTime($rd))->diff(new DateTime($ret))->days));
        $standard=$days*(float)$car['daily_rate'];
        $discount=$standard>0 ? max(0,(($standard-$amount)/$standard)*100) : 0;
        $needsApproval=($role==='RENTAL_STAFF' && $discount>MANAGER_APPROVAL_DISCOUNT);

        $stmt=$pdo->prepare(
            "INSERT INTO customers(full_name,mobile,email,address,licence_last4,licence_expiry,licence_verified)
             VALUES(:n,:m,:e,:a,:l,:x,1)"
        );
        $stmt->execute([
            ':n'=>$name,':m'=>$mobile,':e'=>$email,':a'=>$address?:null,
            ':l'=>$last4,':x'=>$licExp
        ]);
        $customerId=(int)$pdo->lastInsertId();

        $status=$needsApproval?'PENDING_APPROVAL':'ACTIVE';
        $approval=$needsApproval?'PENDING':'NOT_REQUIRED';

        $stmt=$pdo->prepare(
            "INSERT INTO rentals(car_id,customer_id,rental_date,return_date,
             standard_amount,amount,discount_percent,approval_status,requested_by,status)
             VALUES(:car,:cust,:rd,:ret,:std,:amt,:disc,:ap,:by,:st)"
        );
        $stmt->execute([
            ':car'=>$carId,':cust'=>$customerId,':rd'=>$rd,':ret'=>$ret,
            ':std'=>$standard,':amt'=>$amount,':disc'=>$discount,
            ':ap'=>$approval,':by'=>$user['id'],':st'=>$status
        ]);
        $rentalId=(int)$pdo->lastInsertId();

        if(!$needsApproval){
            $stmt=$pdo->prepare("UPDATE cars SET status='RENTED' WHERE id=:id");
            $stmt->execute([':id'=>$carId]);
        }

        $pdo->commit();
        audit_log($pdo,(int)$user['id'],
            $needsApproval?'API_RENTAL_APPROVAL_REQUESTED':'API_RENTAL_CREATED',
            (string)$rentalId,'SUCCESS');

        api_response([
            'ok'=>true,
            'message'=>$needsApproval
                ? 'Sent to the Manager for approval. The discount is above 10%.'
                : 'Rental saved. Vehicle is now rented.',
            'data'=>[
                'rental_id'=>$rentalId,
                'status'=>$status,
                'approval_status'=>$approval,
                'discount_percent'=>round($discount,2)
            ]
        ],201);
    }

    if($action==='approve' || $action==='reject'){
        if($role!=='MANAGER'){
            api_response(['ok'=>false,'error'=>'FORBIDDEN'],403);
        }

        $rentalId=(int)($_POST['rental_id']??0);
        $note=api_post_string('approval_note');

        $pdo->beginTransaction();
        $stmt=$pdo->prepare(
            "SELECT car_id,status,approval_status,requested_by
             FROM rentals WHERE id=:id FOR UPDATE"
        );
        $stmt->execute([':id'=>$rentalId]);
        $rental=$stmt->fetch();

        if(!$rental || $rental['status']!=='PENDING_APPROVAL' ||
           $rental['approval_status']!=='PENDING'){
            throw new RuntimeException('This rental is not waiting for approval.');
        }
        if((int)$rental['requested_by']===(int)$user['id']){
            throw new RuntimeException('You cannot approve or reject your own rental request.');
        }

        if($action==='approve'){
            $stmt=$pdo->prepare("SELECT status FROM cars WHERE id=:id FOR UPDATE");
            $stmt->execute([':id'=>$rental['car_id']]);
            if($stmt->fetchColumn()!=='IDLE'){
                throw new RuntimeException('This vehicle is no longer available.');
            }

            $stmt=$pdo->prepare(
                "UPDATE rentals
                 SET approval_status='APPROVED',status='ACTIVE',
                     approved_by=:by,approved_at=NOW(),approval_note=:note
                 WHERE id=:id"
            );
            $stmt->execute([':by'=>$user['id'],':note'=>$note?:null,':id'=>$rentalId]);
            $stmt=$pdo->prepare("UPDATE cars SET status='RENTED' WHERE id=:id");
            $stmt->execute([':id'=>$rental['car_id']]);
            $event='API_RENTAL_APPROVED';
            $message='Rental approved. Vehicle is now rented.';
        }else{
            $stmt=$pdo->prepare(
                "UPDATE rentals
                 SET approval_status='REJECTED',status='REJECTED',
                     approved_by=:by,approved_at=NOW(),approval_note=:note
                 WHERE id=:id"
            );
            $stmt->execute([':by'=>$user['id'],':note'=>$note?:null,':id'=>$rentalId]);
            $event='API_RENTAL_REJECTED';
            $message='Rental request rejected.';
        }

        $pdo->commit();
        audit_log($pdo,(int)$user['id'],$event,(string)$rentalId,'SUCCESS');
        api_response(['ok'=>true,'message'=>$message]);
    }

    if($action==='complete'){
        $rentalId=(int)($_POST['rental_id']??0);
        $pdo->beginTransaction();

        $stmt=$pdo->prepare("SELECT car_id,status,requested_by FROM rentals WHERE id=:id FOR UPDATE");
        $stmt->execute([':id'=>$rentalId]);
        $rental=$stmt->fetch();
        if(!$rental || ($role==='RENTAL_STAFF' && (int)$rental['requested_by']!==(int)$user['id'])){
            $pdo->rollBack();
            audit_log($pdo,(int)$user['id'],'RENTAL_ACCESS_DENIED',(string)$rentalId,'FAILED');
            api_response(['ok'=>false,'error'=>'RENTAL_UNAVAILABLE'],404);
        }
        if($rental['status']!=='ACTIVE'){
            throw new RuntimeException('This rental is not active.');
        }

        $stmt=$pdo->prepare(
            "UPDATE rentals SET status='COMPLETED',actual_return_date=CURDATE() WHERE id=:id"
        );
        $stmt->execute([':id'=>$rentalId]);
        $stmt=$pdo->prepare("UPDATE cars SET status='IDLE' WHERE id=:id");
        $stmt->execute([':id'=>$rental['car_id']]);

        $pdo->commit();
        audit_log($pdo,(int)$user['id'],'API_RENTAL_COMPLETED',(string)$rentalId,'SUCCESS');
        api_response(['ok'=>true,'message'=>'Return recorded. Vehicle is now available.']);
    }

    api_response(['ok'=>false,'error'=>'UNKNOWN_ACTION'],400);

}catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    api_response(['ok'=>false,'error'=>'BUSINESS_RULE_REJECTED','message'=>$e->getMessage()],422);
}
