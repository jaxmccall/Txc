<?php
/**
 * Database Connection Test Script
 * Tests database connectivity with detailed error reporting
 * Usage: Access via browser or CLI for debugging database issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Database Connection Test</h2>\n";
echo "<pre>\n";

// Test 1: Basic credentials check
echo "=== TEST 1: Database Credentials ===\n";
$host = 'localhost';
$database = 'u925878138_tripplex';
$username = 'u925878138_admin';
$password = 'Chills@1008!!';

echo "Host: $host\n";
echo "Database: $database\n";
echo "Username: $username\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n\n";

// Test 2: MySQLi Connection
echo "=== TEST 2: MySQLi Connection ===\n";
try {
    $mysqli = new mysqli($host, $username, $password, $database);
    
    if ($mysqli->connect_errno) {
        echo "❌ MySQLi Connection FAILED\n";
        echo "Error #" . $mysqli->connect_errno . ": " . $mysqli->connect_error . "\n";
    } else {
        echo "✅ MySQLi Connection SUCCESSFUL\n";
        echo "Server Info: " . $mysqli->server_info . "\n";
        echo "Host Info: " . $mysqli->host_info . "\n";
        
        // Test query
        $result = $mysqli->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Query Test: Found " . $row['count'] . " users in database\n";
        } else {
            echo "⚠️  Query Test Failed: " . $mysqli->error . "\n";
        }
        
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "❌ MySQLi Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: PDO Connection
echo "=== TEST 3: PDO Connection ===\n";
try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ PDO Connection SUCCESSFUL\n";
    echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $row = $stmt->fetch();
    echo "✅ Query Test: Found " . $row['count'] . " users in database\n";
    
} catch (PDOException $e) {
    echo "❌ PDO Connection FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Configuration Files Check
echo "=== TEST 4: Configuration Files ===\n";

$config_files = [
    'config.php' => 'Main configuration file',
    'login_config.php' => 'Login configuration file',
    'db-connect.php' => 'Database connection file'
];

foreach ($config_files as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $file - $description (EXISTS)\n";
        
        // Check if file contains database credentials
        $content = file_get_contents($file);
        if (strpos($content, 'u925878138_tripplex') !== false) {
            echo "   ✅ Contains correct database name\n";
        } else {
            echo "   ⚠️  May contain incorrect database credentials\n";
        }
    } else {
        echo "❌ $file - $description (MISSING)\n";
    }
}

echo "\n";

// Test 5: Session and Error Logging
echo "=== TEST 5: System Configuration ===\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
echo "Error Reporting: " . (error_reporting() ? "ENABLED" : "DISABLED") . "\n";
echo "Display Errors: " . (ini_get('display_errors') ? "ON" : "OFF") . "\n";

// Check required PHP extensions
$required_extensions = ['mysqli', 'pdo', 'pdo_mysql', 'session', 'json'];
echo "\nRequired PHP Extensions:\n";
foreach ($required_extensions as $ext) {
    echo "  " . ($ext === 'pdo_mysql' ? 'PDO MySQL' : strtoupper($ext)) . ": " . 
         (extension_loaded($ext) ? "✅ LOADED" : "❌ MISSING") . "\n";
}

echo "\n";
echo "=== TEST COMPLETE ===\n";
echo "If all tests pass, your database connection is working correctly.\n";
echo "If tests fail, check the error messages above for troubleshooting.\n";
echo "</pre>";

// Add some basic styling
echo "<style>
body { font-family: monospace; margin: 20px; }
pre { background: #f5f5f5; padding: 15px; border: 1px solid #ddd; }
h2 { color: #333; }
</style>";
?>