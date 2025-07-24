<?php
session_start();
require_once 'config.php';

// Always return JSON response
header('Content-Type: application/json');

// Error logging function
function log_error($message, $context = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    error_log('[LOGIN_ERROR] ' . json_encode($log_entry));
}

// Helper: send error and exit
function send_error($errors, $message = null) {
    $response = ['success' => false, 'errors' => $errors];
    if ($message) {
        $response['message'] = $message;
    }
    echo json_encode($response);
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
            // Check database connection status
            if ($mysqli->connect_errno) {
                log_error('Database connection failed', [
                    'error' => $mysqli->connect_error,
                    'errno' => $mysqli->connect_errno
                ]);
                send_error(['general' => 'Database connection error. Please try again later.'], 'Database connection failed');
            }

            $stmt = $mysqli->prepare("SELECT id, username, password FROM users WHERE email = ?");
            if (!$stmt) {
                log_error('Failed to prepare SQL statement', ['error' => $mysqli->error]);
                send_error(['general' => 'Database error. Please try again later.'], 'SQL prepare failed');
            }

            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $email;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['last_activity'] = time(); // Set initial activity time for auto-logout

                    // Log successful login
                    error_log('[LOGIN_SUCCESS] User logged in: ' . json_encode([
                        'user_id' => $user['id'],
                        'email' => $email,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]));

                    echo json_encode(['success' => true, 'message' => 'Login successful']);
                    exit();
                } else {
                    log_error('Invalid password attempt', [
                        'email' => $email,
                        'user_id' => $user['id']
                    ]);
                    $errors['password'] = 'Incorrect password';
                }
            } else {
                log_error('User not found', ['email' => $email]);
                $errors['email'] = 'Email not found';
            }
            $stmt->close();
        } catch (Exception $e) {
            log_error('Login exception occurred', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            send_error(['general' => 'An unexpected error occurred. Please try again later.'], 'Exception during login');
        }
    }

    send_error($errors, 'Login validation failed');
} else {
    log_error('Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
    send_error(['general' => 'Invalid request method'], 'Invalid request');
}
?>