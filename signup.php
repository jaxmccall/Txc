<?php
// Error logger function
function log_signup_error($error_message) {
    $log_file = __DIR__ . '/signup_errors.log';
    $date = date('Y-m-d H:i:s');
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $log_entry = "[$date] [IP: $client_ip] [UA: $user_agent] $error_message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

require_once 'config.php'; // This sets $pdo
session_start();

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check database connection
if (!isset($pdo) || !$pdo) {
    log_signup_error("Database connection error: Unknown");
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['form' => 'Database connection error.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $referral = trim($_POST['referralCode'] ?? '');

    $errors = [];

    // Username validation
    if (empty($username)) {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors['username'] = '3-30 chars, letters, numbers, underscores only.';
    }

    // Email validation
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email address.';
    }

    // Password validation
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/\d/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        $errors['password'] = 'Min 8 chars, 1 uppercase, 1 number, 1 special character.';
    }

    // Terms validation
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'You must accept the terms and conditions.';
    }

    if ($errors) {
        log_signup_error("Validation failed: " . json_encode($errors));
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // Check for existing username/email
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email
        ]);
        if ($stmt->fetch()) {
            log_signup_error("Duplicate username/email: $username / $email");
            http_response_code(409);
            echo json_encode(['success' => false, 'errors' => ['username' => 'Username or email already in use.']]);
            exit;
        }
    } catch (PDOException $e) {
        log_signup_error("SELECT error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => ['form' => 'Database error (SELECT).']]);
        exit;
    }

    // Capture IP and User-Agent
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // GeoIP lookup
    $country = $region = $city = $lat = $lon = $isp = null;
    if ($ip) {
        $geo = @json_decode(@file_get_contents("http://ip-api.com/json/$ip"), true);
        if ($geo && $geo['status'] === 'success') {
            $country = $geo['country'];
            $region = $geo['regionName'];
            $city = $geo['city'];
            $lat = $geo['lat'];
            $lon = $geo['lon'];
            $isp = $geo['isp'];
        }
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, signup_ip, signup_user_agent, signup_country, signup_region, signup_city, signup_lat, signup_lon, signup_isp, referral_code)
        VALUES (:username, :email, :password, :ip, :ua, :country, :region, :city, :lat, :lon, :isp, :referral)");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hash,
            ':ip' => $ip,
            ':ua' => $userAgent,
            ':country' => $country,
            ':region' => $region,
            ':city' => $city,
            ':lat' => $lat,
            ':lon' => $lon,
            ':isp' => $isp,
            ':referral' => $referral
        ]);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['last_activity'] = time();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        log_signup_error("INSERT error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => ['form' => 'Failed to create user.']]);
    }
}
?>