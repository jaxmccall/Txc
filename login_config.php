<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'tripple_exchange');
define('DB_USER', 'db_user');
define('DB_PASS', 'your_secure_password');

// SMTP Configuration
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'support@trippleexchange.com');
define('SMTP_PASSWORD', 'your_smtp_password');
define('SMTP_FROM_EMAIL', 'support@trippleexchange.com');
define('SMTP_FROM_NAME', 'Tripple Exchange Support');

// Application settings
define('SITE_NAME', 'Tripple Exchange');
define('SITE_URL', 'https://trippleexchange.com');
define('ADMIN_EMAIL', 'admin@trippleexchange.com');

// Security settings
define('PEPPER', 'your_pepper_string_here'); // Generate a random string
define('TOKEN_EXPIRY', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes in seconds

// Session settings
session_start([
    'cookie_lifetime' => 86400, // 24 hours
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true,
    'cookie_samesite' => 'Lax'
]);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Timezone
date_default_timezone_set('UTC');

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}