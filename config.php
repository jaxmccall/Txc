<?php
// ===============================
// CONFIGURATION
// ===============================

// --- Database credentials ---
define('DB_HOST', 'localhost');
define('DB_USER', 'u925878138_admin');
define('DB_PASS', 'Chills@1008!!');
define('DB_NAME', 'u925878138_tripplex');

// --- SMTP Configuration ---
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'support@trippleexchange.com');
define('SMTP_PASSWORD', 'AAaa112233!!!');
define('FROM_EMAIL', 'support@trippleexchange.com');
define('FROM_NAME', 'Tripple Exchange');

// --- Session Cookie Settings (set BEFORE session_start) ---
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
session_set_cookie_params([
    'lifetime' => 0, // Session cookie (until browser close)
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => $https,       // Only send cookie over HTTPS
    'httponly' => true,         // JS cannot access cookies
    'samesite' => 'Lax'         // Or 'Strict'
]);

// --- Start session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database connection (mysqli) ---
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    die("Database connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// Optional: Set default timezone
// date_default_timezone_set('UTC');

?>