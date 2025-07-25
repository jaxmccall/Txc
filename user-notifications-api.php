<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_notifications':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(50, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            
            $whereClause = "WHERE user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $whereClause .= " AND is_read = 0";
            }
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $whereClause");
            $countStmt->execute($params);
            $totalNotifications = $countStmt->fetchColumn();
            
            // Get notifications
            $stmt = $pdo->prepare("
                SELECT 
                    n.*,
                    sender.username as sender_username,
                    sender.first_name as sender_first_name,
                    sender.last_name as sender_last_name
                FROM notifications n
                LEFT JOIN users sender ON n.sender_id = sender.id
                $whereClause
                ORDER BY n.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalNotifications / $limit),
                    'total_notifications' => $totalNotifications,
                    'per_page' => $limit
                ]
            ]);
            break;
            
        case 'mark_read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $notificationId = intval($_POST['notification_id'] ?? 0);
            
            if ($notificationId) {
                // Mark specific notification as read
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$notificationId, $userId]);
            } else {
                // Mark all notifications as read
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE user_id = ? AND is_read = 0
                ");
                $stmt->execute([$userId]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
            break;
            
        case 'get_unread_count':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $unreadCount = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'unread_count' => $unreadCount
            ]);
            break;
            
        case 'delete_notification':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $notificationId = intval($_POST['notification_id'] ?? 0);
            
            if (!$notificationId) {
                throw new Exception('Notification ID required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Notification not found');
            }
            
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>