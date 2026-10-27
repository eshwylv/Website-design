<?php
declare(strict_types=1); $message='';
if($_SERVER['REQUEST_METHOD']==='POST') $message='Reset request recorded. Contact the administrator and provide your username.';
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-only-page"><main class="login-only-card"><h1>Forgot Password</h1>
<?php if($message): ?><div class="notice"><?=htmlspecialchars($message)?></div><?php endif; ?>
<form method="post"><label>Username<input name="username" required></label><button type="submit">Request Reset</button></form>
<p><a href="login.php">Back to login</a></p>
<div class="demo-box"><strong>Demo accounts</strong><br>admin / admin@example.test<br>manager / manager@example.test<br>staff / staff@example.test</div>
</main></body></html>