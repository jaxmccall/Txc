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
        $stmt = $mysqli->prepare("SELECT id, username, password FROM users WHERE email = ?");
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

                echo json_encode(['success' => true]);
                exit();
            } else {
                $errors['password'] = 'Incorrect password';
            }
        } else {
            $errors['email'] = 'Email not found';
        }
        $stmt->close();
    }

    send_error($errors);
} else {
    send_error(['general' => 'Invalid request']);
}
?>