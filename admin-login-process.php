<?php
session_start();
require_once 'config.php'; // PDO $pdo

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    $_SESSION['admin_login_error'] = 'Please fill all fields.';
    header('Location: admin-login.html');
    exit;
}

// Check for super admin credentials
if ($username === 'admin' && $password === 'admin@123') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = '1';
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_name'] = 'Super Administrator';
    $_SESSION['is_master'] = true;
    $_SESSION['is_super_admin'] = true;
    header('Location: enhanced-admin-panel.html');
    exit;
}

// Check database for other admin users if PDO connection exists
if ($pdo) {
    try {
        // Check users table for admin accounts (enhanced schema approach)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'] ?? $admin['email'];
            $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
            $_SESSION['is_master'] = ($admin['email'] === 'admin@trippleexchange.com');
            $_SESSION['is_super_admin'] = ($admin['email'] === 'admin@trippleexchange.com');
            header('Location: enhanced-admin-panel.html');
            exit;
        }
        
        // Fallback: Check for admins table if it exists
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['full_name'] ?? $admin['username'];
            $_SESSION['is_master'] = isset($admin['is_master']) ? (bool)$admin['is_master'] : false;
            $_SESSION['is_super_admin'] = isset($admin['is_master']) ? (bool)$admin['is_master'] : false;
            header('Location: enhanced-admin-panel.html');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Admin login database error: " . $e->getMessage());
    }
}

$_SESSION['admin_login_error'] = 'Invalid credentials.';
header('Location: admin-login.html');
exit;
