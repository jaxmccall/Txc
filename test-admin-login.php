<?php
// Simple test admin login without database for testing purposes
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

// Simple test credentials
if ($username === 'admin' && $password === 'admin123') {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_logged_in'] = 1;
    $_SESSION['LAST_LOGIN'] = date('Y-m-d H:i:s');
    $_SESSION['LAST_ACTIVITY'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'redirect' => 'enhanced-admin-panel.html'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials.'
    ]);
}
?>