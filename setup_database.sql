-- Database Setup for Tripple Exchange
-- Run this script to initialize the database with required tables

CREATE DATABASE IF NOT EXISTS tripple_exchange;
USE tripple_exchange;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(50),
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);

-- User balances table
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
    INDEX idx_user_id (user_id),
    INDEX idx_asset_symbol (asset_symbol)
);

-- Balance transactions table
CREATE TABLE IF NOT EXISTS balance_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    asset_symbol VARCHAR(10) NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    transaction_type ENUM('credit', 'debit') NOT NULL,
    old_balance DECIMAL(20,8) NOT NULL,
    new_balance DECIMAL(20,8) NOT NULL,
    description TEXT,
    reference_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_asset_symbol (asset_symbol),
    INDEX idx_created_at (created_at),
    INDEX idx_reference_id (reference_id)
);

-- Trades table
CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pair VARCHAR(20) NOT NULL,
    side ENUM('buy', 'sell') NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    price DECIMAL(20,8) NOT NULL,
    total DECIMAL(20,8) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_pair (pair),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- OTP verification table
CREATE TABLE IF NOT EXISTS otp_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('signup', 'login', 'password_reset') NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_otp_code (otp_code),
    INDEX idx_expires_at (expires_at)
);

-- Insert default assets for new users
INSERT IGNORE INTO user_balances (user_id, asset_symbol, balance, locked_balance) 
SELECT u.id, assets.symbol, 0.00000000, 0.00000000
FROM users u
CROSS JOIN (
    SELECT 'USDT' as symbol UNION ALL
    SELECT 'BTC' UNION ALL
    SELECT 'ETH' UNION ALL
    SELECT 'BNB' UNION ALL
    SELECT 'ADA' UNION ALL
    SELECT 'XRP' UNION ALL
    SELECT 'SOL' UNION ALL
    SELECT 'DOGE' UNION ALL
    SELECT 'DOT' UNION ALL
    SELECT 'LTC'
) assets
WHERE NOT EXISTS (
    SELECT 1 FROM user_balances ub 
    WHERE ub.user_id = u.id AND ub.asset_symbol = assets.symbol
);

-- Create a sample admin user (password: admin123)
INSERT IGNORE INTO users (email, password_hash, first_name, last_name, is_verified, is_active) 
VALUES ('admin@tripple-exchange.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', TRUE, TRUE);

-- Initialize balances for admin user
SET @admin_user_id = (SELECT id FROM users WHERE email = 'admin@tripple-exchange.com');
INSERT IGNORE INTO user_balances (user_id, asset_symbol, balance, locked_balance) VALUES
(@admin_user_id, 'USDT', 10000.00000000, 0.00000000),
(@admin_user_id, 'BTC', 0.50000000, 0.00000000),
(@admin_user_id, 'ETH', 5.00000000, 0.00000000),
(@admin_user_id, 'BNB', 20.00000000, 0.00000000),
(@admin_user_id, 'ADA', 1000.00000000, 0.00000000),
(@admin_user_id, 'XRP', 500.00000000, 0.00000000),
(@admin_user_id, 'SOL', 10.00000000, 0.00000000),
(@admin_user_id, 'DOGE', 5000.00000000, 0.00000000),
(@admin_user_id, 'DOT', 50.00000000, 0.00000000),
(@admin_user_id, 'LTC', 5.00000000, 0.00000000);

-- Create sample notifications for admin
INSERT IGNORE INTO notifications (user_id, title, message, type) VALUES
(@admin_user_id, 'Welcome to Tripple Exchange', 'Your account has been successfully created and verified.', 'success'),
(@admin_user_id, 'Security Alert', 'Please enable 2FA for enhanced account security.', 'warning'),
(@admin_user_id, 'System Maintenance', 'Scheduled maintenance on Sunday 2AM-4AM UTC.', 'info'),
(@admin_user_id, 'New Feature Available', 'Advanced trading charts are now available in the trade section.', 'info');

COMMIT;
