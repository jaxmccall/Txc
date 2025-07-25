<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id']) || time() - ($_SESSION['LAST_ACTIVITY'] ?? 0) > 1800) {
  session_destroy();
  header("Location: admin-login.html");
  exit;
}
$_SESSION['LAST_ACTIVITY'] = time(); // Reset timer
?>
