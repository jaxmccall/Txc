<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success' => false, 'logged_in' => false]);
    exit;
}

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'admin_id' => $_SESSION['admin_id'],
    'admin_username' => $_SESSION['admin_username'],
    'admin_name' => $_SESSION['admin_name'],
    'user_type' => $_SESSION['user_type'] ?? 'admin',
    'is_super_admin' => $_SESSION['is_super_admin'] ?? false
]);
?>