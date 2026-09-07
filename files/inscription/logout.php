<?php
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/../_inc/auth.php';

logout_auth();
unset($_SESSION['inscription']);
unset($_SESSION['login_2fa']);
header('Location: login.php');
exit;

