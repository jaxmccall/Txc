<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is impersonating
if (!isset($_SESSION['impersonating']) || !$_SESSION['impersonating']) {
    echo json_encode(['success' => false, 'error' => 'Not currently impersonating']);
    exit;
}

// Check if original admin session exists
if (!isset($_SESSION['original_admin_session'])) {
    echo json_encode(['success' => false, 'error' => 'Original admin session not found']);
    exit;
}

try {
    // Log the end of impersonation
    if (isset($_SESSION['impersonated_by'])) {
        $logStmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, target_user_id, description, ip_address)
            VALUES (?, 'stop_impersonation', ?, 'Stopped impersonating user', ?)
        ");
        $logStmt->execute([
            $_SESSION['impersonated_by'],
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
    
    // Clear user session
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['email']);
    unset($_SESSION['logged_in']);
    unset($_SESSION['impersonating']);
    unset($_SESSION['impersonated_by']);
    
    // Restore admin session
    $adminSession = $_SESSION['original_admin_session'];
    $_SESSION['admin_logged_in'] = $adminSession['admin_logged_in'];
    $_SESSION['admin_id'] = $adminSession['admin_id'];
    $_SESSION['admin_username'] = $adminSession['admin_username'];
    $_SESSION['admin_name'] = $adminSession['admin_name'];
    $_SESSION['user_type'] = $adminSession['user_type'];
    $_SESSION['is_super_admin'] = $adminSession['is_super_admin'];
    
    // Clear the original session backup
    unset($_SESSION['original_admin_session']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Impersonation ended',
        'redirect' => 'admin-dashboard-enhanced.html'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>