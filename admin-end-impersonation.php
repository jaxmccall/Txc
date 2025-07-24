<?php
session_start();

header('Content-Type: application/json');

// Check if currently impersonating
if (!isset($_SESSION['is_impersonating']) || !$_SESSION['is_impersonating']) {
    echo json_encode(['success' => false, 'message' => 'Not currently impersonating']);
    exit;
}

// Restore original admin session
if (isset($_SESSION['admin_original_session'])) {
    $originalSession = $_SESSION['admin_original_session'];
    
    // Clear user session data
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['email']);
    unset($_SESSION['first_name']);
    unset($_SESSION['last_name']);
    unset($_SESSION['is_verified']);
    unset($_SESSION['logged_in']);
    
    // Clear impersonation data
    unset($_SESSION['is_impersonating']);
    unset($_SESSION['impersonated_user_id']);
    unset($_SESSION['impersonated_user_name']);
    unset($_SESSION['admin_original_session']);
    
    // Restore admin session
    $_SESSION['admin_logged_in'] = $originalSession['admin_logged_in'];
    $_SESSION['admin_id'] = $originalSession['admin_id'];
    $_SESSION['admin_username'] = $originalSession['admin_username'];
    $_SESSION['admin_name'] = $originalSession['admin_name'];
    $_SESSION['is_master'] = $originalSession['is_master'];
    $_SESSION['is_super_admin'] = $originalSession['is_super_admin'];
    
    echo json_encode(['success' => true, 'message' => 'Impersonation ended']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not restore admin session']);
}
?>