<?php
session_start();
require_once 'db-connect.php';

// Check admin session - standardized validation
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
    header('Location: admin-login.html');
    exit;
}

$adminName = $_SESSION['admin_username'] ?? 'Admin';

$stats = [
    'totalUsers' => '-',
    'pendingKYC' => '-',
    'pendingWithdrawals' => '-',
    'ongoingTrades' => '-',
    'platformBalance' => '-'
];

try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['totalUsers'] = $stmt->fetchColumn();

    // Pending KYC verifications
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending'");
    $stats['pendingKYC'] = $stmt->fetchColumn();

    // Pending withdrawals
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'");
    $stats['pendingWithdrawals'] = $stmt->fetchColumn();

    // Ongoing trades (open or processing)
    $stmt = $pdo->query("SELECT COUNT(*) FROM trades WHERE status IN ('open','processing')");
    $stats['ongoingTrades'] = $stmt->fetchColumn();

    // Platform balance
    $stmt = $pdo->query("SELECT SUM(balance) FROM platform_wallets");
    $balance = $stmt->fetchColumn();
    $stats['platformBalance'] = '$' . number_format($balance ?? 0, 2);
} catch (PDOException $e) {
    // If DB fails, $stats remain as '-'
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f9fafb;
      color: #22223b;
      margin: 0;
      padding: 0;
      min-height: 100vh;
    }
    .dashboard-container {
      max-width: 1100px;
      margin: 36px auto 0 auto;
      padding: 24px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 24px 0 rgba(0,0,0,0.09);
    }
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 20px;
    }
    .header h1 {
      font-size: 1.7rem;
      font-weight: 600;
      letter-spacing: -.03em;
      margin: 0;
    }
    .btn-logout {
      background: #ef233c;
      color: #fff;
      font-weight: 500;
      border: none;
      border-radius: 6px;
      padding: 9px 20px;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.18s;
      text-decoration: none;
      display: inline-block;
    }
    .btn-logout:hover {
      background: #ba1b2a;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 28px;
      margin-top: 24px;
    }
    .card {
      background: #f5f7fa;
      border-radius: 10px;
      padding: 28px 18px 24px 18px;
      box-shadow: 0 1px 7px 0 rgba(67,97,238,0.04);
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      min-height: 110px;
    }
    .card h3 {
      margin: 0 0 10px 0;
      font-size: 1.05rem;
      color: #4b5563;
      font-weight: 500;
      letter-spacing: -.01em;
    }
    .card p {
      margin: 0 0 0 0;
      font-size: 2rem;
      color: #22223b;
      font-weight: 600;
      letter-spacing: -.02em;
      word-break: break-all;
    }
    @media (max-width: 600px) {
      .dashboard-container {
        padding: 8px;
      }
      .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      .stats-grid {
        gap: 12px;
      }
      .card {
        padding: 18px 8px 16px 8px;
      }
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <div class="header">
      <h1>Welcome, <?php echo htmlspecialchars($adminName); ?></h1>
      <a href="admin-logout.php" class="btn-logout">Logout</a>
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
  </div>
</body>
</html>