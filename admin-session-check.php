<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== 1) {
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
    exit();
}

// Return success with admin info
echo json_encode([
    'success' => true,
    'authenticated' => true,
    'admin' => [
        'id' => $_SESSION['admin_id'] ?? 0,
        'username' => $_SESSION['admin_username'] ?? 'Admin',
        'email' => '', // Add email if available in session
    ]
]);
?>
