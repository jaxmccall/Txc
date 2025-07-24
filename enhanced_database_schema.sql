-- ===============================
-- TRIPPLE EXCHANGE - ENHANCED DATABASE SCHEMA
-- Version: 2.0 (Production Ready)
-- ===============================

CREATE DATABASE IF NOT EXISTS u925878138_tripplex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE u925878138_tripplex;

-- Enhanced Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(50),
    
    -- Account Status
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    account_score INT DEFAULT 0 CHECK (account_score >= 0 AND account_score <= 100),
    
    -- KYC Status
    kyc_status ENUM('pending', 'submitted', 'approved', 'rejected') DEFAULT 'pending',
    
    -- Wallet Information
    wallet_address VARCHAR(255),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    last_activity TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_last_activity (last_activity)
);

-- Enhanced User Balances
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
);

-- Enhanced Balance Transactions
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
    FOREIGN KEY (admin_id) REFERENCES users(id) ON SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at)
);

-- Mining Machines
CREATE TABLE IF NOT EXISTS mining_machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    hash_rate DECIMAL(15,2) NOT NULL,
    daily_reward DECIMAL(20,8) NOT NULL,
    price DECIMAL(20,8) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Mining Contracts
CREATE TABLE IF NOT EXISTS user_mining_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    machine_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    purchase_price DECIMAL(20,8) NOT NULL,
    daily_reward DECIMAL(20,8) NOT NULL,
    total_earned DECIMAL(20,8) DEFAULT 0.00000000,
    status ENUM('active', 'paused', 'expired') DEFAULT 'active',
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES mining_machines(id) ON DELETE CASCADE
);

-- Notifications System
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
);

-- OTP Codes
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    email VARCHAR(255),
    code VARCHAR(10) NOT NULL,
    type ENUM('login', 'signup', 'password_reset') NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_expires_at (expires_at)
);

-- Asset Configurations
CREATE TABLE IF NOT EXISTS asset_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    decimals INT DEFAULT 8,
    deposit_enabled BOOLEAN DEFAULT TRUE,
    withdrawal_enabled BOOLEAN DEFAULT TRUE,
    min_withdrawal DECIMAL(20,8) DEFAULT 0.00000001,
    withdrawal_fee DECIMAL(20,8) DEFAULT 0.00000000,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    
    INDEX idx_symbol (symbol),
    INDEX idx_sort_order (sort_order)
);

-- Insert default admin user
INSERT IGNORE INTO users (username, email, password, first_name, last_name, is_verified, is_active) VALUES 
('admin', 'admin@trippleexchange.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', TRUE, TRUE);

-- Insert default assets
INSERT IGNORE INTO asset_configs (symbol, name, decimals, sort_order) VALUES 
('USDT', 'Tether USD', 6, 1),
('BTC', 'Bitcoin', 8, 2),
('ETH', 'Ethereum', 18, 3),
('BNB', 'Binance Coin', 18, 4),
('ADA', 'Cardano', 6, 5),
('XRP', 'Ripple', 6, 6),
('SOL', 'Solana', 9, 7),
('DOT', 'Polkadot', 10, 8);

-- Set up admin balances
SET @admin_user_id = (SELECT id FROM users WHERE username = 'admin' LIMIT 1);
INSERT IGNORE INTO user_balances (user_id, asset_symbol, balance) VALUES 
(@admin_user_id, 'USDT', 100000.00000000),
(@admin_user_id, 'BTC', 10.00000000),
(@admin_user_id, 'ETH', 50.00000000),
(@admin_user_id, 'BNB', 200.00000000),
(@admin_user_id, 'ADA', 10000.00000000),
(@admin_user_id, 'XRP', 5000.00000000),
(@admin_user_id, 'SOL', 100.00000000),
(@admin_user_id, 'DOT', 500.00000000);
