<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch user assets with real balances from database
    $stmt = $pdo->prepare("
        SELECT 
            ub.asset_symbol as symbol,
            ac.name,
            ub.balance,
            ub.locked_balance,
            ac.decimals
        FROM user_balances ub
        JOIN asset_configs ac ON ub.asset_symbol = ac.symbol
        WHERE ub.user_id = ? AND ub.balance > 0
        ORDER BY ac.sort_order ASC
    ");
    $stmt->execute([$user_id]);
    $userAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch live prices from external API (or use cached prices)
    $prices = fetchLivePrices();
    
    // Calculate current values
    foreach ($userAssets as &$asset) {
        $symbol = $asset['symbol'];
        
        // Get current price from live data or fallback to static
        $asset['currentPrice'] = $prices[$symbol] ?? getStaticPrice($symbol);
        $asset['change24h'] = $prices[$symbol . '_change'] ?? 0;
        $asset['value'] = $asset['balance'] * $asset['currentPrice'];
        
        // Add icon path
        $asset['icon'] = "/assets/icons/" . strtolower($symbol) . ".png";
        $asset['id'] = $symbol; // For compatibility
    }
    
    echo json_encode([
        'success' => true,
        'assets' => $userAssets,
        'total_value' => array_sum(array_column($userAssets, 'value'))
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

function fetchLivePrices() {
    // In production, this would fetch from CoinGecko or other price API
    // For now, return empty array to use static prices
    return [];
}

function getStaticPrice($symbol) {
    // Fallback static prices for development
    return match($symbol) {
        'BTC' => 63000,
        'ETH' => 3100,
        'USDT' => 1,
        'BNB' => 580,
        'ADA' => 0.45,
        'XRP' => 0.52,
        'SOL' => 140,
        'DOT' => 25,
        default => 1
    };
}
?>