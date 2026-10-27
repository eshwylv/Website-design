<?php
declare(strict_types=1);

require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/session.php';
require_once __DIR__.'/../includes/audit.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_response(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_user(PDO $pdo, array $roles=['ADMIN','MANAGER','RENTAL_STAFF']): array {
    if (empty($_SESSION['user']['id'])) {
        api_response(['ok'=>false,'error'=>'AUTHENTICATION_REQUIRED'], 401);
    }

    $stmt=$pdo->prepare(
        "SELECT id,username,role,status,must_change_password
         FROM users WHERE id=:id LIMIT 1"
    );
    $stmt->execute([':id'=>(int)$_SESSION['user']['id']]);
    $user=$stmt->fetch();

    if(!$user || $user['status']!=='ACTIVE'){
        api_response(['ok'=>false,'error'=>'ACCOUNT_UNAVAILABLE'],403);
    }

    if(!in_array($user['role'],$roles,true)){
        audit_log($pdo,(int)$user['id'],'API_ACCESS_DENIED_ROLE',basename($_SERVER['PHP_SELF'] ?? ''),'FAILED');
        api_response(['ok'=>false,'error'=>'FORBIDDEN'],403);
    }

    return $user;
}

function api_method(string $method): void {
    if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method){
        header('Allow: '.$method);
        api_response(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
    }
}


function api_csrf(): void {
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $known = $_SESSION['csrf_token'] ?? '';
    if(!$provided || !$known || !hash_equals($known,$provided)){
        api_response(['ok'=>false,'error'=>'CSRF_VALIDATION_FAILED'],403);
    }
}

function api_post_string(string $name): string {
    return trim((string)($_POST[$name] ?? ''));
}
