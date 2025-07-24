<?php
// Create Dummy User for Testing Admin Features
require_once '../config.php';
require_once '../db-connect.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Dummy user data
    $username = 'testuser';
    $email = 'testuser@example.com';
    $password = 'Test123!'; // Simple test password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $initialBalance = 1000.00; // $1000 USDT for testing
    $creditScore = 750; // Good credit score
    
    // Check if user already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->execute([$username, $email]);
    
    if ($checkStmt->fetch()) {
        echo "❌ Dummy user already exists!\n";
        echo "Username: $username\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
        echo "You can use this existing user for testing.\n";
        exit;
    }
    
    // Create the dummy user
    $insertStmt = $pdo->prepare("
        INSERT INTO users (username, email, password, balance, credit_score, status, created_at, last_login) 
        VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    
    $insertStmt->execute([
        $username,
        $email,
        $hashedPassword,
        $initialBalance,
        $creditScore
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Create user_wallets table if it doesn't exist and add some dummy wallet addresses
    $createWalletsTable = "CREATE TABLE IF NOT EXISTS user_wallets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        cryptocurrency VARCHAR(10) NOT NULL,
        network VARCHAR(50),
        wallet_address VARCHAR(100) NOT NULL,
        assigned_by VARCHAR(50),
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        UNIQUE KEY unique_user_crypto_network (user_id, cryptocurrency, network),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($createWalletsTable);
    
    // Add some dummy wallet addresses
    $wallets = [
        ['BTC', 'Bitcoin', '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'],
        ['ETH', 'Ethereum', '0x742d35Cc6634C0532925a3b8D4f25F2d4c4b8c9c'],
        ['USDT', 'Ethereum', '0x8ba1f109551bD432803012645Hac136c5c2BD754'],
        ['BNB', 'BSC', 'bnb1grpf0955h0ykzq3ar5nmum7y6gdfl6lxfn46h2']
    ];
    
    foreach ($wallets as $wallet) {
        $walletStmt = $pdo->prepare("
            INSERT INTO user_wallets (user_id, cryptocurrency, network, wallet_address, assigned_by) 
            VALUES (?, ?, ?, ?, 'system')
        ");
        $walletStmt->execute([$userId, $wallet[0], $wallet[1], $wallet[2]]);
    }
    
    echo "✅ Dummy user created successfully!\n\n";
    echo "=== TEST USER CREDENTIALS ===\n";
    echo "Username: $username\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "User ID: $userId\n";
    echo "Initial Balance: $" . number_format($initialBalance, 2) . " USDT\n";
    echo "Credit Score: $creditScore\n";
    echo "Status: Active\n\n";
    
    echo "=== ASSIGNED WALLETS ===\n";
    foreach ($wallets as $wallet) {
        echo "• {$wallet[0]} ({$wallet[1]}): {$wallet[2]}\n";
    }
    
    echo "\n=== TESTING INSTRUCTIONS ===\n";
    echo "1. Login to admin panel\n";
    echo "2. Use this dummy user to test:\n";
    echo "   - Balance adjustments (credit/debit/set)\n";
    echo "   - Credit score updates\n";
    echo "   - Wallet address assignments\n";
    echo "   - User impersonation\n";
    echo "3. Login to frontend with these credentials to test user experience\n";
    echo "4. Test impersonation by switching between admin and user views\n\n";
    
    echo "🎯 Ready for comprehensive admin feature testing!\n";
    
} catch (Exception $e) {
    echo "❌ Error creating dummy user: " . $e->getMessage() . "\n";
    
    // If users table doesn't exist, create it
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "\n🔧 Creating users table...\n";
        
        $createUsersTable = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(15,2) DEFAULT 0.00,
            credit_score INT DEFAULT 500,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            email_verified BOOLEAN DEFAULT FALSE,
            phone VARCHAR(20),
            kyc_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
        )";
        
        try {
            $pdo->exec($createUsersTable);
            echo "✅ Users table created successfully!\n";
            echo "🔄 Please run this script again to create the dummy user.\n";
        } catch (Exception $createError) {
            echo "❌ Failed to create users table: " . $createError->getMessage() . "\n";
        }
    }
}
?>
