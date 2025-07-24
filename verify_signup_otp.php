<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require 'login_config.php';

header('Content-Type: application/json');

// Get POST data (support both JSON and form data)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $otp = $data['otp'] ?? '';
} else {
    $email = $_POST['email'] ?? '';
    $otp = $_POST['otp'] ?? '';
}

// Input validation
if (empty($email) || empty($otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($otp) !== 6 || !ctype_digit($otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format. Must be 6 digits']);
    exit;
}

try {
    // Check OTP
    $stmt = $mysqli->prepare("
        SELECT id FROM signup_otps 
        WHERE email = ? AND otp = ? AND expires_at > NOW() AND used = 0 
        LIMIT 1
    ");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        // Mark OTP as used
        $stmt = $mysqli->prepare("UPDATE signup_otps SET used = 1 WHERE id = ?");
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();

        // Store verification in session
        $_SESSION['signup_verified'] = true;
        $_SESSION['signup_email'] = $email;
        $_SESSION['signup_time'] = time();

        echo json_encode([
            'success' => true,
            'message' => 'OTP verified successfully'
        ]);
    } else {
        // Record failed attempt
        $stmt = $mysqli->prepare("
            INSERT INTO failed_login_attempts (email, ip_address, attempt_time)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ss", $email, $_SERVER['REMOTE_ADDR']);
        $stmt->execute();

        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired OTP'
        ]);
    }
} catch (mysqli_sql_exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
?>