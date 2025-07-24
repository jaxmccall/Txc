<?php
// File: /admin/api/admin-update-kyc-status.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

$id = isset($data['id']) ? intval($data['id']) : 0;
$status = isset($data['status']) ? $data['status'] : '';

if (!$id || !in_array($status, ['approved', 'rejected', 'pending'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$stmt = $pdo->prepare("UPDATE kyc_requests SET status = :status WHERE id = :id");
$ok = $stmt->execute([':status' => $status, ':id' => $id]);

echo json_encode(['success' => $ok]);
?>
