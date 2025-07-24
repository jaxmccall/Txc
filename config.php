<?php
$DB_HOST = 'localhost';
$DB_USER = 'u925878138_admin';
$DB_PASS = 'Chills@1008!!';
$DB_NAME = 'u925878138_tripplex';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log the error but continue execution
    error_log("Database connection failed: " . $e->getMessage());
    $pdo = null;
    
    // Only die with JSON error if this is an AJAX request that specifically needs database access
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        // Check if this is a super admin login attempt that shouldn't require database
        $is_super_admin_login = (
            isset($_POST['username']) && $_POST['username'] === 'admin' &&
            isset($_POST['password']) && $_POST['password'] === 'admin@123'
        );
        
        if (!$is_super_admin_login) {
            // Only return database error for non-super-admin operations
            die(json_encode([
                "success" => false,
                "message" => "Database temporarily unavailable"
            ]));
        }
    }
}
?>
