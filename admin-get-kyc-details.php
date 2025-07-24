<?php
// File: /admin/api/admin-get-kyc-details.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

$stmt = $pdo->prepare("SELECT k.*, u.email, u.full_name FROM kyc_requests k LEFT JOIN users u ON k.user_id = u.id WHERE k.id = :id");
$stmt->execute([':id' => $id]);
$kyc = $stmt->fetch(PDO::FETCH_ASSOC);

if ($kyc) {
    echo json_encode(['success' => true, 'kyc' => $kyc]);
} else {
    echo json_encode(['success' => false, 'error' => 'KYC request not found']);
}
?>
