<?php
require 'config.php';
header('Content-Type: application/json');
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$id = $_POST['id'] ?? null;

if (!$user_id || !$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$stmt = $pdo->prepare(
    "UPDATE notifications SET is_read=1 WHERE id=:id AND (user_id=:uid OR user_id IS NULL)"
);
$stmt->execute(['id' => $id, 'uid' => $user_id]);

echo json_encode(['success' => true]);
