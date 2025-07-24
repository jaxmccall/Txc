<?php
session_start();
require 'login_config.php'; // Your database config

header('Content-Type: application/json');

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$now = time();

// Check failed attempts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM failed_login_attempts 
                       WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$stmt->execute([$ip]);
$failedAttempts = $stmt->fetchColumn();

if ($failedAttempts >= 5) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many attempts. Please wait 1 minute.'
    ]);
    exit;
}

try {
    // Check if OTP was verified
    if (!isset($_SESSION['signup_verified']) || $_SESSION['signup_verified'] !== true) {
        throw new Exception("OTP verification required");
    }

    // Validate inputs
    $required = ['username', 'email', 'password', 'confirmPassword', 'terms'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Verify email matches the one that was OTP verified
    if ($_POST['email'] !== $_SESSION['signup_email']) {
        throw new Exception("Email verification mismatch");
    }

    // Check password match
    if ($_POST['password'] !== $_POST['confirmPassword']) {
        throw new Exception("Passwords don't match");
    }

    // Check terms agreement
    if ($_POST['terms'] !== 'on') {
        throw new Exception("You must agree to the terms");
    }

    // Additional password validation
    if (strlen($_POST['password']) < 8) {
        throw new Exception("Password must be at least 8 characters");
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        throw new Exception("Email already registered");
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    if ($stmt->fetch()) {
        throw new Exception("Username already taken");
    }

    // Hash password
    $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, phone, created_at) 
                          VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $_POST['username'],
        $_POST['email'],
        $passwordHash,
        $_POST['phone'] ?? null
    ]);

    // Create session
    $session_id = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $stmt = $pdo->prepare("INSERT INTO sessions (session_id, user_id, expires_at)
                          VALUES (?, LAST_INSERT_ID(), ?)");
    $stmt->execute([$session_id, $expires_at]);

    // Clear verification session
    unset($_SESSION['signup_verified']);
    unset($_SESSION['signup_email']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Account created successfully',
        'session_id' => $session_id
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Signup error: " . $e->getMessage());
    
    // Record failed attempt
    $stmt = $pdo->prepare("INSERT INTO failed_login_attempts (email, ip_address, attempt_time)
                          VALUES (?, ?, NOW())");
    $stmt->execute([$_POST['email'] ?? '', $ip]);

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    // Close database connections
    $stmt = null;
    $pdo = null;
    $mysqli = null;
}
?>