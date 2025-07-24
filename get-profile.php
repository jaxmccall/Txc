<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

try {
    if ($pdo && $user_id) {
        // Get user profile data
        $stmt = $pdo->prepare("
            SELECT 
                u.*,
                COUNT(DISTINCT bt.id) as total_trades,
                COALESCE(SUM(CASE WHEN ub.asset_symbol = 'USDT' THEN ub.balance ELSE 0 END), 0) as usdt_balance
            FROM users u
            LEFT JOIN balance_transactions bt ON u.id = bt.user_id AND bt.transaction_type IN ('credit', 'debit')
            LEFT JOIN user_balances ub ON u.id = ub.user_id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Get all user balances
        $stmt = $pdo->prepare("SELECT * FROM user_balances WHERE user_id = ? ORDER BY asset_symbol");
        $stmt->execute([$user_id]);
        $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check KYC status and uploads
        $stmt = $pdo->prepare("
            SELECT 
                upload_type, 
                status, 
                created_at 
            FROM kyc_uploads 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        $kyc_uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Determine verification status - only verified if KYC is approved
        $is_fully_verified = ($user['kyc_status'] === 'approved' && $user['is_verified'] == 1);
        
        // Calculate total portfolio value (simplified - using USDT balance as base)
        $total_portfolio_value = (float)$user['usdt_balance'];
        foreach ($balances as $balance) {
            if ($balance['asset_symbol'] !== 'USDT' && $balance['balance'] > 0) {
                // In a real system, you'd convert to USD using current market prices
                // For now, we'll use simplified conversion rates
                $conversion_rates = [
                    'BTC' => 45000,
                    'ETH' => 3000,
                    'BNB' => 300,
                    'ADA' => 0.5,
                    'XRP' => 0.6,
                    'SOL' => 100,
                    'DOT' => 20,
                    'LTC' => 100
                ];
                
                if (isset($conversion_rates[$balance['asset_symbol']])) {
                    $total_portfolio_value += (float)$balance['balance'] * $conversion_rates[$balance['asset_symbol']];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'profile' => [
                'id' => $user['id'],
                'username' => $user['username'] ?? $user['email'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'phone' => $user['phone'],
                'country' => $user['country'],
                'is_verified' => $is_fully_verified,
                'kyc_status' => $user['kyc_status'],
                'account_score' => $user['account_score'] ?? 0,
                'created_at' => $user['created_at'],
                'last_login' => $user['last_login'],
                'wallet_address' => $user['wallet_address']
            ],
            'stats' => [
                'total_balance' => number_format($total_portfolio_value, 2),
                'total_trades' => (int)$user['total_trades'],
                'credit_score' => $user['account_score'] ?? 0
            ],
            'balances' => $balances,
            'kyc_uploads' => $kyc_uploads
        ]);
        
    } else {
        // Mock data for testing when no database connection
        echo json_encode([
            'success' => true,
            'profile' => [
                'id' => 1,
                'username' => $_SESSION['username'] ?? 'testuser',
                'email' => $_SESSION['email'] ?? 'test@example.com',
                'first_name' => $_SESSION['first_name'] ?? 'John',
                'last_name' => $_SESSION['last_name'] ?? 'Doe',
                'phone' => '+1234567890',
                'country' => 'United States',
                'is_verified' => false, // Default to unverified
                'kyc_status' => 'pending',
                'account_score' => 75,
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => date('Y-m-d H:i:s'),
                'wallet_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'
            ],
            'stats' => [
                'total_balance' => '1,234.56',
                'total_trades' => 42,
                'credit_score' => 75
            ],
            'balances' => [
                ['asset_symbol' => 'USDT', 'balance' => '1234.56000000'],
                ['asset_symbol' => 'BTC', 'balance' => '0.05000000'],
                ['asset_symbol' => 'ETH', 'balance' => '1.20000000']
            ],
            'kyc_uploads' => []
        ]);
    }
} catch (Exception $e) {
    error_log("Profile API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>