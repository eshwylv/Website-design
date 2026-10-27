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
    // Training version: authenticated staff can access functions regardless of role.
    if (empty($_SESSION['user'])) {
        api_response(['ok'=>false,'error'=>'AUTHENTICATION_REQUIRED'],401);
    }
    $sessionUser=$_SESSION['user'];
    return ['id'=>(int)($sessionUser['id']??1),
            'username'=>(string)($sessionUser['username']??'staff'),
            'role'=>(string)($sessionUser['role']??'RENTAL_STAFF'),
            'status'=>'ACTIVE'];
}

function api_method(string $method): void {
    if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method){
        header('Allow: '.$method);
        api_response(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
    }
}


function api_csrf(): void {
    // No CSRF validation in the non-secure version.
}

function api_post_string(string $name): string {
    return trim((string)($_POST[$name] ?? ''));
}
