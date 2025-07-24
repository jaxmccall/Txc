<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$title = $input['title'] ?? null;
$message = $input['message'] ?? null;
$type = $input['type'] ?? 'info';
$send_to_all = $input['send_to_all'] ?? false;

if (!$send_to_all && !$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required when not sending to all users']);
    exit;
}

if (!$title || !$message) {
    echo json_encode(['success' => false, 'message' => 'Title and message are required']);
    exit;
}

// Validate notification type
$allowed_types = ['info', 'success', 'warning', 'error', 'deposit', 'security', 'system'];
if (!in_array($type, $allowed_types)) {
    $type = 'info';
}

try {
    if ($pdo) {
        if ($send_to_all) {
            // Send notification to all active users
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type) 
                SELECT id, ?, ?, ? FROM users WHERE is_active = 1
            ");
            $stmt->execute([$title, $message, $type]);
            $notification_count = $stmt->rowCount();
            
            echo json_encode([
                'success' => true, 
                'message' => "Notification sent to {$notification_count} users",
                'count' => $notification_count
            ]);
        } else {
            // Send notification to specific user
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $title, $message, $type]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Notification sent successfully'
            ]);
        }
    } else {
        // Mock response for testing
        echo json_encode([
            'success' => true, 
            'message' => 'Notification sent successfully (mock mode)'
        ]);
    }
} catch (Exception $e) {
    error_log("Admin notification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>