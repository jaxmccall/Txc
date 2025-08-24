<?php
// File: api/admin-balance-user.php
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

$user_id = intval($input['id'] ?? 0);
$amount = floatval($input['amount'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

if ($amount == 0) {
    echo json_encode(['success' => false, 'message' => 'Amount cannot be zero']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get current balance
    $stmt = $pdo->prepare("
        SELECT balance 
        FROM user_balances 
        WHERE user_id = :user_id AND asset_symbol = 'USDT'
    ");
    $stmt->execute([':user_id' => $user_id]);
    $current_balance = $stmt->fetchColumn();
    
    if ($current_balance === false) {
        // Create balance record if it doesn't exist
        $current_balance = 0;
        $stmt = $pdo->prepare("
            INSERT INTO user_balances (user_id, asset_symbol, balance, created_at)
            VALUES (:user_id, 'USDT', 0, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([':user_id' => $user_id]);
    } else {
        $current_balance = floatval($current_balance);
    }
    
    $new_balance = $current_balance + $amount;
    
    // Prevent negative balance
    if ($new_balance < 0) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance for this operation']);
        exit;
    }
    
    // Update balance
    $stmt = $pdo->prepare("
        UPDATE user_balances 
        SET balance = :new_balance, updated_at = CURRENT_TIMESTAMP
        WHERE user_id = :user_id AND asset_symbol = 'USDT'
    ");
    $stmt->execute([
        ':new_balance' => $new_balance,
        ':user_id' => $user_id
    ]);
    
    // Log the transaction
    $admin_id = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
    $transaction_type = $amount > 0 ? 'credit' : 'debit';
    $description = $amount > 0 ? 'Admin credit adjustment' : 'Admin debit adjustment';
    
    $stmt = $pdo->prepare("
        INSERT INTO balance_transactions (
            user_id, asset_symbol, amount, transaction_type, 
            balance_before, balance_after, description, admin_id, created_at
        ) VALUES (
            :user_id, 'USDT', :amount, :transaction_type, 
            :balance_before, :balance_after, :description, :admin_id, CURRENT_TIMESTAMP
        )
    ");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':amount' => $amount,
        ':transaction_type' => $transaction_type,
        ':balance_before' => $current_balance,
        ':balance_after' => $new_balance,
        ':description' => $description,
        ':admin_id' => $admin_id
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Balance updated successfully',
        'old_balance' => $current_balance,
        'new_balance' => $new_balance,
        'amount_added' => $amount
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error in admin-balance-user.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>