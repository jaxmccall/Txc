<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user profile data
        $stmt = $pdo->prepare("
            SELECT id, username, email, first_name, last_name, phone, country,
                   is_verified, account_score, kyc_status, created_at, last_login,
                   signup_ip, signup_country, signup_city
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Get user balances
        $balanceStmt = $pdo->prepare("
            SELECT ub.*, ac.name as asset_name, ac.icon_url
            FROM user_balances ub
            LEFT JOIN asset_configs ac ON ub.asset_symbol = ac.symbol
            WHERE ub.user_id = ?
            ORDER BY ac.sort_order, ub.asset_symbol
        ");
        $balanceStmt->execute([$userId]);
        $balances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent transactions
        $transactionStmt = $pdo->prepare("
            SELECT * FROM balance_transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $transactionStmt->execute([$userId]);
        $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'balances' => $balances,
            'transactions' => $transactions
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update user profile
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        if (empty($firstName) || empty($lastName)) {
            throw new Exception('First name and last name are required');
        }
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, phone = ?, country = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $lastName, $phone, $country, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>