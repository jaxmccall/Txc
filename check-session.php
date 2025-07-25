<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database configuration
require_once 'config.php';

try {
    $response = array();
    
    // Check if user session exists and is valid
    if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        // Check session timeout (15 minutes = 900 seconds)
        $sessionTimeout = 15 * 60; // 15 minutes in seconds
        
        if (isset($_SESSION['last_activity'])) {
            $timeSinceLastActivity = time() - $_SESSION['last_activity'];
            
            if ($timeSinceLastActivity > $sessionTimeout) {
                // Session expired
                session_unset();
                session_destroy();
                $response['authenticated'] = false;
                $response['reason'] = 'session_expired';
            } else {
                // Session is valid
                $_SESSION['last_activity'] = time();
                $response['authenticated'] = true;
                $response['user_id'] = $_SESSION['user_id'];
                $response['username'] = $_SESSION['username'];
                $response['last_activity'] = $_SESSION['last_activity'];
                $response['impersonating'] = isset($_SESSION['impersonating']) ? $_SESSION['impersonating'] : false;
            }
        } else {
            // No last activity recorded, set it now
            $_SESSION['last_activity'] = time();
            $response['authenticated'] = true;
            $response['user_id'] = $_SESSION['user_id'];
            $response['username'] = $_SESSION['username'];
            $response['last_activity'] = $_SESSION['last_activity'];
            $response['impersonating'] = isset($_SESSION['impersonating']) ? $_SESSION['impersonating'] : false;
        }
    } else {
        // No valid session
        $response['authenticated'] = false;
        $response['reason'] = 'no_session';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => 'Server error occurred',
        'debug' => $e->getMessage()
    ]);
}
?>
