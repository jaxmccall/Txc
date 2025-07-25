<?php
/**
 * User Data API - Tripple Exchange
 * Provides real user information for authenticated users
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function sendSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendError('Authentication required', 401);
}

$user_id = $_SESSION['user_id'];

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get user profile data
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    username,
                    email,
                    first_name,
                    last_name,
                    phone,
                    country,
                    is_verified,
                    kyc_status,
                    wallet_address,
                    account_score,
                    created_at,
                    last_login
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                sendError('User not found', 404);
            }
            
            // Get user activity summary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN transaction_type IN ('deposit', 'credit') THEN amount ELSE 0 END) as total_deposits,
                    SUM(CASE WHEN transaction_type IN ('withdrawal', 'debit') THEN amount ELSE 0 END) as total_withdrawals
                FROM balance_transactions 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent transactions
            $stmt = $pdo->prepare("
                SELECT 
                    asset_symbol,
                    amount,
                    transaction_type,
                    description,
                    created_at
                FROM balance_transactions 
                WHERE user_id = ?
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Remove sensitive data
            unset($user['password']);
            
            sendSuccess([
                'user' => $user,
                'activity' => $activity,
                'recent_transactions' => $recent_transactions
            ]);
            break;
            
        case 'POST':
            // Update user profile
            $input = json_decode(file_get_contents('php://input'), true);
            
            $allowed_fields = ['first_name', 'last_name', 'phone', 'country', 'wallet_address'];
            $update_fields = [];
            $update_values = [];
            
            foreach ($allowed_fields as $field) {
                if (isset($input[$field])) {
                    $update_fields[] = "$field = ?";
                    $update_values[] = $input[$field];
                }
            }
            
            if (empty($update_fields)) {
                sendError('No valid fields to update');
            }
            
            $update_values[] = $user_id;
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET " . implode(', ', $update_fields) . ", updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute($update_values);
            
            sendSuccess(['message' => 'Profile updated successfully']);
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
?>