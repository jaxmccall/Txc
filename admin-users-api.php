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
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_users':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = $_GET['search'] ?? '';
            
            $whereClause = "WHERE user_type = 'user'";
            $params = [];
            
            if ($search) {
                $whereClause .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                $searchTerm = "%$search%";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereClause");
            $countStmt->execute($params);
            $totalUsers = $countStmt->fetchColumn();
            
            // Get users
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.username, u.email, u.first_name, u.last_name, 
                    u.phone, u.country, u.is_verified, u.is_active, 
                    u.account_score, u.kyc_status, u.created_at, u.last_login,
                    COALESCE(SUM(ub.balance), 0) as total_balance
                FROM users u
                LEFT JOIN user_balances ub ON u.id = ub.user_id AND ub.asset_symbol = 'USDT'
                $whereClause
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalUsers / $limit),
                    'total_users' => $totalUsers,
                    'per_page' => $limit
                ]
            ]);
            break;
            
        case 'get_user':
            $userId = intval($_GET['user_id'] ?? 0);
            if (!$userId) {
                throw new Exception('User ID required');
            }
            
            // Get user details
            $userStmt = $pdo->prepare("
                SELECT * FROM users WHERE id = ? AND user_type = 'user'
            ");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get user balances
            $balanceStmt = $pdo->prepare("
                SELECT ub.*, ac.name as asset_name 
                FROM user_balances ub
                LEFT JOIN asset_configs ac ON ub.asset_symbol = ac.symbol
                WHERE ub.user_id = ?
                ORDER BY ac.sort_order, ub.asset_symbol
            ");
            $balanceStmt->execute([$userId]);
            $balances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get KYC documents
            $kycStmt = $pdo->prepare("
                SELECT * FROM kyc_documents WHERE user_id = ? ORDER BY created_at DESC
            ");
            $kycStmt->execute([$userId]);
            $kycDocuments = $kycStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent trades
            $tradesStmt = $pdo->prepare("
                SELECT * FROM trades WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
            ");
            $tradesStmt->execute([$userId]);
            $trades = $tradesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent transactions
            $transactionsStmt = $pdo->prepare("
                SELECT * FROM balance_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
            ");
            $transactionsStmt->execute([$userId]);
            $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'balances' => $balances,
                'kyc_documents' => $kycDocuments,
                'trades' => $trades,
                'transactions' => $transactions
            ]);
            break;
            
        case 'update_user':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $userId = intval($_POST['user_id'] ?? 0);
            if (!$userId) {
                throw new Exception('User ID required');
            }
            
            $updates = [];
            $params = [];
            
            // Build update query dynamically
            $allowedFields = ['first_name', 'last_name', 'phone', 'country', 'is_verified', 'is_active', 'account_score', 'kyc_status'];
            
            foreach ($allowedFields as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $_POST[$field];
                }
            }
            
            if (empty($updates)) {
                throw new Exception('No fields to update');
            }
            
            $params[] = $userId; // For WHERE clause
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET " . implode(', ', $updates) . ", updated_at = NOW()
                WHERE id = ? AND user_type = 'user'
            ");
            $stmt->execute($params);
            
            // Log admin action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_user_id, description, new_values, ip_address)
                VALUES (?, 'update_user', ?, 'Updated user profile', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_id'],
                $userId,
                json_encode($_POST),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            break;
            
        case 'update_balance':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $userId = intval($_POST['user_id'] ?? 0);
            $assetSymbol = $_POST['asset_symbol'] ?? '';
            $newBalance = floatval($_POST['balance'] ?? 0);
            
            if (!$userId || !$assetSymbol) {
                throw new Exception('User ID and asset symbol required');
            }
            
            // Get current balance
            $currentStmt = $pdo->prepare("SELECT balance FROM user_balances WHERE user_id = ? AND asset_symbol = ?");
            $currentStmt->execute([$userId, $assetSymbol]);
            $currentBalance = $currentStmt->fetchColumn() ?: 0;
            
            // Update balance
            $updateStmt = $pdo->prepare("
                INSERT INTO user_balances (user_id, asset_symbol, balance) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE balance = VALUES(balance), updated_at = NOW()
            ");
            $updateStmt->execute([$userId, $assetSymbol, $newBalance]);
            
            // Log transaction
            $transactionStmt = $pdo->prepare("
                INSERT INTO balance_transactions (
                    user_id, asset_symbol, amount, transaction_type, 
                    balance_before, balance_after, description, admin_id
                ) VALUES (?, ?, ?, 'admin_adjustment', ?, ?, 'Admin balance adjustment', ?)
            ");
            $transactionStmt->execute([
                $userId, 
                $assetSymbol, 
                $newBalance - $currentBalance, 
                $currentBalance, 
                $newBalance, 
                $_SESSION['admin_id']
            ]);
            
            // Log admin action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_user_id, description, old_values, new_values, ip_address)
                VALUES (?, 'update_balance', ?, 'Updated user balance', ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_id'],
                $userId,
                json_encode(['old_balance' => $currentBalance]),
                json_encode(['new_balance' => $newBalance, 'asset' => $assetSymbol]),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Balance updated successfully']);
            break;
            
        case 'impersonate_user':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $userId = intval($_POST['user_id'] ?? 0);
            if (!$userId) {
                throw new Exception('User ID required');
            }
            
            // Get user details
            $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'user'");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Store admin session for later restoration
            $_SESSION['original_admin_session'] = [
                'admin_logged_in' => $_SESSION['admin_logged_in'],
                'admin_id' => $_SESSION['admin_id'],
                'admin_username' => $_SESSION['admin_username'],
                'admin_name' => $_SESSION['admin_name'],
                'user_type' => $_SESSION['user_type'],
                'is_super_admin' => $_SESSION['is_super_admin']
            ];
            
            // Set user session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in'] = true;
            $_SESSION['impersonating'] = true;
            $_SESSION['impersonated_by'] = $_SESSION['admin_id'];
            
            // Log admin action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_user_id, description, ip_address)
                VALUES (?, 'impersonate_user', ?, 'Started impersonating user', ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_id'],
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Impersonation started', 'redirect' => 'dashboard.html']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>