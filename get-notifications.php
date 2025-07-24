<?php
require 'config.php'; // Your DB connection

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    if ($pdo) {
        $stmt = $pdo->prepare(
            "SELECT id, type, title, message, created_at as time, is_read
             FROM notifications
             WHERE user_id = :uid
             ORDER BY created_at DESC LIMIT 100"
        );
        $stmt->execute(['uid' => $user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } else {
        // Mock notifications for testing
        echo json_encode([
            'success' => true, 
            'notifications' => [
                [
                    'id' => 1,
                    'type' => 'info',
                    'title' => 'Welcome to Tripple Exchange',
                    'message' => 'Your account has been created successfully.',
                    'time' => date('Y-m-d H:i:s'),
                    'is_read' => 0
                ],
                [
                    'id' => 2,
                    'type' => 'warning',
                    'title' => 'Complete KYC Verification',
                    'message' => 'Please complete your KYC verification to unlock all features.',
                    'time' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'is_read' => 0
                ]
            ]
        ]);
    }
} catch (Exception $e) {
    error_log("Notifications API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>
