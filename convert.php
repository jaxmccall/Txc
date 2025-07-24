<?php
header("Content-Type: application/json");
require_once 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['user_id'], $input['from_asset'], $input['to_asset'], $input['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$userId = (int)$input['user_id'];
$fromAsset = $mysqli->real_escape_string($input['from_asset']);
$toAsset = $mysqli->real_escape_string($input['to_asset']);
$amount = (float)$input['amount'];

// Start transaction
$mysqli->begin_transaction();

try {
    // 1. Verify user balance
    $balanceQuery = $mysqli->prepare("SELECT balance FROM user_balances WHERE user_id = ? AND asset_id = ?");
    $balanceQuery->bind_param("is", $userId, $fromAsset);
    $balanceQuery->execute();
    $balanceResult = $balanceQuery->get_result();
    
    if ($balanceResult->num_rows === 0) {
        throw new Exception("Asset not found in user wallet");
    }
    
    $balanceRow = $balanceResult->fetch_assoc();
    $currentBalance = (float)$balanceRow['balance'];
    
    if ($amount > $currentBalance) {
        throw new Exception("Insufficient balance");
    }
    
    // 2. Get current conversion rate (from external API)
    $rate = getConversionRate($fromAsset, $toAsset);
    $convertedAmount = $amount * $rate;
    
    // 3. Update balances
    // Deduct from_asset
    $updateFrom = $mysqli->prepare("
        UPDATE user_balances 
        SET balance = balance - ? 
        WHERE user_id = ? AND asset_id = ?
    ");
    $updateFrom->bind_param("dis", $amount, $userId, $fromAsset);
    $updateFrom->execute();
    
    // Add to_asset (or create if doesn't exist)
    $updateTo = $mysqli->prepare("
        INSERT INTO user_balances (user_id, asset_id, balance)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE balance = balance + ?
    ");
    $updateTo->bind_param("isdd", $userId, $toAsset, $convertedAmount, $convertedAmount);
    $updateTo->execute();
    
    // 4. Record transaction
    $insertTx = $mysqli->prepare("
        INSERT INTO conversions 
        (user_id, from_asset, to_asset, amount, rate, converted_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, 'completed')
    ");
    $insertTx->bind_param("issddd", 
        $userId, 
        $fromAsset, 
        $toAsset, 
        $amount, 
        $rate, 
        $convertedAmount
    );
    $insertTx->execute();
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'new_balances' => [
            $fromAsset => $currentBalance - $amount,
            $toAsset => $convertedAmount
        ]
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Mock function to get conversion rate
function getConversionRate($from, $to) {
    // In production, fetch from CoinGecko API
    $rates = [
        'btc_usdt' => 63000,
        'eth_usdt' => 3100,
        'bnb_usdt' => 580,
        'ada_usdt' => 0.45
    ];
    
    $key = strtolower($from . '_' . $to);
    return $rates[$key] ?? 1;
}