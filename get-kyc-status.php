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
    // Get user KYC status
    $userStmt = $pdo->prepare("SELECT kyc_status, kyc_submitted_at, kyc_reviewed_at FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Get uploaded documents
    $docsStmt = $pdo->prepare("
        SELECT id, document_type, file_name, file_size, status, created_at, reviewed_at, admin_notes
        FROM kyc_documents 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $docsStmt->execute([$userId]);
    $documents = $docsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'status' => $user['kyc_status'],
        'submitted_at' => $user['kyc_submitted_at'],
        'reviewed_at' => $user['kyc_reviewed_at'],
        'documents' => $documents
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>