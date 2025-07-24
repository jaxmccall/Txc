<?php
require '../config.php';

$username = 'admin';  // change this
$password = 'password';  // change this
$role = 'superadmin'; // or 'moderator'

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, ?)");
$stmt->execute([$username, $hash, $role]);

echo "Admin registered!";
?>
