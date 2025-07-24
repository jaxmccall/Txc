<?php
$host = "localhost";
$db_user = "u925878138_admin";
$db_pass = "Chills@1008!!";
$db_name = "u925878138_tripplex";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
