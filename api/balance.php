<?php
/**
 * Balance Management API - Tripple Exchange
 * Provides real-time balance data for authenticated users
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function sendSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendError('Authentication required', 401);
}

$user_id = $_SESSION['user_id'];

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get user balances
            $stmt = $pdo->prepare("
                SELECT 
                    ub.asset_symbol,
                    ub.balance,
                    ub.locked_balance,
                    ac.name as asset_name,
                    ac.decimals
                FROM user_balances ub 
                LEFT JOIN asset_configs ac ON ub.asset_symbol = ac.symbol
                WHERE ub.user_id = ? AND ub.balance > 0
                ORDER BY ac.sort_order ASC, ub.asset_symbol ASC
            ");
            $stmt->execute([$user_id]);
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total portfolio value (in USDT)
            $total_value = 0;
            foreach ($balances as &$balance) {
                // For now, use USDT as base. In production, fetch live prices
                $price = ($balance['asset_symbol'] === 'USDT') ? 1 : 0;
                $balance['usd_value'] = $balance['balance'] * $price;
                $total_value += $balance['usd_value'];
            }
            
            sendSuccess([
                'balances' => $balances,
                'total_value' => $total_value,
                'user_id' => $user_id
            ]);
            break;
            
        case 'POST':
            // Admin adjustment of balance (requires admin privileges)
            if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
                sendError('Admin privileges required', 403);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $target_user_id = $input['user_id'] ?? null;
            $asset_symbol = $input['asset_symbol'] ?? null;
            $amount = $input['amount'] ?? null;
            $type = $input['type'] ?? 'credit'; // credit or debit
            $description = $input['description'] ?? 'Admin adjustment';
            
            if (!$target_user_id || !$asset_symbol || !$amount) {
                sendError('Missing required fields: user_id, asset_symbol, amount');
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Get current balance
                $stmt = $pdo->prepare("
                    SELECT balance FROM user_balances 
                    WHERE user_id = ? AND asset_symbol = ?
                ");
                $stmt->execute([$target_user_id, $asset_symbol]);
                $current_balance = $stmt->fetchColumn() ?: 0;
                
                // Calculate new balance
                $new_balance = ($type === 'credit') ? 
                    $current_balance + $amount : 
                    $current_balance - $amount;
                
                if ($new_balance < 0) {
                    throw new Exception('Insufficient balance');
                }
                
                // Update balance
                $stmt = $pdo->prepare("
                    INSERT INTO user_balances (user_id, asset_symbol, balance)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    balance = ?,
                    updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$target_user_id, $asset_symbol, $new_balance, $new_balance]);
                
                // Record transaction
                $stmt = $pdo->prepare("
                    INSERT INTO balance_transactions 
                    (user_id, asset_symbol, amount, transaction_type, balance_before, balance_after, description, admin_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $target_user_id, $asset_symbol, $amount, 
                    'admin_adjustment', $current_balance, $new_balance, 
                    $description, $_SESSION['admin_id']
                ]);
                
                $pdo->commit();
                sendSuccess(['message' => 'Balance updated successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                sendError('Transaction failed: ' . $e->getMessage());
            }
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
?>