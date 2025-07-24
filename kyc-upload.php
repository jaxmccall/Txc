<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
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

$user_id = $_SESSION['user_id'] ?? null;
$upload_type = $_POST['upload_type'] ?? null; // 'selfie', 'id_document', 'id_screenshot'

if (!$user_id || !$upload_type) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate upload type
$allowed_types = ['selfie', 'id_document', 'id_screenshot'];
if (!in_array($upload_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid upload type']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

$file = $_FILES['file'];

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Validate file type
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
$file_info = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($file_info, $file['tmp_name']);
finfo_close($file_info);

if (!in_array($mime_type, $allowed_mime_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and PDF files are allowed']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = "uploads/kyc/{$user_id}/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $upload_type . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$file_path = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    if ($pdo) {
        // Create KYC uploads table if it doesn't exist
        $create_table_sql = "
        CREATE TABLE IF NOT EXISTS kyc_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            upload_type ENUM('selfie', 'id_document', 'id_screenshot') NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            admin_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_upload_type (upload_type),
            INDEX idx_status (status)
        )";
        
        $pdo->exec($create_table_sql);
        
        // Remove any existing upload of the same type for this user
        $stmt = $pdo->prepare("DELETE FROM kyc_uploads WHERE user_id = ? AND upload_type = ?");
        $stmt->execute([$user_id, $upload_type]);
        
        // Insert new upload record
        $stmt = $pdo->prepare("
            INSERT INTO kyc_uploads (user_id, upload_type, filename, original_filename, file_path, mime_type, file_size) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $upload_type,
            $filename,
            $file['name'],
            $file_path,
            $mime_type,
            $file['size']
        ]);
        
        // Update user KYC status to 'submitted' if all required uploads are present
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT upload_type) as upload_count 
            FROM kyc_uploads 
            WHERE user_id = ? AND upload_type IN ('selfie', 'id_document')
        ");
        $stmt->execute([$user_id]);
        $upload_count = $stmt->fetchColumn();
        
        if ($upload_count >= 2) {
            $stmt = $pdo->prepare("UPDATE users SET kyc_status = 'submitted' WHERE id = ?");
            $stmt->execute([$user_id]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'data' => [
            'upload_type' => $upload_type,
            'filename' => $filename,
            'original_filename' => $file['name'],
            'size' => $file['size']
        ]
    ]);
    
} catch (Exception $e) {
    // Remove uploaded file if database operation failed
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    error_log("KYC upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>