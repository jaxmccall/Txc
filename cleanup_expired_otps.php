<?php
require 'config.php';

$stmt = $pdo->prepare("DELETE FROM login_otps WHERE expires_at < NOW()");
$stmt->execute();

echo "Expired OTPs cleaned: " . $stmt->rowCount();
?>
