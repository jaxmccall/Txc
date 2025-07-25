<?php
session_start();
require_once 'config.php';

// Always return JSON response
header('Content-Type: application/json');

// Helper: send error and exit
function send_error($errors) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }

    // If no errors, verify credentials
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, user_type, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Check if account is active
                if (!$user['is_active']) {
                    $errors['email'] = 'Account is disabled. Please contact support.';
                } elseif (password_verify($password, $user['password'])) {
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_activity = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $email;
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['last_activity'] = time();

                    // Redirect to appropriate dashboard
                    $redirectUrl = 'dashboard.html';
                    if ($user['user_type'] === 'admin' || $user['user_type'] === 'super_admin') {
                        $redirectUrl = 'admin-dashboard.html';
                    }

                    echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
                    exit();
                } else {
                    $errors['password'] = 'Incorrect password';
                }
            } else {
                $errors['email'] = 'Email not found';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $errors['general'] = 'Login failed. Please try again.';
        }
    }

    send_error($errors);
} else {
    send_error(['general' => 'Invalid request']);
}
?>