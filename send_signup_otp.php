<?php
// Start output buffering and enable error logging
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('UTC');

// Debug log file path
$log_file = __DIR__ . '/email_debug.log';

// Initialize debug log
$debug_log = [];
$debug_log[] = str_repeat('=', 80);
$debug_log[] = '[' . date('Y-m-d H:i:s') . '] Starting OTP process';

// Function to write to log file
function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Function to send JSON response and exit
function sendResponse($success, $message, $debug = []) {
    global $debug_log;
    
    // Add message to debug log
    $debug_log[] = $message;
    
    // Prepare response
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => time()
    ];
    
    // Only include debug info in development
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $response['debug'] = $debug;
    }
    
    // Log the full debug info
    writeLog(implode("\n", $debug_log));
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Main execution
try {
    // Log initial request info
    $debug_log[] = 'Server: ' . php_uname();
    $debug_log[] = 'PHP Version: ' . phpversion();
    $debug_log[] = 'Request Method: ' . $_SERVER['REQUEST_METHOD'];
    $debug_log[] = 'Content Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set');
    $debug_log[] = 'Remote IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Use POST.');
    }

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $username = trim($input['username'] ?? '');

    if (!$email) {
        throw new Exception('Invalid email address');
    }
    if (empty($username)) {
        throw new Exception('Username is required');
    }

    $debug_log[] = "Processing request for email: $email, username: $username";

    // Load configuration
    if (!file_exists('login_config.php')) {
        throw new Exception('Configuration file not found');
    }
    require 'login_config.php';

    // Verify required constants
    $required_constants = ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_PORT', 'FROM_EMAIL', 'FROM_NAME'];
    $missing = array_filter($required_constants, function($c) { return !defined($c); });
    if (!empty($missing)) {
        throw new Exception('Missing required configuration: ' . implode(', ', $missing));
    }

    // Generate OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $debug_log[] = "Generated OTP: $otp (expires: $expires)";

    // Store OTP in database
    $stmt = $mysqli->prepare("INSERT INTO signup_otps (email, otp, expires_at) VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE otp = VALUES(otp), expires_at = VALUES(expires_at)");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('sss', $email, $otp, $expires);
    if (!$stmt->execute()) {
        throw new Exception('Database execute failed: ' . $stmt->error);
    }
    $stmt->close();

    // Load PHPMailer
    $phpmailer_files = [
        __DIR__ . '/PHPMailer/src/PHPMailer.php',
        __DIR__ . '/PHPMailer/src/SMTP.php',
        __DIR__ . '/PHPMailer/src/Exception.php'
    ];
    foreach ($phpmailer_files as $file) {
        if (!file_exists($file)) {
            throw new Exception("PHPMailer file not found: $file");
        }
        require_once $file;
    }

    // Create and configure PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = 'tls';
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) use (&$debug_log) {
        $debug_log[] = "SMTP: $str";
    };

    // Email content
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($email, $username);
    $mail->isHTML(true);
    $mail->Subject = 'Your Verification Code';
    
    $mail->Body = "
        <h2>Your Verification Code</h2>
        <p>Hello " . htmlspecialchars($username) . ",</p>
        <p>Your verification code is: <strong>$otp</strong></p>
        <p>This code will expire in 5 minutes.</p>
        <p>If you didn't request this, please ignore this email.</p>
    ";
    
    $mail->AltBody = "Your verification code is: $otp\n\nThis code will expire in 5 minutes.";

    // Send email
    if ($mail->send()) {
        $debug_log[] = 'Email sent successfully';
        sendResponse(true, 'Verification code sent successfully');
    } else {
        throw new Exception('Failed to send email: ' . $mail->ErrorInfo);
    }

} catch (Exception $e) {
    $error = $e->getMessage();
    $debug_log[] = "ERROR: $error";
    $debug_log[] = "Stack trace: " . $e->getTraceAsString();
    sendResponse(false, 'An error occurred while sending the verification code');
} finally {
    // Clean up
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($mail) && $mail instanceof PHPMailer\PHPMailer\PHPMailer) {
        $mail->smtpClose();
    }
    // Write final log
    writeLog(implode("\n", $debug_log));
}