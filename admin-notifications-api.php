<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_notification':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $recipientType = $_POST['recipient_type'] ?? ''; // 'all', 'user', 'kyc_pending', 'verified'
            $userId = intval($_POST['user_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $type = $_POST['type'] ?? 'info'; // info, success, warning, error, admin
            
            if (empty($title) || empty($message)) {
                throw new Exception('Title and message are required');
            }
            
            if (!in_array($type, ['info', 'success', 'warning', 'error', 'admin', 'system'])) {
                $type = 'info';
            }
            
            $recipientIds = [];
            
            // Determine recipients based on type
            switch ($recipientType) {
                case 'all':
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_type = 'user' AND is_active = 1");
                    $stmt->execute();
                    $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                    
                case 'user':
                    if (!$userId) {
                        throw new Exception('User ID required for individual notification');
                    }
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'user'");
                    $stmt->execute([$userId]);
                    if ($stmt->fetchColumn()) {
                        $recipientIds = [$userId];
                    } else {
                        throw new Exception('User not found');
                    }
                    break;
                    
                case 'kyc_pending':
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_type = 'user' AND kyc_status IN ('pending', 'submitted')");
                    $stmt->execute();
                    $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                    
                case 'verified':
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_type = 'user' AND kyc_status = 'approved'");
                    $stmt->execute();
                    $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                    
                default:
                    throw new Exception('Invalid recipient type');
            }
            
            if (empty($recipientIds)) {
                throw new Exception('No recipients found');
            }
            
            // Send notifications
            $insertStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, sender_id, title, message, type) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $sentCount = 0;
            foreach ($recipientIds as $recipientId) {
                $insertStmt->execute([$recipientId, $_SESSION['admin_id'], $title, $message, $type]);
                $sentCount++;
            }
            
            // Log admin action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, description, new_values, ip_address)
                VALUES (?, 'send_notification', 'Sent notification to users', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_id'],
                json_encode([
                    'recipient_type' => $recipientType,
                    'title' => $title,
                    'type' => $type,
                    'recipients_count' => $sentCount
                ]),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => "Notification sent to {$sentCount} user(s)",
                'sent_count' => $sentCount
            ]);
            break;
            
        case 'get_notifications':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications n
                INNER JOIN users u ON n.user_id = u.id
                WHERE n.sender_id = ? OR n.sender_id IS NULL
            ");
            $countStmt->execute([$_SESSION['admin_id']]);
            $totalNotifications = $countStmt->fetchColumn();
            
            // Get notifications
            $stmt = $pdo->prepare("
                SELECT 
                    n.*,
                    u.username, u.first_name, u.last_name,
                    sender.username as sender_username
                FROM notifications n
                INNER JOIN users u ON n.user_id = u.id
                LEFT JOIN users sender ON n.sender_id = sender.id
                WHERE n.sender_id = ? OR n.sender_id IS NULL
                ORDER BY n.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute([$_SESSION['admin_id']]);
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
            
        case 'get_notification_templates':
            $templates = [
                [
                    'id' => 'welcome',
                    'title' => 'Welcome to Tripple Exchange',
                    'message' => 'Welcome to Tripple Exchange! Complete your KYC verification to unlock all trading features.',
                    'type' => 'success'
                ],
                [
                    'id' => 'kyc_reminder',
                    'title' => 'Complete Your KYC Verification',
                    'message' => 'Please complete your KYC verification to access full trading features on Tripple Exchange.',
                    'type' => 'warning'
                ],
                [
                    'id' => 'security_alert',
                    'title' => 'Security Alert',
                    'message' => 'Enable 2FA and review your account security settings to keep your assets safe.',
                    'type' => 'warning'
                ],
                [
                    'id' => 'maintenance',
                    'title' => 'Scheduled Maintenance',
                    'message' => 'Tripple Exchange will undergo scheduled maintenance on [DATE] from [TIME] to [TIME]. Trading will be temporarily unavailable.',
                    'type' => 'info'
                ],
                [
                    'id' => 'new_feature',
                    'title' => 'New Feature Available',
                    'message' => 'Check out our latest feature: [FEATURE_NAME]. Visit your dashboard to learn more.',
                    'type' => 'info'
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
            break;
            
        case 'get_user_list':
            $search = $_GET['search'] ?? '';
            $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
            
            $whereClause = "WHERE user_type = 'user'";
            $params = [];
            
            if ($search) {
                $whereClause .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                $searchTerm = "%$search%";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }
            
            $stmt = $pdo->prepare("
                SELECT id, username, email, first_name, last_name, kyc_status
                FROM users 
                $whereClause
                ORDER BY username
                LIMIT $limit
            ");
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>