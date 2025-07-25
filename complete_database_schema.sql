-- ===============================
-- TRIPPLE EXCHANGE - COMPLETE DATABASE SCHEMA
-- Production Ready with All Requirements
-- ===============================

-- Use the existing Hostinger database
USE u925878138_tripplex;

-- Enhanced Users table (includes both regular users and admins)
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
    account_score INT DEFAULT 100 CHECK (account_score >= 0 AND account_score <= 100),
    
    -- User type (admin or regular user)
    user_type ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    
    -- KYC Status
    kyc_status ENUM('pending', 'submitted', 'approved', 'rejected') DEFAULT 'pending',
    kyc_submitted_at TIMESTAMP NULL,
    kyc_reviewed_at TIMESTAMP NULL,
    kyc_reviewed_by INT NULL,
    
    -- Registration details
    signup_ip VARCHAR(45),
    signup_user_agent TEXT,
    signup_country VARCHAR(100),
    signup_region VARCHAR(100),
    signup_city VARCHAR(100),
    signup_lat DECIMAL(10, 8),
    signup_lon DECIMAL(11, 8),
    signup_isp VARCHAR(255),
    referral_code VARCHAR(50),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    last_activity TIMESTAMP NULL,
    
    FOREIGN KEY (kyc_reviewed_by) REFERENCES users(id) ON SET NULL,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_user_type (user_type),
    INDEX idx_kyc_status (kyc_status),
    INDEX idx_last_activity (last_activity)
);

-- KYC Documents table
CREATE TABLE IF NOT EXISTS kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type ENUM('selfie', 'id_front', 'id_back', 'id_file') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    upload_ip VARCHAR(45),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_document_type (document_type),
    INDEX idx_status (status)
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
    transaction_type ENUM('credit', 'debit', 'deposit', 'withdrawal', 'mining_reward', 'admin_adjustment', 'trade') NOT NULL,
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
    INDEX idx_created_at (created_at),
    INDEX idx_reference_id (reference_id)
);

-- Enhanced Trades table
CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pair VARCHAR(20) NOT NULL,
    side ENUM('buy', 'sell') NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    price DECIMAL(20,8) NOT NULL,
    total DECIMAL(20,8) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled', 'win', 'loss') DEFAULT 'pending',
    result ENUM('win', 'loss', 'pending') DEFAULT 'pending',
    profit_loss DECIMAL(20,8) DEFAULT 0.00000000,
    admin_notes TEXT,
    modified_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    modified_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (modified_by) REFERENCES users(id) ON SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_pair (pair),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Enhanced Notifications System
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT NULL, -- NULL for system notifications, admin ID for admin sent
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'deposit', 'security', 'system', 'admin', 'kyc', 'trade') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type)
);

-- OTP Codes (enhanced)
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    type ENUM('login', 'signup', 'password_reset') NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_expires_at (expires_at),
    INDEX idx_code (code)
);

-- Asset Configurations
CREATE TABLE IF NOT EXISTS asset_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    decimals INT DEFAULT 8,
    deposit_enabled BOOLEAN DEFAULT TRUE,
    withdrawal_enabled BOOLEAN DEFAULT TRUE,
    trading_enabled BOOLEAN DEFAULT TRUE,
    min_withdrawal DECIMAL(20,8) DEFAULT 0.00000001,
    withdrawal_fee DECIMAL(20,8) DEFAULT 0.00000000,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    icon_url VARCHAR(255),
    
    INDEX idx_symbol (symbol),
    INDEX idx_sort_order (sort_order),
    INDEX idx_is_active (is_active)
);

-- Admin Session Tracking
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    impersonated_user_id INT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (impersonated_user_id) REFERENCES users(id) ON SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_session_token (session_token),
    INDEX idx_last_activity (last_activity)
);

-- Admin Action Logs
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_user_id INT NULL,
    description TEXT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Insert default super admin user (username: admin, password: admin@123)
INSERT IGNORE INTO users (
    username, 
    email, 
    password, 
    first_name, 
    last_name, 
    is_verified, 
    is_active, 
    user_type,
    account_score,
    kyc_status
) VALUES (
    'admin', 
    'admin@trippleexchange.com', 
    '$2y$10$8K1p/a8d6KBj7UJ5qHI8Ru.BFG8GztMlqhCGmfnYZ2qPk6q2sJzIq', -- admin@123
    'Super', 
    'Administrator', 
    TRUE, 
    TRUE, 
    'super_admin',
    100,
    'approved'
);

-- Insert default assets
INSERT IGNORE INTO asset_configs (symbol, name, decimals, sort_order, icon_url) VALUES 
('USDT', 'Tether USD', 6, 1, 'https://cryptologos.cc/logos/tether-usdt-logo.png'),
('BTC', 'Bitcoin', 8, 2, 'https://cryptologos.cc/logos/bitcoin-btc-logo.png'),
('ETH', 'Ethereum', 18, 3, 'https://cryptologos.cc/logos/ethereum-eth-logo.png'),
('BNB', 'BNB', 18, 4, 'https://cryptologos.cc/logos/bnb-bnb-logo.png'),
('ADA', 'Cardano', 6, 5, 'https://cryptologos.cc/logos/cardano-ada-logo.png'),
('XRP', 'Ripple', 6, 6, 'https://cryptologos.cc/logos/xrp-xrp-logo.png'),
('SOL', 'Solana', 9, 7, 'https://cryptologos.cc/logos/solana-sol-logo.png'),
('DOT', 'Polkadot', 10, 8, 'https://cryptologos.cc/logos/polkadot-new-dot-logo.png'),
('DOGE', 'Dogecoin', 8, 9, 'https://cryptologos.cc/logos/dogecoin-doge-logo.png'),
('LTC', 'Litecoin', 8, 10, 'https://cryptologos.cc/logos/litecoin-ltc-logo.png');

-- Set up admin balances
SET @admin_user_id = (SELECT id FROM users WHERE username = 'admin' LIMIT 1);
INSERT IGNORE INTO user_balances (user_id, asset_symbol, balance) VALUES 
(@admin_user_id, 'USDT', 1000000.00000000),
(@admin_user_id, 'BTC', 100.00000000),
(@admin_user_id, 'ETH', 500.00000000),
(@admin_user_id, 'BNB', 2000.00000000),
(@admin_user_id, 'ADA', 100000.00000000),
(@admin_user_id, 'XRP', 50000.00000000),
(@admin_user_id, 'SOL', 1000.00000000),
(@admin_user_id, 'DOT', 5000.00000000),
(@admin_user_id, 'DOGE', 500000.00000000),
(@admin_user_id, 'LTC', 500.00000000);

-- Create welcome notification for admin
INSERT IGNORE INTO notifications (user_id, title, message, type) VALUES
(@admin_user_id, 'Welcome to Tripple Exchange Admin Panel', 'Your super administrator account has been successfully created. You now have full access to manage users, trades, KYC, and all platform features.', 'success');

-- Create uploads directory structure (placeholder - actual directories need to be created)
-- /uploads/kyc/{user_id}/
-- /uploads/kyc/{user_id}/selfie/
-- /uploads/kyc/{user_id}/id_documents/

COMMIT;