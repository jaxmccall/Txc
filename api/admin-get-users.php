<?php
// File: api/admin-get-users.php
header('Content-Type: application/json');
require_once '../config.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$kyc_status = $_GET['kyc_status'] ?? '';
$email_verified = $_GET['email_verified'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = intval($_GET['per_page'] ?? 15);

// Calculate offset
$offset = ($page - 1) * $per_page;

try {
    // Build WHERE conditions
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE :search OR u.email LIKE :search OR u.username LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($status)) {
        if ($status === 'active') {
            $where_conditions[] = "u.is_active = 1";
        } elseif ($status === 'frozen') {
            $where_conditions[] = "u.is_active = 0";
        }
    }
    
    if (!empty($kyc_status)) {
        $where_conditions[] = "u.kyc_status = :kyc_status";
        $params[':kyc_status'] = $kyc_status;
    }
    
    if (!empty($email_verified)) {
        $where_conditions[] = "u.is_verified = :email_verified";
        $params[':email_verified'] = $email_verified === '1' ? 1 : 0;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(u.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(u.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM users u $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get users with pagination
    $sql = "
        SELECT 
            u.id,
            u.username,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.email,
            u.phone,
            u.country,
            u.is_verified,
            u.is_active,
            u.kyc_status,
            COALESCE(u.account_score, 100) as credit_score,
            COALESCE(ub.balance, 0) as wallet_balance,
            u.created_at,
            u.last_login,
            CASE 
                WHEN u.is_active = 1 THEN 'active' 
                ELSE 'frozen' 
            END as status,
            '192.168.1.1' as ip_address,
            COALESCE(u.country, 'Unknown') as location
        FROM users u
        LEFT JOIN user_balances ub ON u.id = ub.user_id AND ub.asset_symbol = 'USDT'
        $where_clause
        ORDER BY u.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // Bind other parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format user data
    foreach ($users as &$user) {
        $user['wallet_balance'] = floatval($user['wallet_balance']);
        $user['credit_score'] = intval($user['credit_score']);
        $user['account_score'] = $user['credit_score']; // Alias for compatibility
        $user['balance'] = $user['wallet_balance']; // Alias for compatibility
        $user['email_verified'] = (bool)$user['is_verified'];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total_count' => intval($total_count),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_count / $per_page)
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in admin-get-users.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>