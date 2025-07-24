<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user assets from database
$stmt = $mysqli->prepare("SELECT a.id, a.symbol, a.name, a.icon, ua.balance 
                     FROM user_assets ua
                     JOIN assets a ON ua.asset_id = a.id
                     WHERE ua.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userAssets = $result->fetch_all(MYSQLI_ASSOC);

// Add current prices and values (would be fetched from external API in real implementation)
foreach ($userAssets as &$asset) {
    // This would be replaced with actual price fetching logic
    $asset['currentPrice'] = match($asset['symbol']) {
        'BTC' => 63000,
        'ETH' => 3100,
        'USDT' => 1,
        'BNB' => 580,
        'ADA' => 0.45,
        'XRP' => 0.52,
        'SOL' => 140,
        default => 1
    };
    $asset['change24h'] = match($asset['symbol']) {
        'BTC' => 2.5,
        'ETH' => -1.2,
        'USDT' => 0,
        'BNB' => 0.8,
        'ADA' => 1.2,
        'XRP' => -0.3,
        'SOL' => 3.7,
        default => 0
    };
    $asset['value'] = $asset['balance'] * $asset['currentPrice'];
}

echo json_encode([
    'success' => true,
    'assets' => $userAssets
]);