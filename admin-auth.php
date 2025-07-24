<?php
session_start();
if (!isset($_SESSION['admin_id']) || time() - $_SESSION['last_activity'] > 1800) {
  session_destroy();
  header("Location: admin-login.html");
  exit;
}
$_SESSION['last_activity'] = time(); // Reset timer
?>
