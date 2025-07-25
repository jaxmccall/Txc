<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Support both POST body JSON and form data
$notification_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Try JSON first
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_id = $input['notification_id'] ?? $_POST['notification_id'] ?? $_POST['id'] ?? null;
}

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $result = $stmt->execute([$notification_id, $user_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to update notification']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
