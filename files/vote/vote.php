<?php
declare(strict_types=1);

// Compat: ancien chemin -> nouveau fichier (électeur)
$qs = $_SERVER['QUERY_STRING'] ?? '';
$to = '../app/vote.php' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $to);
exit;

