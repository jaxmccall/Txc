<?php
/**
 * Emergency Test User Creation Script
 * Creates a test user for admin troubleshooting and emergency access
 * 
 * SECURITY WARNING: Remove this file from production servers!
 * This script should only be used for testing and emergency access.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';

// Set content type for web access
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 Emergency Test User Creation</h2>\n";
echo "<pre>\n";

// Default test user credentials
$test_username = 'testuser';
$test_email = 'test@example.com';
$test_password = 'TestPass123!';

// Allow customization via GET parameters (for CLI/web testing)
if (isset($_GET['username'])) $test_username = $_GET['username'];
if (isset($_GET['email'])) $test_email = $_GET['email'];
if (isset($_GET['password'])) $test_password = $_GET['password'];

echo "Creating test user with:\n";
echo "Username: $test_username\n";
echo "Email: $test_email\n";
echo "Password: " . str_repeat('*', strlen($test_password)) . "\n\n";

try {
    // Check if user already exists
    $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check_stmt->bind_param("ss", $test_username, $test_email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "⚠️  User already exists with this username or email!\n";
        echo "Updating existing user instead...\n\n";
        
        // Update existing user
        $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
        $update_stmt = $mysqli->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE username = ? OR email = ?");
        $update_stmt->bind_param("sss", $hashed_password, $test_username, $test_email);
        
        if ($update_stmt->execute()) {
            echo "✅ Test user updated successfully!\n";
            echo "You can now login with:\n";
            echo "  Username/Email: $test_username or $test_email\n";
            echo "  Password: $test_password\n";
        } else {
            echo "❌ Failed to update user: " . $mysqli->error . "\n";
        }
        
    } else {
        echo "Creating new test user...\n";
        
        // Hash password
        $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
        
        // Get current IP and user agent for logging
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Command Line';
        
        // Insert new user
        $insert_stmt = $mysqli->prepare("
            INSERT INTO users (
                username, 
                email, 
                password, 
                signup_ip, 
                signup_user_agent, 
                created_at, 
                email_verified
            ) VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $insert_stmt->bind_param("sssss", 
            $test_username, 
            $test_email, 
            $hashed_password, 
            $ip, 
            $user_agent
        );
        
        if ($insert_stmt->execute()) {
            $user_id = $mysqli->insert_id;
            echo "✅ Test user created successfully!\n";
            echo "User ID: $user_id\n";
            echo "Username: $test_username\n";
            echo "Email: $test_email\n";
            echo "Password: $test_password\n";
            echo "Email is pre-verified for testing.\n\n";
            
            // Optional: Add some test balance
            echo "Adding test balance (1000 USDT)...\n";
            $balance_stmt = $mysqli->prepare("
                INSERT INTO user_balances (user_id, asset_symbol, balance, created_at) 
                VALUES (?, 'USDT', 1000.00, NOW())
                ON DUPLICATE KEY UPDATE balance = balance + 1000.00
            ");
            $balance_stmt->bind_param("i", $user_id);
            
            if ($balance_stmt->execute()) {
                echo "✅ Test balance added successfully!\n";
            } else {
                echo "⚠️  Could not add test balance (table may not exist): " . $mysqli->error . "\n";
            }
            
        } else {
            echo "❌ Failed to create user: " . $mysqli->error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== USAGE INSTRUCTIONS ===\n";
echo "1. Navigate to your login page\n";
echo "2. Use the credentials shown above\n";
echo "3. Test the authentication system\n";
echo "4. ⚠️  IMPORTANT: Delete this file from production!\n\n";

echo "=== CUSTOMIZATION ===\n";
echo "You can customize the test user by adding URL parameters:\n";
echo "?username=mytest&email=my@test.com&password=MyPass123!\n\n";

echo "=== SECURITY WARNING ===\n";
echo "🚨 This script creates users without proper validation!\n";
echo "🚨 Only use for testing and emergency access!\n";
echo "🚨 Remove this file from production servers!\n";

echo "</pre>";

// Add some basic styling
echo "<style>
body { font-family: monospace; margin: 20px; background: #fff3cd; }
pre { background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; }
h2 { color: #856404; }
</style>";
?>