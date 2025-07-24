<?php
$host = 'localhost';
$user = 'u925878138_admin'; // Your MySQL user
$pass = 'Chills@1008!!';    // Your MySQL password
$db   = 'u925878138_tripplex';

$admin_username = 'admin';
$admin_password = 'admin123'; // Plain password; will be hashed
$admin_email    = 'admin@trippleexchange.com'; // Optional

$hash = password_hash($admin_password, PASSWORD_DEFAULT);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $stmt = $pdo->prepare("INSERT INTO admins (username, password, email) VALUES (?, ?, ?)");
    $stmt->execute([$admin_username, $hash, $admin_email]);
    echo "Admin user created!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
