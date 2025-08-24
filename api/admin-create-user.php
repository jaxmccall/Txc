<?php
// File: api/admin-create-user.php
header('Content-Type: application/json');
require_once '../config.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$country = trim($input['country'] ?? '');
$password = $input['password'] ?? '';
$kyc_status = $input['kyc_status'] ?? 'pending';
$credit_score = intval($input['credit_score'] ?? 100);
$initial_balance = floatval($input['initial_balance'] ?? 0);

// Validate required fields
if (!$first_name || !$last_name || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate credit score
if ($credit_score < 0 || $credit_score > 100) {
    echo json_encode(['success' => false, 'message' => 'Credit score must be between 0 and 100']);
    exit;
}

// Validate KYC status
if (!in_array($kyc_status, ['pending', 'approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid KYC status']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Generate username from email
    $username = strtolower(explode('@', $email)[0]);
    $base_username = $username;
    $counter = 1;
    
    // Ensure username is unique
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if (!$stmt->fetch()) {
            break;
        }
        $username = $base_username . $counter;
        $counter++;
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            username, email, password, first_name, last_name, phone, country,
            is_verified, is_active, kyc_status, account_score, created_at
        ) VALUES (
            :username, :email, :password, :first_name, :last_name, :phone, :country,
            1, 1, :kyc_status, :account_score, CURRENT_TIMESTAMP
        )
    ");
    
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => $password_hash,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':phone' => $phone ?: null,
        ':country' => $country ?: null,
        ':kyc_status' => $kyc_status,
        ':account_score' => $credit_score
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    // Create default asset balances
    $default_assets = ['USDT', 'BTC', 'ETH', 'BNB', 'ADA', 'XRP', 'SOL', 'DOT'];
    
    foreach ($default_assets as $asset) {
        $balance = ($asset === 'USDT') ? $initial_balance : 0;
        
        $stmt = $pdo->prepare("
            INSERT INTO user_balances (user_id, asset_symbol, balance, created_at)
            VALUES (:user_id, :asset_symbol, :balance, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':asset_symbol' => $asset,
            ':balance' => $balance
        ]);
    }
    
    // Log initial balance if provided
    if ($initial_balance > 0) {
        $admin_id = $_SESSION['admin_user_id'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO balance_transactions (
                user_id, asset_symbol, amount, transaction_type, 
                balance_before, balance_after, description, admin_id
            ) VALUES (
                :user_id, 'USDT', :amount, 'admin_adjustment', 
                0, :amount, 'Initial balance by admin', :admin_id
            )
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':amount' => $initial_balance,
            ':admin_id' => $admin_id
        ]);
    }
    
    // Create welcome notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (:user_id, 'Welcome to Tripple Exchange', 'Your account has been created successfully. Welcome to our platform!', 'success', CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([':user_id' => $user_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $user_id
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error in admin-create-user.php: " . $e->getMessage());
    
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Email or username already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
?>