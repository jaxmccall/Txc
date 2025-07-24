<?php
// Disable error display
ini_set('display_errors', 0);
error_reporting(0);

function initializeDatabase() {
    try {
        require 'login_config.php';
        
        // Check if tables exist
        $tables = ['users', 'signup_otps', 'sessions', 'failed_login_attempts'];
        $missing_tables = [];
        
        foreach ($tables as $table) {
            $result = $mysqli->query("SHOW TABLES LIKE '$table'");
            if (!$result || $result->num_rows == 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            // Read and execute SQL file
            $sql = file_get_contents('database.sql');
            $mysqli->multi_query($sql);
            
            // Check if tables were created
            foreach ($missing_tables as $table) {
                $result = $mysqli->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    error_log("Table $table created successfully");
                } else {
                    error_log("Failed to create table $table");
                    return false;
                }
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
        return false;
    }
}
