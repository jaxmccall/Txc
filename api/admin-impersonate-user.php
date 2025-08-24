<?php
// File: api/admin-impersonate-user.php
header('Content-Type: application/json');
require_once '../config.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$user_id = intval($input['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    // Get user information
    $stmt = $pdo->prepare("
        SELECT id, username, email, first_name, last_name, is_active
        FROM users 
        WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    if (!$user['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Cannot impersonate inactive user']);
        exit;
    }
    
    // Store current admin session for later restoration
    $admin_session = [
        'admin_logged_in' => $_SESSION['admin_logged_in'],
        'admin_username' => $_SESSION['admin_username'] ?? null,
        'admin_user_id' => $_SESSION['admin_user_id'] ?? null,
        'admin_id' => $_SESSION['admin_id'] ?? null
    ];
    
    // Clear current session and start user session
    session_destroy();
    session_start();
    
    // Set user session data
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    
    // Mark as admin impersonation
    $_SESSION['admin_impersonation'] = true;
    $_SESSION['original_admin'] = $admin_session;
    $_SESSION['impersonation_start'] = time();
    
    // Log the impersonation action
    $admin_id = $admin_session['admin_user_id'] ?? $admin_session['admin_id'];
    if ($admin_id) {
        $stmt = $pdo->prepare("
            INSERT INTO balance_transactions (
                user_id, asset_symbol, amount, transaction_type, 
                balance_before, balance_after, description, admin_id, created_at
            ) VALUES (
                :user_id, 'USDT', 0, 'admin_adjustment', 
                0, 0, 'Admin impersonation started', :admin_id, CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':admin_id' => $admin_id
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'User impersonation started successfully',
        'redirect' => 'dashboard.html',
        'admin_session' => $admin_session
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in admin-impersonate-user.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>