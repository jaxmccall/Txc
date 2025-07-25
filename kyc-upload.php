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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $documentType = $_POST['document_type'] ?? '';
        $allowedTypes = ['selfie', 'id_front', 'id_back', 'id_file'];
        
        if (!in_array($documentType, $allowedTypes)) {
            throw new Exception('Invalid document type');
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }

        $file = $_FILES['document'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmpName = $file['tmp_name'];
        $mimeType = $file['type'];

        // Validate file size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            throw new Exception('File size too large. Maximum 5MB allowed.');
        }

        // Validate file type
        $allowedMimeTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new Exception('Invalid file type. Only images and PDF documents are allowed.');
        }

        // Create user directory
        $userDir = "uploads/kyc/{$userId}";
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        // Generate unique filename
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = $documentType . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $userDir . '/' . $uniqueFileName;

        // Move uploaded file
        if (!move_uploaded_file($fileTmpName, $filePath)) {
            throw new Exception('Failed to save file');
        }

        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO kyc_documents 
            (user_id, document_type, file_name, file_path, file_size, mime_type, upload_ip, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $uploadIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        
        $stmt->execute([
            $userId, 
            $documentType, 
            $fileName, 
            $filePath, 
            $fileSize, 
            $mimeType, 
            $uploadIp
        ]);

        // Update user KYC status if this is the first document
        $checkStmt = $pdo->prepare("SELECT kyc_status FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['kyc_status'] === 'pending') {
            $updateStmt = $pdo->prepare("UPDATE users SET kyc_status = 'submitted', kyc_submitted_at = NOW() WHERE id = ?");
            $updateStmt->execute([$userId]);
        }

        // Create notification for admin
        $notificationStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            SELECT id, 'New KYC Document Uploaded', CONCAT('User ', ?, ' has uploaded a ', ?, ' document for verification.'), 'admin'
            FROM users WHERE user_type IN ('admin', 'super_admin')
        ");
        $notificationStmt->execute([$_SESSION['username'], $documentType]);

        echo json_encode([
            'success' => true, 
            'message' => 'Document uploaded successfully',
            'document_id' => $pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>