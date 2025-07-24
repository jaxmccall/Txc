<?php
// File: api/admin-edit-user.php
header('Content-Type: application/json');
require_once '../config.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$user_id = intval($input['id'] ?? 0);
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$status = $input['status'] ?? 'active';
$kyc_status = $input['kyc_status'] ?? 'pending';
$credit_score = intval($input['credit_score'] ?? 100);

if (!$user_id || !$name || !$email) {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
    exit;
}

// Validate credit score
if ($credit_score < 0 || $credit_score > 100) {
    echo json_encode(['success' => false, 'message' => 'Credit score must be between 0 and 100']);
    exit;
}

// Validate status values
if (!in_array($status, ['active', 'frozen'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid account status']);
    exit;
}

if (!in_array($kyc_status, ['pending', 'approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid KYC status']);
    exit;
}

try {
    // Split name into first_name and last_name
    $name_parts = explode(' ', $name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';
    
    // Update user information
    $stmt = $pdo->prepare("
        UPDATE users SET 
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            is_active = :is_active,
            kyc_status = :kyc_status,
            account_score = :account_score,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :user_id
    ");
    
    $stmt->execute([
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':is_active' => $status === 'active' ? 1 : 0,
        ':kyc_status' => $kyc_status,
        ':account_score' => $credit_score,
        ':user_id' => $user_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in admin-edit-user.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>