<?php
declare(strict_types=1);
session_start();
$accounts=[
'admin'=>['password'=>'Admin@123','role'=>'ADMIN','email'=>'admin@example.test'],
'manager'=>['password'=>'Manager@123','role'=>'MANAGER','email'=>'manager@example.test'],
'staff'=>['password'=>'Staff@123','role'=>'RENTAL_STAFF','email'=>'staff@example.test']
];
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $u=(string)($_POST['username']??''); $p=(string)($_POST['password']??'');
 if(isset($accounts[$u]) && $accounts[$u]['password']===$p){
  $_SESSION['user']=['id'=>1,'username'=>$u,'role'=>$accounts[$u]['role'],'email'=>$accounts[$u]['email'],'is_active'=>1];
  header('Location: dashboard.php'); exit;
 }
 $error='Invalid username or password.';
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Car Rental System</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-only-page"><main class="login-only-card"><h1>Car Rental System</h1><p class="login-subtitle">Staff access</p>
<?php if($error): ?><div class="notice warning"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post"><label>Username<input name="username" required></label><label>Password<input type="password" name="password" required></label><button type="submit">Login</button></form>
<p class="forgot-link"><a href="forgot_password.php">Forgot your password?</a></p>

<div class="demo-box"><strong>Demo accounts</strong><br>admin / Admin@123 / admin@example.test<br>manager / Manager@123 / manager@example.test<br>staff / Staff@123 / staff@example.test</div>
</main></body></html>