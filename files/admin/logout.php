<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../_inc/auth.php';

logout_auth();
header('Location: login.php');
exit;

