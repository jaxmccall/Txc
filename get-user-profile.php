<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// For now, return profile data based on session
// In a real implementation, this would fetch from database
$profile = [
    'fullName' => $_SESSION['username'] ?? 'User',
    'email' => $_SESSION['email'] ?? ($_SESSION['username'] ?? 'user') . '@trippleexchange.com',
    'phone' => '+1 (555) 000-0000', // Default placeholder
    'country' => 'United States', // Default placeholder
    'accountId' => 'CTP-' . str_pad($_SESSION['user_id'] ?? 1, 4, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
    'joinDate' => date('Y-m-d', strtotime('-30 days')), // Default to 30 days ago
    'lastLogin' => date('Y-m-d H:i:s'),
    'accountScore' => 100, // Default perfect score
    'kycStatus' => 'verified',
    'twoFactorEnabled' => false
];

echo json_encode([
    'success' => true,
    'profile' => $profile
]);
?>