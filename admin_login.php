<?php
// --- Error logger ---
function log_admin_login_error($error_message) {
    $log_file = __DIR__ . '/admin_login_errors.log';
    $date = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $log_entry = "[$date] [IP: $ip] [UA: $ua] $error_message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// --- Set up error handling to log all PHP errors ---
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    log_admin_login_error("PHP Error [$errno] in $errfile at line $errline: $errstr");
    return false;
});
set_exception_handler(function($exception) {
    log_admin_login_error("Uncaught Exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
});

// --- Always send JSON ---
function respond($data, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// --- DB Connection (PDO) ---
require_once __DIR__ . '/config.php'; // This must define $pdo (PDO instance)

// --- Start session ---
session_start();

// --- Debug: Log full session state (for troubleshooting) ---
log_admin_login_error("DEBUG SESSION (admin_login.php): " . print_r($_SESSION, true));

// --- Only accept POST requests with JSON content ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_admin_login_error("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    log_admin_login_error("Invalid content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none'));
    respond(['success' => false, 'message' => 'Invalid content type.'], 415);
}

// --- Parse input ---
$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (!$username || !$password) {
    log_admin_login_error("Missing username or password: username=[$username], password length=[" . strlen($password) . "]");
    respond(['success' => false, 'message' => 'Username and password are required.'], 422);
}

try {
    // Table must be named 'admins', adjust if your table is different
    $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        log_admin_login_error("Login failed: Username not found [$username]");
        respond(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }
    if (!password_verify($password, $admin['password'])) {
        log_admin_login_error("Login failed: Password incorrect for [$username]");
        respond(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }

    // --- Regenerate session ID and set session variables ---
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['LAST_LOGIN'] = date('Y-m-d H:i:s');
    $_SESSION['LAST_ACTIVITY'] = time();

    // --- Debug: Log full session state after login ---
    log_admin_login_error("DEBUG SESSION AFTER LOGIN (admin_login.php): " . print_r($_SESSION, true));
    log_admin_login_error("Login successful for [$username]");

    respond([
        'success' => true,
        'message' => 'Login successful.',
        'redirect' => 'admin-dashboard.html' // Redirects to admin-dashboard.html
    ]);
} catch (Exception $e) {
    log_admin_login_error("Exception during login: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Server error. Please try again later.'], 500);
}
?>