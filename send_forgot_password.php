<?php
header('Content-Type: application/json');
require 'db_config.php';

$email = $_POST['email'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'If this email exists, a reset link has been sent']);
    exit;
}

// Delete any existing tokens for this email
$conn->query("DELETE FROM password_resets WHERE email = '$email'");

// Generate token
$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Store token
$stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $token, $expires_at);

if ($stmt->execute()) {
    // Send email (in production, use a proper mailer)
    $resetLink = "https://trippleexchange.com/reset-password.html?token=$token";
    
    // This is a simplified email send - use PHPMailer in production
    $subject = "Tripple Exchange Password Reset";
    $message = "Click this link to reset your password: $resetLink";
    $headers = "From: no-reply@trippleexchange.com";
    
    mail($email, $subject, $message, $headers);
    
    echo json_encode(['success' => true, 'message' => 'Reset link sent if email exists']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to generate reset link']);
}
?>