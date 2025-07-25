<?php
// Simple test session setup for development testing
session_start();

// Set up a test user session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['email'] = 'testuser@trippleexchange.com';
$_SESSION['logged_in'] = true;
$_SESSION['last_activity'] = time();

echo json_encode([
    'success' => true,
    'message' => 'Test session created',
    'user' => [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email']
    ]
]);
?>