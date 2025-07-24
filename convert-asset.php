<?php
session_start();
require_once 'config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$from = strtoupper($data['from'] ?? '');
$to = strtoupper($data['to'] ?? '');
$amount = (float)($data['amount'] ?? 0);

// Validate input
if (empty($from) || empty($to) || $amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversion parameters']);
    exit();
}

// Get asset IDs for both assets
$stmt = $mysqli->prepare("SELECT id FROM assets WHERE symbol = ?");

// Get FROM asset ID
$stmt->bind_param("s", $from);
$stmt->execute();
$result = $stmt->get_result();
$fromAsset = $result->fetch_assoc();
$fromAssetId = $fromAsset['id'] ?? null;

// Get TO asset ID
$stmt->bind_param("s", $to);
$stmt->execute();
$result = $stmt->get_result();
$toAsset = $result->fetch_assoc();
$toAssetId = $toAsset['id'] ?? null;

$stmt->close();

if (!$fromAssetId || !$toAssetId) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset symbols']);
    exit();
}

// Start transaction
$mysqli->autocommit(false);

try {
    // Check current balance and deduct from source asset
    $stmt = $mysqli->prepare("UPDATE user_assets SET balance = balance - ? 
                             WHERE user_id = ? AND asset_id = ? AND balance >= ?");
    $stmt->bind_param("diii", $amount, $user_id, $fromAssetId, $amount);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Insufficient balance or asset not found');
    }
    $stmt->close();
    
    // Get conversion rate (simplified - in production this would come from external API)
    $conversionRate = match(true) {
        $from === 'BTC' && $to === 'USDT' => 63000,
        $from === 'ETH' && $to === 'USDT' => 3100,
        $from === 'USDT' && $to === 'BTC' => 1/63000,
        $from === 'USDT' && $to === 'ETH' => 1/3100,
        default => 1 // Same asset or 1:1 conversion
    };
    
    // Add to target asset
    $convertedAmount = $amount * $conversionRate;
    $stmt = $mysqli->prepare("INSERT INTO user_assets (user_id, asset_id, balance, created_at) 
                             VALUES (?, ?, ?, NOW())
                             ON DUPLICATE KEY UPDATE balance = balance + ?");
    $stmt->bind_param("iidd", $user_id, $toAssetId, $convertedAmount, $convertedAmount);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $mysqli->commit();
    
    // Log successful conversion
    error_log("CONVERSION SUCCESS: User $user_id converted $amount $from to $convertedAmount $to");
    
    echo json_encode([
        'success' => true, 
        'converted_amount' => $convertedAmount,
        'rate' => $conversionRate
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $mysqli->rollback();
    
    // Log conversion failure
    error_log("CONVERSION FAILED: User $user_id - " . $e->getMessage());
    
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    // Restore autocommit
    $mysqli->autocommit(true);
}
?>