<?php
session_start();
require_once 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? '');
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

    // Name validation
    if (empty($firstName)) {
        $errors['firstName'] = 'First name is required.';
    }
    if (empty($lastName)) {
        $errors['lastName'] = 'Last name is required.';
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

    try {
        // Check for existing username/email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'errors' => ['username' => 'Username or email already in use.']]);
            exit;
        }

        // Capture IP and User-Agent
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // GeoIP lookup
        $signupCountry = $region = $city = $lat = $lon = $isp = null;
        if ($ip && $ip !== 'unknown') {
            $geo = @json_decode(@file_get_contents("http://ip-api.com/json/$ip"), true);
            if ($geo && $geo['status'] === 'success') {
                $signupCountry = $geo['country'];
                $region = $geo['regionName'];
                $city = $geo['city'];
                $lat = $geo['lat'];
                $lon = $geo['lon'];
                $isp = $geo['isp'];
            }
        }

        // Hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user with account_score = 100 and balance = 0 (default)
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, email, password, first_name, last_name, phone, country,
                signup_ip, signup_user_agent, signup_country, signup_region, 
                signup_city, signup_lat, signup_lon, signup_isp, referral_code,
                account_score, user_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 100, 'user')
        ");
        
        $stmt->execute([
            $username, $email, $hash, $firstName, $lastName, $phone, $country,
            $ip, $userAgent, $signupCountry, $region, $city, $lat, $lon, $isp, $referral
        ]);
        
        $userId = $pdo->lastInsertId();

        // Initialize user balances for all supported assets (starting at 0)
        $assets = ['USDT', 'BTC', 'ETH', 'BNB', 'ADA', 'XRP', 'SOL', 'DOT', 'DOGE', 'LTC'];
        $balanceStmt = $pdo->prepare("INSERT INTO user_balances (user_id, asset_symbol, balance) VALUES (?, ?, 0.00000000)");
        
        foreach ($assets as $asset) {
            $balanceStmt->execute([$userId, $asset]);
        }

        // Create welcome notification
        $notificationStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, 'Welcome to Tripple Exchange!', 'Your account has been successfully created. Complete your KYC verification to start trading.', 'success')
        ");
        $notificationStmt->execute([$userId]);

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_type'] = 'user';
        $_SESSION['last_activity'] = time();

        echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
        
    } catch (PDOException $e) {
        error_log("Signup error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => ['form' => 'Failed to create account. Please try again.']]);
    }
}
?>