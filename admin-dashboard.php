<?php
session_start();
require_once 'db-connect.php';

// Ensure only a logged-in admin (not impersonated) can view this
if (!isset($_SESSION['admin_id']) || isset($_SESSION['is_impersonated'])) {
    header('Location: admin-login.php');
    exit;
}

$adminName = $_SESSION['admin_username'] ?? 'Admin';

try {
    $stats = [];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM users");
    $stats['totalUsers'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM users WHERE kyc_status = 'pending'");
    $stats['pendingKYC'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM withdrawals WHERE status = 'pending'");
    $stats['pendingWithdrawals'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM trades WHERE status IN ('open', 'processing')");
    $stats['ongoingTrades'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT SUM(balance) AS total FROM platform_wallets");
    $balance = $stmt->fetchColumn();
    $stats['platformBalance'] = '$' . number_format($balance ?? 0, 2);

} catch (PDOException $e) {
    $stats = [
        'totalUsers' => '-',
        'pendingKYC' => '-',
        'pendingWithdrawals' => '-',
        'ongoingTrades' => '-',
        'platformBalance' => '-'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f9fafb;
      color: #1f2937;
      margin: 0;
      padding: 20px;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }
    .card {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 12px rgba(0,0,0,0.05);
    }
    .card h3 {
      margin: 0 0 10px;
      font-size: 1.1rem;
      color: #4b5563;
    }
    .card p {
      font-size: 1.5rem;
      margin: 0;
      color: #111827;
    }
    .btn-logout {
      background-color: #dc2626;
      color: white;
      padding: 8px 14px;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 500;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>Welcome, <?php echo htmlspecialchars($adminName); ?></h1>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>

  <div class="stats-grid">
    <div class="card">
      <h3>Total Users</h3>
      <p><?php echo $stats['totalUsers']; ?></p>
    </div>
    <div class="card">
      <h3>Pending KYC Verifications</h3>
      <p><?php echo $stats['pendingKYC']; ?></p>
    </div>
    <div class="card">
      <h3>Pending Withdrawals</h3>
      <p><?php echo $stats['pendingWithdrawals']; ?></p>
    </div>
    <div class="card">
      <h3>Ongoing Trades</h3>
      <p><?php echo $stats['ongoingTrades']; ?></p>
    </div>
    <div class="card">
      <h3>Platform Balance</h3>
      <p><?php echo $stats['platformBalance']; ?></p>
    </div>
  </div>
</body>
</html>
