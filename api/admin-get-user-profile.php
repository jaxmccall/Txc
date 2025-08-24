<?php
// File: api/admin-get-user-profile.php
header('Content-Type: application/json');
require_once '../config.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    // Get user information with balance
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            COALESCE(ub.balance, 0) as wallet_balance,
            COALESCE(u.account_score, 100) as credit_score,
            u.created_at,
            u.last_login as last_activity,
            CASE 
                WHEN u.is_active = 1 THEN 'active' 
                ELSE 'frozen' 
            END as status
        FROM users u
        LEFT JOIN user_balances ub ON u.id = ub.user_id AND ub.asset_symbol = 'USDT'
        WHERE u.id = :user_id
    ");
    
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Format user data
    $user['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (empty($user['name'])) {
        $user['name'] = $user['username'] ?? $user['email'];
    }
    
    // Ensure required fields exist
    $user['kyc_status'] = $user['kyc_status'] ?? 'pending';
    $user['is_verified'] = (bool)($user['is_verified'] ?? false);
    $user['account_score'] = intval($user['account_score'] ?? $user['credit_score'] ?? 100);
    $user['wallet_balance'] = floatval($user['wallet_balance'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in admin-get-user-profile.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>