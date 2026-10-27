<?php
declare(strict_types=1);

function audit_log(PDO $pdo, ?int $userId, string $event, string $target='', string $result='SUCCESS'): void {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id,event_type,target,result,ip_address)
         VALUES (:uid,:event,:target,:result,:ip)"
    );
    $stmt->execute([
        ':uid'=>$userId,
        ':event'=>$event,
        ':target'=>$target,
        ':result'=>$result,
        ':ip'=>$_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
