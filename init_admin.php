<?php
// Database initialization script to ensure admins table exists
require_once 'config.php';

try {
    // Create admins table if it doesn't exist
    $sql = "
    CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        is_master BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Admins table created/verified successfully.\n";
    
    // Check if there's at least one admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Create default admin (username: admin, password: Admin@123)
        $username = 'admin';
        $password = 'Admin@123';
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO admins (username, password, full_name, email, is_master) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password_hash, 'System Administrator', 'admin@trippleexchange.com', true]);
        
        echo "Default admin created: username='admin', password='Admin@123'\n";
    } else {
        echo "Admin users already exist in database.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>