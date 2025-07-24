<?php
require 'config.php'; // Your DB connection

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, type, title, message, time, is_read
     FROM notifications
     WHERE user_id = :uid OR user_id IS NULL
     ORDER BY time DESC LIMIT 100"
);
$stmt->execute(['uid' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'notifications' => $notifications]);
