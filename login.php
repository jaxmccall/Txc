<?php
session_start();
require_once 'config.php';

// Always return JSON response
header('Content-Type: application/json');

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/login_errors.log');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Helper: send error and exit
function send_error($errors, $debug_info = null) {
    $response = ['success' => false, 'errors' => $errors];
    
    // Log debug information for troubleshooting
    if ($debug_info) {
        error_log("Login Error Debug: " . json_encode($debug_info));
    }
    
    echo json_encode($response);
    exit();
}

// Helper: send success response
function send_success($message = 'Login successful', $data = null) {
    $response = ['success' => true, 'message' => $message];
    if ($data) {
        $response['data'] = $data;
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
            $stmt = $mysqli->prepare("SELECT id, username, password FROM users WHERE email = ?");
            if (!$stmt) {
                error_log("Database prepare error: " . $mysqli->error);
                send_error(['general' => 'Database error occurred'], ['mysql_error' => $mysqli->error]);
            }

            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Successful login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $email;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['last_activity'] = time(); // Set initial activity time for auto-logout

                    // Log successful login
                    error_log("Successful login for user: " . $email);
                    
                    send_success();
                } else {
                    // Log failed password attempt
                    error_log("Failed password attempt for user: " . $email);
                    $errors['password'] = 'Incorrect password';
                }
            } else {
                // Log failed email attempt
                error_log("Login attempt with non-existent email: " . $email);
                $errors['email'] = 'Email not found';
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Login exception: " . $e->getMessage());
            send_error(['general' => 'An error occurred during login. Please try again.'], ['exception' => $e->getMessage()]);
        }
    }

    // Send validation errors
    if (!empty($errors)) {
        send_error($errors);
    }
} else {
    send_error(['general' => 'Invalid request method'], ['method' => $_SERVER['REQUEST_METHOD']]);
}
?>