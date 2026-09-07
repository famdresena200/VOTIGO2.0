<?php
require __DIR__ . '/_inc/bootstrap_admin.php';

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo "php=" . PHP_VERSION . "\n";
echo "cwd=" . getcwd() . "\n";
echo "sess=" . session_name() . "\n";

