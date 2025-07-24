<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $response = array();
    
    // Check if user session exists
    if (isset($_SESSION['user_id'])) {
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        $response['success'] = true;
        $response['last_activity'] = $_SESSION['last_activity'];
        $response['message'] = 'Session activity updated';
    } else {
        $response['success'] = false;
        $response['message'] = 'No active session';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'debug' => $e->getMessage()
    ]);
}
?>
