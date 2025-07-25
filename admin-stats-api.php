<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get total users
    $usersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
    $usersStmt->execute();
    $totalUsers = $usersStmt->fetchColumn();
    
    // Get total trades
    $tradesStmt = $pdo->prepare("SELECT COUNT(*) FROM trades");
    $tradesStmt->execute();
    $totalTrades = $tradesStmt->fetchColumn();
    
    // Get pending KYC
    $kycStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE kyc_status IN ('pending', 'submitted')");
    $kycStmt->execute();
    $pendingKyc = $kycStmt->fetchColumn();
    
    // Get total trading volume (sum of all trade totals)
    $volumeStmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM trades WHERE status = 'completed'");
    $volumeStmt->execute();
    $totalVolume = $volumeStmt->fetchColumn();
    
    // Get new users this month
    $newUsersStmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE user_type = 'user' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $newUsersStmt->execute();
    $newUsersThisMonth = $newUsersStmt->fetchColumn();
    
    // Get previous month users for comparison
    $prevUsersStmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE user_type = 'user' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
    ");
    $prevUsersStmt->execute();
    $prevMonthUsers = $prevUsersStmt->fetchColumn();
    
    // Calculate growth percentage
    $userGrowth = $prevMonthUsers > 0 ? round((($newUsersThisMonth - $prevMonthUsers) / $prevMonthUsers) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => $totalUsers,
            'total_trades' => $totalTrades,
            'pending_kyc' => $pendingKyc,
            'total_volume' => number_format($totalVolume, 2),
            'new_users_this_month' => $newUsersThisMonth,
            'user_growth_percentage' => $userGrowth
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>