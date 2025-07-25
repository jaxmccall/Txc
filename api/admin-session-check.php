<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

// Return admin info if authenticated
echo json_encode([
    'success' => true,
    'authenticated' => true,
    'admin' => [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? 'Admin',
        'name' => $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin',
        'email' => $_SESSION['admin_email'] ?? '',
        'is_master' => $_SESSION['is_master'] ?? false
    ]
]);
?>