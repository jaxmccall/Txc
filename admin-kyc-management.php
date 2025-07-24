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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all pending KYC uploads
    try {
        if ($pdo) {
            $stmt = $pdo->prepare("
                SELECT 
                    ku.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.kyc_status as user_kyc_status
                FROM kyc_uploads ku
                JOIN users u ON ku.user_id = u.id
                ORDER BY ku.created_at DESC
            ");
            $stmt->execute();
            $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'uploads' => $uploads]);
        } else {
            // Mock data for testing
            echo json_encode([
                'success' => true,
                'uploads' => [
                    [
                        'id' => 1,
                        'user_id' => 1,
                        'upload_type' => 'selfie',
                        'filename' => 'selfie_1234567890.jpg',
                        'original_filename' => 'my_selfie.jpg',
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s'),
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john@example.com'
                    ]
                ]
            ]);
        }
    } catch (Exception $e) {
        error_log("Admin KYC view error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update KYC upload status
    $input = json_decode(file_get_contents('php://input'), true);
    $upload_id = $input['upload_id'] ?? null;
    $status = $input['status'] ?? null;
    $admin_notes = $input['admin_notes'] ?? '';
    
    if (!$upload_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    if (!in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    try {
        if ($pdo) {
            // Update upload status
            $stmt = $pdo->prepare("
                UPDATE kyc_uploads 
                SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$status, $admin_notes, $upload_id]);
            
            // Get user_id for this upload
            $stmt = $pdo->prepare("SELECT user_id FROM kyc_uploads WHERE id = ?");
            $stmt->execute([$upload_id]);
            $user_id = $stmt->fetchColumn();
            
            if ($user_id) {
                // Check if all uploads for this user are approved
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_uploads,
                           SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_uploads
                    FROM kyc_uploads 
                    WHERE user_id = ? AND upload_type IN ('selfie', 'id_document')
                ");
                $stmt->execute([$user_id]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update user KYC status based on upload statuses
                if ($counts['total_uploads'] >= 2 && $counts['approved_uploads'] >= 2) {
                    $stmt = $pdo->prepare("UPDATE users SET kyc_status = 'approved', is_verified = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Send notification to user
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, 
                        'KYC Verification Approved', 
                        'Your identity verification has been approved. Your account is now fully verified.',
                        'success'
                    ]);
                } elseif ($status === 'rejected') {
                    $stmt = $pdo->prepare("UPDATE users SET kyc_status = 'rejected' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Send notification to user
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, 
                        'KYC Verification Rejected', 
                        'Your identity verification has been rejected. Please resubmit your documents. Reason: ' . $admin_notes,
                        'warning'
                    ]);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'KYC status updated successfully']);
        } else {
            // Mock response for testing
            echo json_encode(['success' => true, 'message' => 'KYC status updated successfully (mock mode)']);
        }
    } catch (Exception $e) {
        error_log("Admin KYC update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>