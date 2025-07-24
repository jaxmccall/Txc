<?php
require_once 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
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

    // Terms validation (client-side only, but check if present)
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'You must accept the terms and conditions.';
    }

    if ($errors) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // Check for existing username/email
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        // Log existing user attempt  
        error_log("SIGNUP FAILED: Username or email already exists - username: $username, email: $email from IP: $ip");
        
        http_response_code(409);
        echo json_encode(['success' => false, 'errors' => ['username' => 'Username or email already in use.']]);
        exit;
    }
    $stmt->close();

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
    $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, signup_ip, signup_user_agent, signup_country, signup_region, signup_city, signup_lat, signup_lon, signup_isp, referral_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssssddss",
        $username,
        $email,
        $hash,
        $ip,
        $userAgent,
        $country,
        $region,
        $city,
        $lat,
        $lon,
        $isp,
        $referral
    );
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['last_activity'] = time(); // Set initial activity time for auto-logout
        
        // Log successful signup
        error_log("SIGNUP SUCCESS: User $username (ID: $user_id) registered with email: $email from IP: $ip");
        
        echo json_encode(['success' => true]);
    } else {
        // Log failed signup attempt
        error_log("SIGNUP FAILED: Database error for username: $username, email: $email from IP: $ip - Error: " . $mysqli->error);
        
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => ['form' => 'Failed to create user.']]);
    }
    $stmt->close();
}
?>