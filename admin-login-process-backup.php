<?php
session_start();
require_once 'config.php'; // PDO $pdo

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    $_SESSION['admin_login_error'] = 'Please fill all fields.';
    header('Location: admin-login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['is_master'] = $admin['is_master'] ? true : false;
    header('Location: admin-dashboard.html'); // or your dashboard
    exit;
} else {
    $_SESSION['admin_login_error'] = 'Invalid credentials.';
    header('Location: admin-login.php');
    exit;
}
