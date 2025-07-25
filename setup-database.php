<?php
/**
 * Database Setup Script for Tripple Exchange
 * Run this file once to set up the database schema
 */

require_once 'config.php';

echo "<h2>Tripple Exchange Database Setup</h2>\n";
echo "<p>Setting up database schema...</p>\n";

try {
    // Read the complete database schema
    $schema = file_get_contents('complete_database_schema.sql');
    
    if (!$schema) {
        throw new Exception('Could not read database schema file');
    }
    
    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)), 
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (trim($statement)) {
            try {
                $pdo->exec($statement);
                $successCount++;
                echo "<p style='color: green;'>✓ Executed: " . substr(trim($statement), 0, 60) . "...</p>\n";
            } catch (PDOException $e) {
                $errorCount++;
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>\n";
                echo "<p style='color: #666;'>Statement: " . substr(trim($statement), 0, 100) . "...</p>\n";
            }
        }
    }
    
    echo "<hr>\n";
    echo "<h3>Setup Complete!</h3>\n";
    echo "<p><strong>Results:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>✓ Successful statements: $successCount</li>\n";
    echo "<li>✗ Failed statements: $errorCount</li>\n";
    echo "</ul>\n";
    
    if ($errorCount === 0) {
        echo "<p style='color: green; font-weight: bold;'>🎉 Database setup completed successfully!</p>\n";
        echo "<h4>Default Super Admin Account:</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Username:</strong> admin</li>\n";
        echo "<li><strong>Password:</strong> admin@123</li>\n";
        echo "<li><strong>Email:</strong> admin@trippleexchange.com</li>\n";
        echo "</ul>\n";
        echo "<p><a href='admin-login.html'>Login to Admin Panel</a></p>\n";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>⚠️ Setup completed with some errors. Please review the errors above.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Setup failed: " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><small>Generated on " . date('Y-m-d H:i:s') . "</small></p>\n";
?>