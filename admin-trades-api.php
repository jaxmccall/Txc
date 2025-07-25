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
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_trades':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $status = $_GET['status'] ?? '';
            $userId = intval($_GET['user_id'] ?? 0);
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($status && in_array($status, ['pending', 'completed', 'cancelled', 'win', 'loss'])) {
                $whereClause .= " AND t.status = ?";
                $params[] = $status;
            }
            
            if ($userId) {
                $whereClause .= " AND t.user_id = ?";
                $params[] = $userId;
            }
            
            // Get total count
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM trades t
                INNER JOIN users u ON t.user_id = u.id
                $whereClause
            ");
            $countStmt->execute($params);
            $totalTrades = $countStmt->fetchColumn();
            
            // Get trades
            $stmt = $pdo->prepare("
                SELECT 
                    t.*,
                    u.username, u.first_name, u.last_name,
                    modifier.username as modified_by_username
                FROM trades t
                INNER JOIN users u ON t.user_id = u.id
                LEFT JOIN users modifier ON t.modified_by = modifier.id
                $whereClause
                ORDER BY t.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'trades' => $trades,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalTrades / $limit),
                    'total_trades' => $totalTrades,
                    'per_page' => $limit
                ]
            ]);
            break;
            
        case 'update_trade':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $tradeId = intval($_POST['trade_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $result = $_POST['result'] ?? '';
            $profitLoss = floatval($_POST['profit_loss'] ?? 0);
            $pair = $_POST['pair'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (!$tradeId) {
                throw new Exception('Trade ID required');
            }
            
            // Get current trade
            $currentStmt = $pdo->prepare("SELECT * FROM trades WHERE id = ?");
            $currentStmt->execute([$tradeId]);
            $currentTrade = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentTrade) {
                throw new Exception('Trade not found');
            }
            
            $updates = [];
            $params = [];
            
            // Build update query dynamically
            if ($status && in_array($status, ['pending', 'completed', 'cancelled', 'win', 'loss'])) {
                $updates[] = "status = ?";
                $params[] = $status;
            }
            
            if ($result && in_array($result, ['win', 'loss', 'pending'])) {
                $updates[] = "result = ?";
                $params[] = $result;
            }
            
            if (isset($_POST['profit_loss'])) {
                $updates[] = "profit_loss = ?";
                $params[] = $profitLoss;
            }
            
            if ($pair) {
                $updates[] = "pair = ?";
                $params[] = $pair;
            }
            
            if ($notes !== null) {
                $updates[] = "admin_notes = ?";
                $params[] = $notes;
            }
            
            if (!empty($updates)) {
                $updates[] = "modified_by = ?";
                $updates[] = "modified_at = NOW()";
                $params[] = $_SESSION['admin_id'];
                $params[] = $tradeId; // For WHERE clause
                
                $stmt = $pdo->prepare("
                    UPDATE trades 
                    SET " . implode(', ', $updates) . "
                    WHERE id = ?
                ");
                $stmt->execute($params);
            }
            
            // If marking as win/loss, handle balance adjustments
            if ($result && in_array($result, ['win', 'loss']) && $profitLoss != 0) {
                $userId = $currentTrade['user_id'];
                
                // Get user's current USDT balance
                $balanceStmt = $pdo->prepare("
                    SELECT balance FROM user_balances 
                    WHERE user_id = ? AND asset_symbol = 'USDT'
                ");
                $balanceStmt->execute([$userId]);
                $currentBalance = $balanceStmt->fetchColumn() ?: 0;
                
                $newBalance = $currentBalance + $profitLoss;
                
                // Update balance
                $updateBalanceStmt = $pdo->prepare("
                    INSERT INTO user_balances (user_id, asset_symbol, balance) 
                    VALUES (?, 'USDT', ?)
                    ON DUPLICATE KEY UPDATE balance = VALUES(balance), updated_at = NOW()
                ");
                $updateBalanceStmt->execute([$userId, $newBalance]);
                
                // Log transaction
                $transactionStmt = $pdo->prepare("
                    INSERT INTO balance_transactions (
                        user_id, asset_symbol, amount, transaction_type, 
                        balance_before, balance_after, description, reference_id, admin_id
                    ) VALUES (?, 'USDT', ?, 'trade', ?, ?, ?, ?, ?)
                ");
                $transactionStmt->execute([
                    $userId, 
                    $profitLoss, 
                    $currentBalance, 
                    $newBalance, 
                    "Trade #{$tradeId} " . ($result === 'win' ? 'profit' : 'loss'),
                    "trade_{$tradeId}",
                    $_SESSION['admin_id']
                ]);
                
                // Send notification to user
                $notificationStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, sender_id, title, message, type) 
                    VALUES (?, ?, ?, ?, 'trade')
                ");
                
                $title = $result === 'win' ? 'Trade Profit Credited' : 'Trade Loss Applied';
                $message = "Your trade #{$tradeId} ({$currentTrade['pair']}) resulted in a " . 
                          ($result === 'win' ? 'profit' : 'loss') . 
                          " of " . ($profitLoss >= 0 ? '+' : '') . number_format($profitLoss, 2) . " USDT.";
                
                $notificationStmt->execute([$userId, $_SESSION['admin_id'], $title, $message]);
            }
            
            // Log admin action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_user_id, description, old_values, new_values, ip_address)
                VALUES (?, 'update_trade', ?, 'Updated trade details', ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_id'],
                $currentTrade['user_id'],
                json_encode($currentTrade),
                json_encode($_POST),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Trade updated successfully']);
            break;
            
        case 'create_trade':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $userId = intval($_POST['user_id'] ?? 0);
            $pair = $_POST['pair'] ?? '';
            $side = $_POST['side'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            
            if (!$userId || !$pair || !$side || !$amount || !$price) {
                throw new Exception('All trade fields are required');
            }
            
            if (!in_array($side, ['buy', 'sell'])) {
                throw new Exception('Invalid trade side');
            }
            
            if (!in_array($status, ['pending', 'completed', 'cancelled'])) {
                $status = 'pending';
            }
            
            $total = $amount * $price;
            
            // Create trade
            $stmt = $pdo->prepare("
                INSERT INTO trades (user_id, pair, side, amount, price, total, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $pair, $side, $amount, $price, $total, $status]);
            $tradeId = $pdo->lastInsertId();
            
            // Log admin action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_user_id, description, new_values, ip_address)
                VALUES (?, 'create_trade', ?, 'Created trade for user', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_id'],
                $userId,
                json_encode($_POST),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            // Send notification to user
            $notificationStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, sender_id, title, message, type) 
                VALUES (?, ?, ?, ?, 'trade')
            ");
            
            $notificationStmt->execute([
                $userId, 
                $_SESSION['admin_id'], 
                'New Trade Created',
                "A new {$side} trade for {$amount} {$pair} has been created in your account.",
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Trade created successfully',
                'trade_id' => $tradeId
            ]);
            break;
            
        case 'get_trade_stats':
            // Get trade statistics
            $stats = [];
            
            // Total trades by status
            $statusCounts = $pdo->query("
                SELECT status, COUNT(*) as count 
                FROM trades 
                GROUP BY status
            ")->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $stats['pending'] = $statusCounts['pending'] ?? 0;
            $stats['completed'] = $statusCounts['completed'] ?? 0;
            $stats['cancelled'] = $statusCounts['cancelled'] ?? 0;
            $stats['win'] = $statusCounts['win'] ?? 0;
            $stats['loss'] = $statusCounts['loss'] ?? 0;
            $stats['total'] = array_sum($statusCounts);
            
            // Total volume
            $volumeStmt = $pdo->query("
                SELECT COALESCE(SUM(total), 0) FROM trades WHERE status IN ('completed', 'win', 'loss')
            ");
            $stats['total_volume'] = $volumeStmt->fetchColumn();
            
            // Most traded pairs
            $pairsStmt = $pdo->query("
                SELECT pair, COUNT(*) as count, SUM(total) as volume
                FROM trades 
                WHERE status IN ('completed', 'win', 'loss')
                GROUP BY pair 
                ORDER BY count DESC 
                LIMIT 5
            ");
            $stats['top_pairs'] = $pairsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        case 'get_trading_pairs':
            $pairs = [
                'BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'ADA/USDT', 'XRP/USDT',
                'SOL/USDT', 'DOT/USDT', 'DOGE/USDT', 'LTC/USDT', 'ETH/BTC',
                'BNB/BTC', 'ADA/BTC', 'XRP/BTC', 'SOL/BTC', 'DOT/BTC'
            ];
            
            echo json_encode([
                'success' => true,
                'pairs' => $pairs
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>