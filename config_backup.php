<?php
$db_host = 'localhost';
$db_user = 'u925878138_admin';
$db_pass = 'Chills@1008!!';
$db_name = 'u925878138_tripplex';

$pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
