<?php
// Enable strict error reporting and log errors to a file for debugging
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/admin_panel_error.log');
error_reporting(E_ALL);

session_start();
// Standardized admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
    error_log("[" . date('Y-m-d H:i:s') . "] Access denied: Not logged in. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: admin-login.html');
    exit;
}

// OPTIONAL: Set a session timeout (e.g., 30 minutes)
$timeout = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    error_log("[" . date('Y-m-d H:i:s') . "] Session expired for user " . ($_SESSION['admin_username'] ?? 'unknown') . ". IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    session_unset();
    session_destroy();
    header('Location: admin-login.html?error=Session expired, please log in again.');
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time(); // Update last activity time

// Grab admin info
$admin_username = htmlspecialchars($_SESSION['admin_username'] ?? 'unknown');
$admin_id = intval($_SESSION['admin_id'] ?? 0);

// Extra debug: Log session state for troubleshooting
error_log("[" . date('Y-m-d H:i:s') . "] Session valid for admin: $admin_username (ID: $admin_id), Session ID: " . session_id());

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enhanced Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CDN for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .navbar-brand { font-weight: 700; }
        .card { box-shadow:0 2px 16px rgba(0,0,0,0.07); }
        .logout-btn { background: #d7263d; color: #fff; }
        .logout-btn:hover { background: #a71d2a; color: #fff; }
        .admin-avatar {
            width: 56px; height: 56px;
            background: #e9ecef;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 600; color: #495057;
            margin-right: 1rem;
        }
        .admin-meta { line-height: 1.2; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <span class="navbar-brand">Admin Panel</span>
        <form class="d-flex ms-auto" method="post" action="logout.php">
            <button class="btn btn-sm logout-btn" type="submit">Logout</button>
        </form>
    </div>
</nav>
<div class="container" style="max-width:900px; margin-top:40px;">
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($admin_username, 0, 2)); ?>
            </div>
            <div class="admin-meta">
                <div><strong><?php echo $admin_username; ?></strong></div>
                <div class="text-muted" style="font-size:0.95em;">ID: <?php echo $admin_id; ?></div>
                <div class="text-muted" style="font-size:0.95em;">
                    Last login: <?php echo isset($_SESSION['LAST_LOGIN']) ? htmlspecialchars($_SESSION['LAST_LOGIN']) : "N/A"; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Dashboard</h5>
                </div>
                <div class="card-body">
                    <p>Welcome to the enhanced admin panel. Only authenticated admins can view this page.</p>
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item">🛡️ <a href="#">Manage Users</a></li>
                        <li class="list-group-item">📝 <a href="#">View Audit Logs</a></li>
                        <li class="list-group-item">🔑 <a href="#">Change Password</a></li>
                        <!-- Add more admin actions here -->
                    </ul>
                    <div class="alert alert-info mt-3">
                        <b>Tip:</b> For security, always log out when finished.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">Quick Info</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li><b>Session ID:</b> <code><?php echo session_id(); ?></code></li>
                        <li><b>IP Address:</b> <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown'); ?></li>
                        <li><b>User Agent:</b> <span style="font-size:0.85em;"><?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'); ?></span></li>
                        <li><b>Session Expires In:</b> 
                            <span id="session-timer"></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Optional: Session countdown timer -->
<script>
const expirySeconds = <?php echo $timeout - (time() - ($_SESSION['LAST_ACTIVITY'] ?? time())); ?>;
let remaining = expirySeconds;
function updateTimer() {
    if (remaining < 0) { document.getElementById('session-timer').innerText = "Expired"; return; }
    const min = Math.floor(remaining / 60);
    const sec = String(remaining % 60).padStart(2, '0');
    document.getElementById('session-timer').innerText = min + "m " + sec + "s";
    remaining--;
}
updateTimer();
setInterval(updateTimer, 1000);
</script>
</body>
</html>