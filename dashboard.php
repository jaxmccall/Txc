<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false, "error"=>"Not authenticated"]);
    exit;
}
$user_id = $_SESSION['user_id'];

// Get username
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username);
if (!$stmt->fetch()) {
    echo json_encode(["success"=>false, "error"=>"User not found"]);
    exit;
}
$stmt->close();

// Get all user asset balances
$stmt = $conn->prepare("SELECT asset_id, balance FROM assets WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$assets = [];
while ($row = $res->fetch_assoc()) {
    // Asset icons for major coins
    $icon = match(strtolower($row['asset_id'])) {
        'bitcoin' => 'btc',
        'btc' => 'btc',
        'ethereum' => 'ethereum',
        'eth' => 'ethereum',
        'usdt' => 'dollar-sign',
        default => 'coins'
    };
    // Nice name
    $name = match(strtolower($row['asset_id'])) {
        'usdt' => 'Tether',
        'btc' => 'Bitcoin',
        'bitcoin' => 'Bitcoin',
        'eth' => 'Ethereum',
        'ethereum' => 'Ethereum',
        default => strtoupper($row['asset_id'])
    };
    $assets[] = [
        'id' => strtolower($row['asset_id']),
        'name' => $name,
        'icon' => $icon,
        'balance' => floatval($row['balance'])
    ];
}
$stmt->close();

// Sort USDT first
usort($assets, function($a, $b){
    if ($a['id'] === 'usdt') return -1;
    if ($b['id'] === 'usdt') return 1;
    return strcmp($a['name'], $b['name']);
});

echo json_encode([
    "success" => true,
    "user" => ["username" => $username],
    "assets" => $assets,
    "notifications" => [] // For extension
]);
?>