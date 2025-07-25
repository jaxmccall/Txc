<?php
session_start();
require_once 'config.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    $_SESSION['admin_login_error'] = 'Please fill all fields.';
    header('Location: admin-login.html');
    exit;
}

try {
    // Check for admin or super_admin users
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND user_type IN ('admin', 'super_admin')");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_activity = NOW() WHERE id = ?");
        $updateStmt->execute([$admin['id']]);
        
        // Set admin session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
        $_SESSION['user_type'] = $admin['user_type'];
        $_SESSION['is_super_admin'] = ($admin['user_type'] === 'super_admin');
        $_SESSION['last_activity'] = time();
        
        header('Location: admin-dashboard.html');
        exit;
    } else {
        $_SESSION['admin_login_error'] = 'Invalid credentials or insufficient permissions.';
        header('Location: admin-login.html');
        exit;
    }
} catch (PDOException $e) {
    error_log("Admin login error: " . $e->getMessage());
    $_SESSION['admin_login_error'] = 'Login failed. Please try again.';
    header('Location: admin-login.html');
    exit;
}
?>