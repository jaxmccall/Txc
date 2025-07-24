<?php
header('Content-Type: application/json');
require 'db_config.php';

// Get input data
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';
$newPassword = $data['new_password'] ?? '';

// Validate input
if (empty($token) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token and password are required']);
    exit;
}

// Check token validity
$stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid reset link']);
    exit;
}

$tokenData = $result->fetch_assoc();

// Check if token is expired
if (strtotime($tokenData['expires_at']) < time()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Reset link has expired']);
    exit;
}

// Hash the new password
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Update user password
$updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$updateStmt->bind_param("ss", $hashedPassword, $tokenData['email']);

if ($updateStmt->execute()) {
    // Delete the used token
    $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
    $deleteStmt->bind_param("s", $token);
    $deleteStmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
}
?>