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

try {
    $user_id = $_SESSION['user_id'];
    
    // Get notifications for the user
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            message,
            type,
            is_read,
            created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    // Format notifications
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $formattedNotifications[] = [
            'id' => $notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'isRead' => (bool)$notification['is_read'],
            'time' => date('M j, Y g:i A', strtotime($notification['created_at'])),
            'timeAgo' => timeAgo($notification['created_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $formattedNotifications,
        'unreadCount' => intval($unreadCount),
        'totalCount' => count($formattedNotifications)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    return floor($time/31536000) . 'y ago';
}
?>