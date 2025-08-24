<?php
// File: api/admin-notify-user.php
header('Content-Type: application/json');
require_once '../config.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$user_id = intval($input['id'] ?? 0);
$message = trim($input['message'] ?? '');

if (!$user_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'User ID and message are required']);
    exit;
}

try {
    // Verify user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Create notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (:user_id, 'Admin Message', :message, 'info', CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':message' => $message
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification sent successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in admin-notify-user.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>