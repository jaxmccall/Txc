<?php
// File: setup_admin_integration.php
// This script ensures all required database tables and columns exist for admin integration

require_once 'config.php';

try {
    echo "Setting up admin integration...\n";
    
    // Check if account_score column exists in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'account_score'");
    if (!$stmt->fetch()) {
        echo "Adding account_score column to users table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN account_score INT DEFAULT 100 CHECK (account_score >= 0 AND account_score <= 100)");
    }
    
    // Check if username column exists in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
    if (!$stmt->fetch()) {
        echo "Adding username column to users table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE AFTER id");
    }
    
    // Check if kyc_status column exists in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'kyc_status'");
    if (!$stmt->fetch()) {
        echo "Adding kyc_status column to users table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN kyc_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
    }
    
    // Ensure user_balances table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            asset_symbol VARCHAR(10) NOT NULL,
            balance DECIMAL(20,8) DEFAULT 0.00000000,
            locked_balance DECIMAL(20,8) DEFAULT 0.00000000,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_asset (user_id, asset_symbol),
            CHECK (balance >= 0),
            CHECK (locked_balance >= 0),
            INDEX idx_user_id (user_id),
            INDEX idx_asset_symbol (asset_symbol)
        )
    ");
    
    // Ensure balance_transactions table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS balance_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            asset_symbol VARCHAR(10) NOT NULL,
            amount DECIMAL(20,8) NOT NULL,
            transaction_type ENUM('credit', 'debit', 'deposit', 'withdrawal', 'mining_reward', 'admin_adjustment') NOT NULL,
            balance_before DECIMAL(20,8) NOT NULL,
            balance_after DECIMAL(20,8) NOT NULL,
            description TEXT,
            reference_id VARCHAR(100),
            admin_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_transaction_type (transaction_type),
            INDEX idx_created_at (created_at)
        )
    ");
    
    // Ensure notifications table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error', 'deposit', 'security', 'system') DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read)
        )
    ");
    
    // Update existing users to have usernames if they don't
    echo "Updating existing users with usernames...\n";
    $stmt = $pdo->query("SELECT id, email FROM users WHERE username IS NULL OR username = ''");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $base_username = strtolower(explode('@', $user['email'])[0]);
        $username = $base_username;
        $counter = 1;
        
        // Ensure username is unique
        while (true) {
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
            $check_stmt->execute([':username' => $username, ':user_id' => $user['id']]);
            
            if (!$check_stmt->fetch()) {
                break;
            }
            
            $username = $base_username . $counter;
            $counter++;
        }
        
        $update_stmt = $pdo->prepare("UPDATE users SET username = :username WHERE id = :user_id");
        $update_stmt->execute([':username' => $username, ':user_id' => $user['id']]);
    }
    
    // Create default asset balances for existing users who don't have them
    echo "Creating default asset balances...\n";
    $default_assets = ['USDT', 'BTC', 'ETH', 'BNB', 'ADA', 'XRP', 'SOL', 'DOT'];
    
    $stmt = $pdo->query("SELECT id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        foreach ($default_assets as $asset) {
            $check_stmt = $pdo->prepare("SELECT id FROM user_balances WHERE user_id = :user_id AND asset_symbol = :asset");
            $check_stmt->execute([':user_id' => $user['id'], ':asset' => $asset]);
            
            if (!$check_stmt->fetch()) {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO user_balances (user_id, asset_symbol, balance, created_at)
                    VALUES (:user_id, :asset_symbol, 0, CURRENT_TIMESTAMP)
                ");
                $insert_stmt->execute([':user_id' => $user['id'], ':asset_symbol' => $asset]);
            }
        }
    }
    
    echo "Admin integration setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error setting up admin integration: " . $e->getMessage() . "\n";
}
?>