<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_id = $input['notification_id'] ?? null;
    
    if (!$notification_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Notification ID required'
        ]);
        exit;
    }
    
    // Mark notification as read
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $result = $stmt->execute([$notification_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or already read'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>