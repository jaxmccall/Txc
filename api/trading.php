<?php
/**
 * Trading API - Tripple Exchange
 * Handles trade execution with balance validation
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
        case 'POST':
            // Place a new trade
            $input = json_decode(file_get_contents('php://input'), true);
            
            $pair = $input['pair'] ?? null;
            $side = $input['side'] ?? null; // 'buy' or 'sell'
            $amount = $input['amount'] ?? null;
            $price = $input['price'] ?? null;
            $type = $input['type'] ?? 'market'; // 'market' or 'limit'
            
            // Validate required fields
            if (!$pair || !$side || !$amount) {
                sendError('Missing required fields: pair, side, amount');
            }
            
            if (!in_array($side, ['buy', 'sell'])) {
                sendError('Invalid side. Must be "buy" or "sell"');
            }
            
            $amount = floatval($amount);
            $price = $price ? floatval($price) : null;
            
            if ($amount <= 0) {
                sendError('Amount must be greater than 0');
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Determine required asset for balance check
                list($base_asset, $quote_asset) = explode('/', $pair);
                $required_asset = ($side === 'buy') ? $quote_asset : $base_asset;
                
                // For market orders, estimate price
                if ($type === 'market' && !$price) {
                    // In production, fetch current market price
                    $price = getMarketPrice($pair);
                }
                
                $total = $amount * $price;
                $required_amount = ($side === 'buy') ? $total : $amount;
                
                // Check user balance
                $stmt = $pdo->prepare("
                    SELECT balance FROM user_balances 
                    WHERE user_id = ? AND asset_symbol = ?
                ");
                $stmt->execute([$user_id, $required_asset]);
                $current_balance = $stmt->fetchColumn() ?: 0;
                
                if ($current_balance < $required_amount) {
                    throw new Exception("Insufficient balance. Required: {$required_amount} {$required_asset}, Available: {$current_balance} {$required_asset}");
                }
                
                // Create trade record
                $stmt = $pdo->prepare("
                    INSERT INTO trades (user_id, pair, side, amount, price, total, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$user_id, $pair, $side, $amount, $price, $total]);
                $trade_id = $pdo->lastInsertId();
                
                // Lock the required balance
                $stmt = $pdo->prepare("
                    UPDATE user_balances 
                    SET balance = balance - ?, locked_balance = locked_balance + ?
                    WHERE user_id = ? AND asset_symbol = ?
                ");
                $stmt->execute([$required_amount, $required_amount, $user_id, $required_asset]);
                
                // Record the balance transaction
                $stmt = $pdo->prepare("
                    INSERT INTO balance_transactions 
                    (user_id, asset_symbol, amount, transaction_type, balance_before, balance_after, description, reference_id)
                    VALUES (?, ?, ?, 'debit', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $required_asset, $required_amount, 
                    $current_balance, $current_balance - $required_amount,
                    "Trade {$side} {$amount} {$base_asset} @ {$price}", 
                    "trade_{$trade_id}"
                ]);
                
                // Simulate trade execution (in production, this would interface with exchange)
                $executed_price = $price * (1 + (rand(-100, 100) / 10000)); // ±1% slippage simulation
                $executed_total = $amount * $executed_price;
                
                // Complete the trade
                $stmt = $pdo->prepare("
                    UPDATE trades 
                    SET status = 'completed', price = ?, total = ?, completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$executed_price, $executed_total, $trade_id]);
                
                // Unlock the locked balance and credit the received asset
                $received_asset = ($side === 'buy') ? $base_asset : $quote_asset;
                $received_amount = ($side === 'buy') ? $amount : $executed_total;
                
                // Unlock the locked balance
                $stmt = $pdo->prepare("
                    UPDATE user_balances 
                    SET locked_balance = locked_balance - ?
                    WHERE user_id = ? AND asset_symbol = ?
                ");
                $stmt->execute([$required_amount, $user_id, $required_asset]);
                
                // Credit the received asset
                $stmt = $pdo->prepare("
                    INSERT INTO user_balances (user_id, asset_symbol, balance)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    balance = balance + ?,
                    updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$user_id, $received_asset, $received_amount, $received_amount]);
                
                // Record credit transaction
                $stmt = $pdo->prepare("
                    SELECT balance FROM user_balances 
                    WHERE user_id = ? AND asset_symbol = ?
                ");
                $stmt->execute([$user_id, $received_asset]);
                $new_balance = $stmt->fetchColumn() ?: $received_amount;
                
                $stmt = $pdo->prepare("
                    INSERT INTO balance_transactions 
                    (user_id, asset_symbol, amount, transaction_type, balance_before, balance_after, description, reference_id)
                    VALUES (?, ?, ?, 'credit', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $received_asset, $received_amount,
                    $new_balance - $received_amount, $new_balance,
                    "Trade {$side} {$amount} {$base_asset} @ {$executed_price} completed",
                    "trade_{$trade_id}"
                ]);
                
                // Create notification
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, ?, ?, 'success')
                ");
                $stmt->execute([
                    $user_id,
                    'Trade Completed',
                    "Your {$side} order for {$amount} {$base_asset} has been completed at {$executed_price} {$quote_asset}"
                ]);
                
                $pdo->commit();
                
                sendSuccess([
                    'trade_id' => $trade_id,
                    'pair' => $pair,
                    'side' => $side,
                    'amount' => $amount,
                    'executed_price' => $executed_price,
                    'total' => $executed_total,
                    'status' => 'completed',
                    'message' => 'Trade executed successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                sendError('Trade execution failed: ' . $e->getMessage());
            }
            break;
            
        case 'GET':
            // Get user's trade history
            $limit = $_GET['limit'] ?? 20;
            $offset = $_GET['offset'] ?? 0;
            
            $stmt = $pdo->prepare("
                SELECT 
                    id, pair, side, amount, price, total, status,
                    created_at, completed_at
                FROM trades 
                WHERE user_id = ?
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user_id, $limit, $offset]);
            $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendSuccess(['trades' => $trades]);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

function getMarketPrice($pair) {
    // Simulate market prices - in production, fetch from real exchange API
    $prices = [
        'BTC/USDT' => 63000 + rand(-1000, 1000),
        'ETH/USDT' => 3100 + rand(-100, 100),
        'BNB/USDT' => 580 + rand(-20, 20),
        'ADA/USDT' => 0.45 + rand(-5, 5) / 100,
        'XRP/USDT' => 0.52 + rand(-3, 3) / 100,
        'SOL/USDT' => 140 + rand(-10, 10),
        'DOT/USDT' => 25 + rand(-2, 2)
    ];
    
    return $prices[$pair] ?? 1;
}
?>