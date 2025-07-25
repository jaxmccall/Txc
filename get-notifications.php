<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    http_response_code(401);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            message,
            type,
            is_read,
            created_at as time
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response to match expected structure
    foreach ($notifications as &$notification) {
        $notification['time'] = date('c', strtotime($notification['time'])); // ISO format
    }
    
    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'count' => count($notifications),
        'unread_count' => array_sum(array_column($notifications, 'is_read')) ? 
            count($notifications) - array_sum(array_column($notifications, 'is_read')) : 
            count($notifications)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
