<?php
function log_signup_error($error_message) {
    $log_file = __DIR__ . '/signup_errors.log';
    $date = date('Y-m-d H:i:s');
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $log_entry = "[$date] [IP: $client_ip] [UA: $user_agent] $error_message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>