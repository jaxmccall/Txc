<?php
session_start(); // Start session for tracking initialization
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// Debugging - log the raw POST data
error_log("Raw POST data: " . file_get_contents('php://input'));
error_log("Raw $_POST: " . print_r($_POST, true));

// Get email from POST data
$email = $_POST['email'] ?? '';
error_log("Extracted email: " . $email);

// Basic validation
if (empty($email)) {
    error_log("Email is empty or not set");
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Lenient email validation
if (!preg_match('/^[^\s@]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
    error_log("Email format validation failed for: " . $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Include the database initialization file
require_once 'init_db.php';

try {
    // Initialize database if needed
    if (!initializeDatabase()) {
        throw new Exception('Failed to initialize database');
    }
    
    require 'login_config.php';
    
    // Check if email exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }
    
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Prepare the response
    $response = [
        'success' => true,
        'exists' => $user !== null
    ];

} catch (Exception $e) {
    error_log("Email check error: " . $e->getMessage());
    
    // Prepare error response
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
} finally {
    // Close statement if it was opened
    if (isset($stmt)) {
        $stmt->close();
    }
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send the JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
