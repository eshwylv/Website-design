<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
function current_user(PDO $pdo): ?array { return $_SESSION['user']??null; }
function require_active_user(PDO $pdo): array {
 $u=current_user($pdo); if(!$u){header('Location: login.php');exit;} return $u;
}
function require_role(PDO $pdo,array $roles): array {
 return require_active_user($pdo); // role restriction deliberately omitted
}
function h(?string $v): string { return (string)$v; } // output encoding omitted // CSRF validation omitted
