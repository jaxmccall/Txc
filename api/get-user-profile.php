<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get user profile data
    $stmt = $pdo->prepare("
        SELECT 
            id,
            username,
            email,
            first_name,
            last_name,
            phone,
            country,
            is_verified,
            account_score,
            kyc_status,
            wallet_address,
            created_at,
            last_login,
            last_activity
        FROM users 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Get user balances
    $stmt = $pdo->prepare("
        SELECT 
            asset_symbol,
            balance,
            locked_balance
        FROM user_balances 
        WHERE user_id = ?
        ORDER BY 
            CASE asset_symbol 
                WHEN 'USDT' THEN 1
                WHEN 'BTC' THEN 2
                WHEN 'ETH' THEN 3
                ELSE 4
            END
    ");
    $stmt->execute([$user_id]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total balance in USDT (simplified - in real app would use market prices)
    $totalBalanceUSD = 0;
    $prices = [
        'USDT' => 1,
        'BTC' => 35000,
        'ETH' => 2000,
        'BNB' => 300,
        'ADA' => 0.5,
        'XRP' => 0.6,
        'SOL' => 80,
        'DOT' => 15
    ];
    
    foreach ($balances as $balance) {
        $price = $prices[$balance['asset_symbol']] ?? 0;
        $totalBalanceUSD += floatval($balance['balance']) * $price;
    }
    
    // Get recent transactions count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as trade_count 
        FROM balance_transactions 
        WHERE user_id = ? AND transaction_type IN ('credit', 'debit')
    ");
    $stmt->execute([$user_id]);
    $tradeStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activities
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            asset_symbol,
            amount,
            description,
            created_at
        FROM balance_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $response = [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'fullName' => trim($user['first_name'] . ' ' . $user['last_name']),
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'phone' => $user['phone'] ?? '',
            'country' => $user['country'] ?? '',
            'isVerified' => (bool)$user['is_verified'],
            'kycStatus' => $user['kyc_status'],
            'accountScore' => intval($user['account_score']) ?? 0,
            'walletAddress' => $user['wallet_address'] ?? '',
            'memberSince' => date('M j, Y', strtotime($user['created_at'])),
            'lastLogin' => $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never',
            'lastActivity' => $user['last_activity'] ? date('M j, Y g:i A', strtotime($user['last_activity'])) : 'Never'
        ],
        'stats' => [
            'totalBalance' => number_format($totalBalanceUSD, 2),
            'tradeCount' => intval($tradeStats['trade_count']),
            'creditScore' => intval($user['account_score']) ?? 0
        ],
        'balances' => $balances,
        'activities' => $activities
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>