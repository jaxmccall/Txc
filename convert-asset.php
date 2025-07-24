<?php
session_start();
require_once '../db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$from = $data['from'];
$to = $data['to'];
$amount = (float) $data['amount'];

// Get asset IDs
$stmt = $db->prepare("SELECT id FROM assets WHERE symbol = ?");
$stmt->execute([strtoupper($from)]);
$fromAsset = $stmt->fetch(PDO::FETCH_ASSOC);
$fromAssetId = $fromAsset['id'] ?? null;

$stmt->execute([strtoupper($to)]);
$toAsset = $stmt->fetch(PDO::FETCH_ASSOC);
$toAssetId = $toAsset['id'] ?? null;

if (!$fromAssetId || !$toAssetId) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset']);
    exit();
}

// Start transaction
$db->beginTransaction();

try {
    // Deduct from source asset
    $stmt = $db->prepare("UPDATE user_assets SET balance = balance - ? 
                         WHERE user_id = ? AND asset_id = ? AND balance >= ?");
    $stmt->execute([$amount, $user_id, $fromAssetId, $amount]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Insufficient balance');
    }
    
    // Get conversion rate (this would be fetched from an external API in real implementation)
    $conversionRate = 1; // Simplified for example
    
    // Add to target asset
    $convertedAmount = $amount * $conversionRate;
    $stmt = $db->prepare("UPDATE user_assets SET balance = balance + ? 
                         WHERE user_id = ? AND asset_id = ?");
    $stmt->execute([$convertedAmount, $user_id, $toAssetId]);
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}