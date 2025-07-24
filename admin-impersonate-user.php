<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    if ($pdo) {
        // Get user details
        $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, is_verified, is_active FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        if (!$user['is_active']) {
            echo json_encode(['success' => false, 'message' => 'Cannot impersonate inactive user']);
            exit;
        }
        
        // Store current admin session data before impersonation
        $_SESSION['admin_original_session'] = [
            'admin_logged_in' => $_SESSION['admin_logged_in'],
            'admin_id' => $_SESSION['admin_id'],
            'admin_username' => $_SESSION['admin_username'],
            'admin_name' => $_SESSION['admin_name'],
            'is_master' => $_SESSION['is_master'] ?? false,
            'is_super_admin' => $_SESSION['is_super_admin'] ?? false
        ];
        
        // Set user session data for impersonation
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'] ?? $user['email'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['is_verified'] = $user['is_verified'];
        $_SESSION['logged_in'] = true;
        
        // Mark as impersonation session
        $_SESSION['is_impersonating'] = true;
        $_SESSION['impersonated_user_id'] = $user['id'];
        $_SESSION['impersonated_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        echo json_encode([
            'success' => true, 
            'message' => 'Impersonation started',
            'user' => [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email']
            ]
        ]);
    } else {
        // Mock response for testing
        echo json_encode([
            'success' => true, 
            'message' => 'Impersonation started (mock mode)',
            'user' => [
                'id' => $user_id,
                'name' => 'Test User',
                'email' => 'test@example.com'
            ]
        ]);
    }
} catch (Exception $e) {
    error_log("Admin impersonation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>