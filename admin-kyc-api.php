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
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_kyc_submissions':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $status = $_GET['status'] ?? '';
            
            $whereClause = "WHERE u.user_type = 'user'";
            $params = [];
            
            if ($status && in_array($status, ['pending', 'submitted', 'approved', 'rejected'])) {
                $whereClause .= " AND u.kyc_status = ?";
                $params[] = $status;
            }
            
            // Get total count
            $countStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT u.id) 
                FROM users u 
                LEFT JOIN kyc_documents kd ON u.id = kd.user_id 
                $whereClause
            ");
            $countStmt->execute($params);
            $totalSubmissions = $countStmt->fetchColumn();
            
            // Get KYC submissions
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.username, u.email, u.first_name, u.last_name,
                    u.kyc_status, u.kyc_submitted_at, u.kyc_reviewed_at,
                    u.created_at,
                    COUNT(kd.id) as document_count,
                    GROUP_CONCAT(DISTINCT kd.document_type) as document_types
                FROM users u
                LEFT JOIN kyc_documents kd ON u.id = kd.user_id
                $whereClause
                GROUP BY u.id
                ORDER BY 
                    CASE u.kyc_status 
                        WHEN 'submitted' THEN 1 
                        WHEN 'pending' THEN 2 
                        WHEN 'approved' THEN 3 
                        WHEN 'rejected' THEN 4 
                    END,
                    u.kyc_submitted_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'submissions' => $submissions,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalSubmissions / $limit),
                    'total_submissions' => $totalSubmissions,
                    'per_page' => $limit
                ]
            ]);
            break;
            
        case 'get_kyc_details':
            $userId = intval($_GET['user_id'] ?? 0);
            if (!$userId) {
                throw new Exception('User ID required');
            }
            
            // Get user details
            $userStmt = $pdo->prepare("
                SELECT * FROM users WHERE id = ? AND user_type = 'user'
            ");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get KYC documents
            $docsStmt = $pdo->prepare("
                SELECT * FROM kyc_documents 
                WHERE user_id = ? 
                ORDER BY document_type, created_at DESC
            ");
            $docsStmt->execute([$userId]);
            $documents = $docsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'documents' => $documents
            ]);
            break;
            
        case 'update_kyc_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $userId = intval($_POST['user_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (!$userId || !in_array($status, ['pending', 'submitted', 'approved', 'rejected'])) {
                throw new Exception('Invalid user ID or status');
            }
            
            // Update user KYC status
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET kyc_status = ?, kyc_reviewed_at = NOW(), kyc_reviewed_by = ?, updated_at = NOW()
                WHERE id = ? AND user_type = 'user'
            ");
            $updateStmt->execute([$status, $_SESSION['admin_id'], $userId]);
            
            // Update document statuses if approving or rejecting
            if (in_array($status, ['approved', 'rejected'])) {
                $docUpdateStmt = $pdo->prepare("
                    UPDATE kyc_documents 
                    SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?
                    WHERE user_id = ?
                ");
                $docUpdateStmt->execute([$status, $_SESSION['admin_id'], $notes, $userId]);
            }
            
            // Create notification for user
            $notificationStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, sender_id, title, message, type) 
                VALUES (?, ?, ?, ?, 'kyc')
            ");
            
            switch ($status) {
                case 'approved':
                    $title = 'KYC Verification Approved';
                    $message = 'Your identity verification has been approved. You now have full access to all platform features.';
                    break;
                case 'rejected':
                    $title = 'KYC Verification Rejected';
                    $message = 'Your identity verification was rejected. ' . ($notes ? 'Reason: ' . $notes : 'Please contact support for more information.');
                    break;
                default:
                    $title = 'KYC Status Updated';
                    $message = 'Your KYC verification status has been updated to: ' . $status;
            }
            
            $notificationStmt->execute([$userId, $_SESSION['admin_id'], $title, $message]);
            
            // Log admin action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_user_id, description, new_values, ip_address)
                VALUES (?, 'update_kyc', ?, 'Updated KYC status', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_id'],
                $userId,
                json_encode(['status' => $status, 'notes' => $notes]),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            echo json_encode(['success' => true, 'message' => 'KYC status updated successfully']);
            break;
            
        case 'update_document_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $documentId = intval($_POST['document_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (!$documentId || !in_array($status, ['pending', 'approved', 'rejected'])) {
                throw new Exception('Invalid document ID or status');
            }
            
            // Update document status
            $updateStmt = $pdo->prepare("
                UPDATE kyc_documents 
                SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$status, $_SESSION['admin_id'], $notes, $documentId]);
            
            // Get document details for logging
            $docStmt = $pdo->prepare("SELECT user_id, document_type FROM kyc_documents WHERE id = ?");
            $docStmt->execute([$documentId]);
            $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                // Log admin action
                $logStmt = $pdo->prepare("
                    INSERT INTO admin_logs (admin_id, action, target_user_id, description, new_values, ip_address)
                    VALUES (?, 'update_document', ?, 'Updated document status', ?, ?)
                ");
                $logStmt->execute([
                    $_SESSION['admin_id'],
                    $doc['user_id'],
                    json_encode(['document_type' => $doc['document_type'], 'status' => $status, 'notes' => $notes]),
                    $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Document status updated successfully']);
            break;
            
        case 'get_kyc_stats':
            // Get KYC statistics
            $stats = [];
            
            $statusCounts = $pdo->query("
                SELECT kyc_status, COUNT(*) as count 
                FROM users 
                WHERE user_type = 'user' 
                GROUP BY kyc_status
            ")->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $stats['pending'] = $statusCounts['pending'] ?? 0;
            $stats['submitted'] = $statusCounts['submitted'] ?? 0;
            $stats['approved'] = $statusCounts['approved'] ?? 0;
            $stats['rejected'] = $statusCounts['rejected'] ?? 0;
            $stats['total'] = array_sum($statusCounts);
            
            // Get documents needing review
            $pendingDocsStmt = $pdo->query("
                SELECT COUNT(*) FROM kyc_documents WHERE status = 'pending'
            ");
            $stats['pending_documents'] = $pendingDocsStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
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