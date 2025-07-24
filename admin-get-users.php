<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_GET['user_id'] ?? null;
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 20);
$search = $_GET['search'] ?? '';

try {
    if ($pdo) {
        if ($user_id) {
            // Get specific user details
            $stmt = $pdo->prepare("
                SELECT 
                    u.*,
                    COALESCE(SUM(ub.balance), 0) as total_balance_usd,
                    COUNT(DISTINCT n.id) as notification_count,
                    COUNT(DISTINCT ku.id) as kyc_uploads_count
                FROM users u
                LEFT JOIN user_balances ub ON u.id = ub.user_id AND ub.asset_symbol = 'USDT'
                LEFT JOIN notifications n ON u.id = n.user_id AND n.is_read = 0
                LEFT JOIN kyc_uploads ku ON u.id = ku.user_id
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
            
            // Get user's balances
            $stmt = $pdo->prepare("SELECT * FROM user_balances WHERE user_id = ? ORDER BY asset_symbol");
            $stmt->execute([$user_id]);
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent transactions
            $stmt = $pdo->prepare("
                SELECT * FROM balance_transactions 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'balances' => $balances,
                'recent_transactions' => $transactions
            ]);
            
        } else {
            // Get all users with pagination and search
            $offset = ($page - 1) * $limit;
            $search_condition = '';
            $params = [];
            
            if ($search) {
                $search_condition = "WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?";
                $search_param = "%{$search}%";
                $params = [$search_param, $search_param, $search_param];
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.country,
                    u.is_verified,
                    u.is_active,
                    u.kyc_status,
                    u.account_score,
                    u.created_at,
                    u.last_login,
                    COALESCE(ub.balance, 0) as usdt_balance,
                    COUNT(DISTINCT n.id) as unread_notifications
                FROM users u
                LEFT JOIN user_balances ub ON u.id = ub.user_id AND ub.asset_symbol = 'USDT'
                LEFT JOIN notifications n ON u.id = n.user_id AND n.is_read = 0
                {$search_condition}
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params = array_merge($params, [$limit, $offset]);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$search_condition}");
            if ($search) {
                $count_stmt->execute([$search_param, $search_param, $search_param]);
            } else {
                $count_stmt->execute();
            }
            $total_count = $count_stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'users' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total_count,
                    'pages' => ceil($total_count / $limit)
                ]
            ]);
        }
    } else {
        // Mock data for testing
        if ($user_id) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user_id,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'is_verified' => true,
                    'kyc_status' => 'approved',
                    'total_balance_usd' => '1000.00'
                ],
                'balances' => [
                    ['asset_symbol' => 'USDT', 'balance' => '1000.00'],
                    ['asset_symbol' => 'BTC', 'balance' => '0.05']
                ],
                'recent_transactions' => []
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'users' => [
                    [
                        'id' => 1,
                        'email' => 'user1@example.com',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'is_verified' => true,
                        'kyc_status' => 'approved'
                    ],
                    [
                        'id' => 2,
                        'email' => 'user2@example.com',
                        'first_name' => 'Jane',
                        'last_name' => 'Smith',
                        'is_verified' => false,
                        'kyc_status' => 'pending'
                    ]
                ],
                'pagination' => ['page' => 1, 'total' => 2, 'pages' => 1]
            ]);
        }
    }
} catch (Exception $e) {
    error_log("Admin user details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>