<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Error: Invalid request method. Use POST.']);
    exit;
}

require 'login_config.php'; // Your DB + SMTP config

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get email from request
$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Generate OTP and expiry
$otp = rand(100000, 999999);
$expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// Store OTP in DB
$stmt = $mysqli->prepare("INSERT INTO login_otps (email, otp, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE otp=?, expires_at=?");
$stmt->bind_param('sssss', $email, $otp, $expires_at, $otp, $expires_at);
$stmt->execute();
$stmt->close();

// Send OTP via email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = 'tls';
    $mail->Port = SMTP_PORT;

    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your Login OTP';
    $mail->Body = "<p>Your Tripple Exchange login code is:</p><h2>$otp</h2><p>This code will expire in 5 minutes.</p>";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Email send error: ' . $mail->ErrorInfo]);
}
?>
