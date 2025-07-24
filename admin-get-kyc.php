<?php
// File: /admin/api/admin-get-kyc.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once 'config.php';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

// Optional search filter
if (!empty($_GET['search'])) {
    $where[] = "(u.email LIKE :search OR u.full_name LIKE :search)";
    $params[':search'] = '%'.$_GET['search'].'%';
}
if (!empty($_GET['status'])) {
    $where[] = "k.status = :status";
    $params[':status'] = $_GET['status'];
}
$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

// Count total rows for pagination
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM kyc_requests k LEFT JOIN users u ON k.user_id = u.id $where_sql");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$sql = "SELECT k.id, k.user_id, k.document_type, k.document_number, k.document_url, k.status, k.created_at, u.email, u.full_name
        FROM kyc_requests k
        LEFT JOIN users u ON k.user_id = u.id
        $where_sql
        ORDER BY k.created_at DESC
        LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'kyc' => $kyc,
    'page' => $page,
    'totalPages' => $totalPages,
]);
?>
