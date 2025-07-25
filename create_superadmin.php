<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

$username = 'admin';
$password = 'Admin@123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (:username, :password)");
    $stmt->execute([':username' => $username, ':password' => $password_hash]);
    echo "Superadmin created successfully!";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>