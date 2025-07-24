<?php
session_start();
ini_set('display_errors', 0); // Disable error display in production
ini_set('log_errors', 1);
error_reporting(E_ALL);

require 'login_config.php';

$response = ['success' => false, 'message' => ''];

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Validate input
    if (empty($_POST['email']) || empty($_POST['otp'])) {
        throw new Exception("Email and OTP are required");
    }

    $email = $mysqli->real_escape_string($_POST['email']);
    $otp = preg_replace('/[^0-9]/', '', $_POST['otp']);

    // Verify OTP
    $stmt = $mysqli->prepare("SELECT id FROM otp_verification 
                            WHERE email = ? AND otp = ? 
                            AND expires_at > NOW() AND used = 0");
    if (!$stmt) {
        throw new Exception("Database error: " . $mysqli->error);
    }

    $stmt->bind_param("ss", $email, $otp);
    if (!$stmt->execute()) {
        throw new Exception("Verification failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Mark OTP as used
        $row = $result->fetch_assoc();
        $update = $mysqli->prepare("UPDATE otp_verification SET used = 1 WHERE id = ?");
        $update->bind_param("i", $row['id']);
        $update->execute();
        
        // Set session variables
        $_SESSION['otp_verified'] = true;
        $_SESSION['otp_email'] = $email;
        $_SESSION['last_activity'] = time();
        
        $response['success'] = true;
        $response['redirect'] = 'login.php'; // Your login page
        $response['message'] = 'Verification successful';
    } else {
        throw new Exception("Invalid or expired OTP");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("OTP Verification Error: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response);
?>